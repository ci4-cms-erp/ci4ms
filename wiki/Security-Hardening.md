One-sentence purpose: the checklist an operator should work through before exposing a ci4ms install to the internet — closing the open registration endpoint, confirming production mode, understanding captcha and DevGate, and applying web-server-level hardening.

## English

This is the most important page in this wiki. Read it fully before going to production.

### Closing the open `/backend/register` endpoint

**Status: left open by product decision, not a bug you need to report.** By default, `/backend/register` accepts requests from unauthenticated visitors and lets them create an account in the backend user table with no email verification. The account lands in Shield's default group, which ships with zero permissions, so it cannot reach any `backend/*` screen behind the permission filter — but it is still an unauthenticated write to the `users` / `auth_identities` tables and a spam/foothold surface. Nothing in the shipped codebase closes it automatically; if you want it closed, you have two independent options, verified against source. **They solve different problems — pick based on what you need.**

#### Option 1 — `Auth.allowRegistration = false` (functional kill-switch)

File: `modules/Auth/Config/Auth.php`

```php
public bool $allowRegistration = false;
```

The check lives inside the controller (`RegisterController`), so it holds regardless of how the controller is reached — this route, a future route pointing at the same controller, or anything else. The route still exists and still responds (a redirect with a flash error, not a 403/404), but no row is ever written to `users`.

#### Option 2 — remove the route

File: `modules/Auth/Config/Routes.php`

```php
service('auth')->routes($routes, ['namespace' => 'Modules\Auth\Controllers', 'except' => ['register']]);
```

This drops the route entirely — `/backend/register` returns a plain 404, before the controller (and therefore before the `allowRegistration` check) ever runs. It's route-table-only: it does not touch `allowRegistration`, so if a later change adds another route or call path to `RegisterController`, registration works again on that new path.

**Applying both is the strongest option**: the route 404s directly, and even if a route to the controller reappears later, `allowRegistration = false` still blocks it. The full technical comparison (enforcement point, response codes, what each option depends on) is in [docs/web-server-hardening.md](../docs/web-server-hardening.md#closing-the-open-backendregister-endpoint) — read it before deciding.

### Confirm the production environment

Both installation paths default to `production` and require an explicit, extra confirmation before they will write `CI_ENVIRONMENT = development`: `production` is the pre-selected radio button in the web installer, and `php spark ci4ms:setup` defaults its prompt to `production` and prints a red warning if you type `development`. Still, confirm the value directly in `.env` after installation — this matters because running in `development` mode enables the debug toolbar and detailed stack traces, which leak internal application details (including database credentials via error pages) to anyone who can reach the site. As an extra signal, the backend itself shows a permanent warning banner to superadmin accounts whenever it detects `ENVIRONMENT === 'development'`, so an accidental production deploy left in development mode is hard to miss once you're logged in. Both installers also narrow `.env`'s file permissions right after writing it (`0600` from the web installer, `0640` from the CLI) so the database password and encryption key aren't left world-readable. See [Configuration](Configuration.md#environment) for where this lives.

### Captcha

Login (and comment) captcha checks are controlled by the `Auth.captchaBypassInDevelopment` setting. The bypass only ever applies when **both** `ENVIRONMENT === 'development'` **and** the setting is enabled — the default seeded value is `false`, so captcha is enforced even in development unless you explicitly turn the bypass on. In production, `ENVIRONMENT` is never `'development'`, so the bypass path can never trigger regardless of the setting — captcha is effectively always required.

#### Login captcha could be skipped entirely (fixed in 0.36.0.0)

The login captcha is a guard against automated password guessing. It could be bypassed: the expected answer is only generated when the login page is opened (GET) and lives for a single request. A bot that never opened the login page and simply left the captcha field out of its POST ended up with an empty expected value on one side and an empty submitted value on the other, and the loose comparison treated "empty equals empty" as a match. The login validation rules also had no "captcha is required" rule to stop it. The result: an automated client could try passwords at machine speed without ever solving a captcha.

**What this was and was not.** A correct password was still required to log in, so this was **not** an authentication bypass. What you lost was the protection against automation. The `auth-rates` limit (10 requests per 60 seconds per IP) stayed active the whole time and still is.

**What changed (two layers):** the captcha field is now required by the login validation, and the comparison now rejects when there is no expected value (fail-closed) and uses a constant-time equality check.

**Development bypass.** The `ENVIRONMENT=development` + `Auth.captchaBypassInDevelopment` bypass still works: a wrong captcha value is accepted. A request that omits the captcha field entirely is now rejected even in development, by design. Normal browser development is unaffected because the form's input is already `required`; only scripts that never send the field notice. If your dev scripts or tests post to the login form, add a captcha field (any value) to the request.

**Affected versions:** this was an internal finding and the affected version range has not been measured. The fix ships in release `0.36.0.0`.

**Severity (two readings, shown deliberately).** As a modelling decision: `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N` — **5.3 (Medium)**, where `I:L` stands for the disabled anti-automation control. Read literally (no direct data impact): `C:N/I:N/A:N` — **0.0**. Both are given; no intermediate value is claimed.

**What to do:** update to `0.36.0.0` or later, and keep `auth-rates` enabled. Confirm `Auth.captchaBypassInDevelopment` is `false` (the default) on anything reachable from outside.

### Login and account enumeration

A failed login now always returns the same generic error (`Auth.loginFailed`), regardless of the actual reason — a wrong password, a username/email that doesn't exist, and a banned/inactive account all look identical to the person submitting the form. The real reason is written to `writable/logs/` instead, with the submitted identifier partially masked, so you can still diagnose a specific failed attempt as an operator without the login form itself telling a visitor which accounts exist.

**Still open: a timing side-channel remains even though the message doesn't leak anything.** CodeIgniter Shield's password check only runs the slow `password_verify()` (bcrypt) when it finds a matching account, and returns immediately when it doesn't — so the *time* the server takes to respond still differs by account existence, even though the text no longer does. Measured on this installation, a login attempt against an existing account took roughly 357 ms versus roughly 8 ms for a non-existent one, with no overlap between the two samples. An attacker who can send enough login attempts can still infer which usernames/emails exist by timing the response.
`CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` — **5.3 (Medium)**.

The `auth-rates` filter throttles repeated attempts against `backend/login*` and `backend/register`, which slows this down but does not close it — throttling limits how many timing samples an attacker can collect per unit of time, it does not equalize the two response times. If username/email privacy matters for your deployment, treat this as a residual risk: a stricter rate limit or a fixed artificial delay on the failure path would close it further, but neither ships today.

#### The magic link endpoint (`backend/login/magic-link`)

The sibling of the same enumeration problem (GHSA-cwhg-7xgv-343q) existed on the magic link form: it answered differently depending on whether the submitted address was registered — a 302 redirect with an error message (with the submitted email embedded in it) when no account existed, versus a 200 "email sent" page when one did. An SMTP failure could also only occur when the account existed, which was a second hint.

All three cases (account exists, account does not exist, SMTP failure) now return the **same page**: *"If an account is registered at this address, a login link has been sent."* When no account exists, no email is sent and nothing is written to the database. SMTP errors go to `writable/logs/` only — if you suspect magic link emails aren't going out, you will not see an error on screen, check the log. Input that fails format validation (not a valid email) still redirects back to the form with a validation error; that reveals nothing about whether an account exists.

**Affected versions:** `>= 0.31.0.0, <= 0.35.0.0`. **The patched release is `0.36.0.0`.** If you run `0.35.0.0` or earlier, this endpoint is open to enumeration; update, or use the workaround below to close it in the meantime.

**Bot protection restored.** `verify()` (the endpoint the emailed link opens) now returns 404 to robot user-agents. This was a check present in upstream Shield that had drifted out of this fork. The practical effect is a functional improvement: link-preview bots and email clients that pre-open the link no longer consume the single-use token.

**Still open: a timing channel on the magic link endpoint.** The messages are now identical, but when an account exists the SMTP send happens, so the response is slower; when it doesn't, it returns immediately. Address enumeration is therefore **still possible through timing**. Closing it needs a queue architecture that moves sending out of the request, and is awaiting the project owner's decision. No timing measurement has been taken on this endpoint; the login measurement above belongs to a different endpoint and does not transfer. A CVSS score for this endpoint has not been calculated yet. The `auth-rates` filter also covers this path (the `backend/login*` pattern) and slows sample collection, but does not close it.

**Workaround:** if you don't need magic link login, disable it in `modules/Auth/Config/Auth.php` (default `true`):

```php
public bool $allowMagicLinkLogins = false;
```

When disabled, the form, the send action and `verify()` all redirect to the login page with a flash error, and no account lookup is performed.

#### Alert box output (flash messages)

The alert boxes on the login, register, lock and install screens now escape HTML metacharacters (`Modules\Auth\Views\_message_block`). This applies to **only 8 views**: `login`, `register`, `lock`, `install`, `magic_link_form`, `email_2fa_verify`, `email_activate_show` and `temp-settings`.

**Still open — backend panel flash messages are not escaped.** Backend panel pages are out of scope for that change; they use a different partial (`sweetalert_message_block`) that prints flash values raw inside a JavaScript string, and it is loaded on every backend page. This is being examined as a separate finding and is **under review**; a CVSS score has not been calculated yet.

### Lock screen and CSRF

**Nothing to do — no operator action required.** This is closed in code and needs no configuration change.

The backend lock screen (`backend/lock` and `backend/lock/*`) used to be on the CSRF exemption list, so two state-changing POSTs were not CSRF-checked: the password check on the lock screen (three wrong attempts end the session completely) and the request that locks the session. The only defence was the `SameSite=Lax` cookie policy (the shipped default in `app/Config/Cookie.php`). The exemption is removed; both endpoints are now CSRF-validated like the rest of the backend.

**What this was and was not.** This is a **defence-in-depth** fix, not an exploitable hole in a default setup: `SameSite=Lax` already blocked a plain cross-site POST. Exploiting it would have needed a same-site foothold (a sibling subdomain, or an XSS elsewhere) or a browser that does not enforce Lax. If it had been exploited, a user's session could have been force-closed or force-locked — there is **no** data read or modification.

**Lock screen behaviour is unchanged for users.** Entering the password several times still works: a wrong password redirects back to the lock screen, and the lock form is a plain form that carries a CSRF field with a fresh token each time it is rendered. (The `X-CSRF-TOKEN` response header applies only to AJAX calls, such as the request that locks the session.)

**Affected versions:** this was an internal finding and the affected version range has not been measured. The fix ships in release `0.36.0.0`.

**Severity:** `CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:N/I:N/A:L` — **3.1 (Low)**.

### DevGate

DevGate is a separate, **development-only** Basic-Auth gate in front of the whole site (`modules/DevGate/`), unrelated to your admin account. It has zero effect once `CI_ENVIRONMENT` is anything other than `development` — nothing runs in production.

Both installation paths — the web installer and `php spark ci4ms:setup` — provision a fresh, independent DevGate credential automatically (reusing your admin username, but with its own generated, hashed password — never your admin password, so a DevGate credential leak cannot compromise the admin account). **This password is shown exactly once — on a one-time disclosure page after the web installer, or printed at the end of the CLI setup run — and cannot be recovered afterward.** Save it immediately. If you didn't save it, or if DevGate's config file couldn't be updated (e.g. not writable), you can generate a new hash yourself:

```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```

...and paste it into `modules/DevGate/Config/DevGate.php`. See the [DevGate module README](../modules/DevGate/README.md) for full configuration details (plaintext vs. hashed passwords, excluding paths).

### Audit notifications

Privilege-affecting actions raise a `ci4ms.audit` event that is delivered to the `superadmin` group as an in-app notification (bell dropdown). You do not have to switch this on — it is wired by default.

Two severities reach you:

- **`critical`** — a rejected privilege-escalation attempt. This fires when an account holding a permission-editing permission tries to grant itself or another user more access than it holds. The request is refused either way; the notification is how you find out it was made. **These cannot be muted**: the per-user opt-out screen deliberately cannot filter `critical` events.
- **`warning`** — a successful privilege-affecting change: a group created or updated, a user's groups or password changed, a permission page added or edited, a database backup restored (including a count of the RBAC statements the restore refused to replay), a migration or seed run, a file edited through the backend file editor.

Two things follow from this for an operator. First, a `critical` notification is worth investigating even though the attempt failed — it means an account with backend access is probing the delegation ceiling. Second, a burst of `warning` events you did not initiate is the earliest signal you get that a backend account has been taken over.

Superadmins can review the full list in the bell dropdown; notifications are also what `Backup::restore()` uses to tell you that RBAC rows in a restored dump were skipped rather than applied.

### Module and permission management lockdown

Module lifecycle actions in the backend Methods section — uploading a module ZIP, creating one via the UI, and deleting an installed module — now require the `superadmin` group specifically, not just a permission grant, because installing or creating a module puts new PHP code on disk that gets `require`d on the next request. Creating or updating an `auth_permissions_pages` row (the Methods form), and the module/page activate-deactivate toggle on the Methods list page, are superadmin-only for the same reason from the other direction: those rows are exactly what the backend authorization filter reads to decide whether a request is allowed. A fixed list of **protected modules** (`Auth`, `Backend`, `Install`, `Methods`, `Settings`, `LanguageManager`, `Pages`, `Blog`, `Theme`, `Users`, `DashboardWidgets`, `Media`, `Menu`) can never be edited or deleted through this screen, **not even by superadmin** — a deliberate guardrail against disabling something the backend itself depends on.

**Still open — two gaps worth knowing about if you ever delegate Methods permissions to a non-superadmin account:**

- **`Module Scan` has no superadmin gate.** Unlike upload/create/delete, `Methods::moduleScan()` is only gated by the `methods.modulescan.create` permission — any account holding it can trigger a scan, and a scan runs migrations and seeders for any module folder already sitting under `modules/` that hasn't been registered yet. If you grant Methods permissions narrowly, exclude Module Scan unless the account should also be able to execute pending migrations/seeders.
  `CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:H/A:L` — **7.1 (High)**.
- **The protected-modules list is not exhaustive.** It does not cover `Fileeditor`, `Backup`, `Notifications`, `MigrationManager`, or `Logs` — a superadmin (the only role that can reach module delete/deactivate at all, per the lockdown above) can still deactivate or delete any of these through the Methods screen with no extra confirmation. This is a narrower risk than the one above since it already requires superadmin, but if you rely on those modules being "always there," know they aren't specially protected the way the modules listed above are.

### RBAC permission data integrity

`auth_permissions_pages.typeOfPermissions` stores a small JSON object (`create_r`/`read_r`/`update_r`/`delete_r`) that the backend authorization filter reads on every request to decide which action a route requires. The Methods form now validates this on write, rejecting anything that isn't a non-empty list of `create`/`read`/`update`/`delete`. On the read side, a malformed value (invalid JSON, or valid JSON that isn't an object) no longer crashes the request — but the fallback is **fail-open to `read`**, the least restrictive action, not fail-closed. In practice a corrupted or hand-edited permission row silently becomes *more* permissive instead of blocking access.

If you ever edit `auth_permissions_pages` directly in the database (outside the Methods UI), check for corrupted rows with (add your table prefix, e.g. `ci4ms_auth_permissions_pages`, if `database.default.DBPrefix` is set):

```sql
SELECT id, pagename FROM auth_permissions_pages
WHERE JSON_VALID(typeOfPermissions) = 0 OR JSON_TYPE(typeOfPermissions) <> 'OBJECT';
```

### Media manager (elFinder) uploads

The backend Media module bundles elFinder 2.1.70 (`studio-42/elfinder`) with its own write-path validation layered on top: every upload, rename, or new file/folder name is checked against an extension denylist (`Modules\Media\Controllers\Media::DENIED_EXTENSIONS`) through elFinder's `acceptedName` hook, not only its `accessControl` hook — uploads go through `acceptedName` but not `accessControl`, so relying on the latter alone would have missed them. This also closes a bypass class where a multi-segment filename like `shell.php.png` slips past a check that only inspects the final extension. `public/media/.htaccess` mirrors the same extension list at the web-server level (**Apache only** — see [Web-server hardening](#web-server-hardening) below for nginx/Caddy/FrankenPHP). Only accounts with the `media.media.create` permission (or superadmin) can run write commands in the Media manager; everyone else gets a read-only file browser.

### Web-server hardening

Beyond application-level settings, ci4ms ships defense-in-depth `.htaccess` rules that block PHP execution inside upload directories (`public/templates/`, `public/media/`, `public/uploads/`) — but **these only work on Apache**. If you deploy on nginx, Caddy, or FrankenPHP, `.htaccess` is silently ignored and you must add the equivalent rules to your server config manually. [docs/web-server-hardening.md](../docs/web-server-hardening.md) covers:

- The exact nginx `location` blocks to mirror the Apache rules
- A known FlyEnv/PhpWebStudy PHP-location misconfiguration that can allow path-info-based execution tricks (`/media/photo.jpg/x.php`), and how to close it
- HSTS, `X-Frame-Options`, `Referrer-Policy`, and other production response headers
- A `curl` command to verify uploaded payloads genuinely cannot execute after deploy

**Read this document if you deploy on anything other than Apache.**

### Reporting a vulnerability

If you find a security vulnerability in ci4ms, do not open a public issue. Report it by email as described in [SECURITY.md](../SECURITY.md). Past reporters are credited in the README's [Security Hall of Fame](../README.md#-security-hall-of-fame).

## Türkçe

Bu, wiki'deki en önemli sayfadır. Production'a çıkmadan önce baştan sona okuyun.

### Açık `/backend/register` endpoint'ini kapatma

**Durum: ürün kararı olarak açık bırakıldı, rapor etmeniz gereken bir hata değil.** Varsayılan olarak `/backend/register`, kimliği doğrulanmamış ziyaretçilerden gelen istekleri kabul eder ve e-posta doğrulaması olmadan backend kullanıcı tablosunda bir hesap oluşturmalarına izin verir. Hesap, sıfır izinle gelen Shield'in varsayılan grubuna düşer, bu yüzden izin filtresinin arkasındaki hiçbir `backend/*` ekranına ulaşamaz — ama yine de `users` / `auth_identities` tablolarına kimliksiz bir yazma işlemi ve bir spam/dayanak noktası yüzeyidir. Mevcut kod tabanında bunu otomatik kapatan hiçbir şey yoktur; kapatmak isterseniz, kaynak koddan doğrulanmış iki bağımsız seçeneğiniz vardır. **Farklı sorunları çözerler — ihtiyacınıza göre seçin.**

#### Seçenek 1 — `Auth.allowRegistration = false` (fonksiyonel kapatma anahtarı)

Dosya: `modules/Auth/Config/Auth.php`

```php
public bool $allowRegistration = false;
```

Kontrol controller'ın içinde (`RegisterController`) yaşar, bu yüzden controller'a nasıl ulaşılırsa ulaşılsın geçerlidir — bu route, aynı controller'a işaret eden gelecekteki bir route veya başka herhangi bir yol. Route hâlâ var olur ve hâlâ yanıt verir (403/404 değil, flash hata mesajlı bir redirect), ama `users` tablosuna asla satır yazılmaz.

#### Seçenek 2 — route'u kaldırma

Dosya: `modules/Auth/Config/Routes.php`

```php
service('auth')->routes($routes, ['namespace' => 'Modules\Auth\Controllers', 'except' => ['register']]);
```

Bu, route'u tamamen kaldırır — `/backend/register`, controller çalışmadan (dolayısıyla `allowRegistration` kontrolü hiç işletilmeden) önce düz bir 404 döner. Yalnızca route tablosuna özeldir: `allowRegistration`'a dokunmaz, bu yüzden ileride `RegisterController`'a işaret eden başka bir route veya çağrı yolu eklenirse, register o yeni yolda tekrar çalışır.

**İkisini birden uygulamak en güçlü seçenektir**: route doğrudan 404 verir, ve ileride controller'a giden bir route yeniden ortaya çıksa bile `allowRegistration = false` onu yine engeller. Tam teknik karşılaştırma (uygulama noktası, yanıt kodları, her seçeneğin neye bağlı olduğu) [docs/web-server-hardening.md](../docs/web-server-hardening.md#closing-the-open-backendregister-endpoint) içindedir — karar vermeden önce okuyun.

### Production ortamını teyit edin

Her iki kurulum yolu da varsayılan olarak `production`'dır ve `CI_ENVIRONMENT = development` yazmadan önce ek, açık bir onay ister: web installer'da `production` önceden seçili radio butonudur, `php spark ci4ms:setup` da hem prompt'unun varsayılanı `production`'dır hem de `development` yazarsanız kırmızı bir uyarı basar. Yine de kurulumdan sonra bunu `.env` içinde doğrudan teyit edin — çünkü `development` modunda çalışmak debug toolbar'ı ve ayrıntılı stack trace'leri etkinleştirir (veritabanı şifresi dahil), bu da siteye ulaşabilen herkese uygulamanın iç detaylarını sızdırır. Ek bir sinyal olarak, backend `ENVIRONMENT === 'development'` olduğunu tespit ettiğinde superadmin hesaplarına kalıcı bir uyarı bandı gösterir; böylece yanlışlıkla development modunda bırakılmış bir production deploy, giriş yaptıktan sonra gözden kaçmaz. Her iki installer da `.env`'i yazdıktan hemen sonra dosya izinlerini de daraltır (web installer `0600`, CLI `0640`), böylece veritabanı şifresi ve şifreleme anahtarı herkesin okuyabileceği şekilde açıkta kalmaz. Bunun nerede olduğu için [Yapılandırma](Configuration.md#ortam) sayfasına bakın.

### Captcha

Login (ve yorum) captcha kontrolleri `Auth.captchaBypassInDevelopment` ayarıyla kontrol edilir. Bypass yalnızca **hem** `ENVIRONMENT === 'development'` **hem de** ayar etkinleştirilmişse devreye girer — seed edilen varsayılan değer `false`'tur, yani bypass'ı açıkça açmadığınız sürece development ortamında bile captcha zorunludur. Production'da `ENVIRONMENT` asla `'development'` olmayacağından, ayarın değeri ne olursa olsun bypass yolu hiçbir zaman tetiklenemez — captcha fiilen her zaman zorunludur.

#### Login captcha'sı tamamen atlanabiliyordu (0.36.0.0'da düzeltildi)

Login captcha'sı, otomatik parola denemelerine karşı bir korumadır. Atlatılabiliyordu: beklenen cevap yalnızca giriş sayfası açıldığında (GET) üretiliyor ve tek istek ömürlü. Giriş sayfasını hiç açmayan ve POST'unda captcha alanını da göndermeyen bir bot'ta bir tarafta boş beklenen değer, diğer tarafta boş gönderilen değer kalıyordu; gevşek karşılaştırma "boş eşittir boş" diyerek bunu eşleşme sayıyordu. Login doğrulama kurallarında da "captcha zorunlu" kuralı yoktu. Sonuç: otomatik bir istemci captcha çözmeden makine hızında parola deneyebiliyordu.

**Bunun ne olduğu ve ne olmadığı.** Giriş yine doğru parola gerektiriyordu; yani bu bir kimlik doğrulama atlatması **değildir**. Kaybedilen şey otomasyona karşı korumaydı. `auth-rates` sınırı (IP başına 60 saniyede 10 istek) bu süre boyunca da devredeydi ve devrede kalıyor.

**Ne değişti (iki katman):** captcha alanı artık login doğrulamasında zorunlu; karşılaştırma ise beklenen değer yoksa reddediyor (fail-closed) ve sabit zamanlı eşitlik kontrolü kullanıyor.

**Geliştirme ortamı bypass'ı.** `ENVIRONMENT=development` + `Auth.captchaBypassInDevelopment` bypass'ı hâlâ çalışıyor: yanlış captcha değeri kabul edilir. Captcha alanını **hiç göndermeyen** istek ise artık development'ta bile reddedilir; bu bilinçli bir karardır. Tarayıcıdan yapılan normal geliştirme etkilenmez, çünkü formun input'u zaten `required`; yalnızca alanı hiç yollamayan betikler fark eder. Login formuna POST atan geliştirme betikleriniz veya testleriniz varsa isteğe bir captcha alanı ekleyin (değeri önemli değil).

**Etkilenen sürümler:** bu bir iç bulgudur ve etkilenen sürüm aralığı ölçülmedi. Düzeltme `0.36.0.0` sürümüyle geliyor.

**Önem derecesi (iki okuma, bilinçli olarak).** Modelleme kararı olarak: `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N` — **5.3 (Orta/Medium)**; buradaki `I:L`, devre dışı kalan anti-otomasyon kontrolünü temsil eder. Harfiyen okunursa (doğrudan veri etkisi yok): `C:N/I:N/A:N` — **0.0**. İkisi de verilmiştir; ara değer iddia edilmez.

**Yapmanız gereken:** `0.36.0.0` veya daha yeni bir sürüme güncelleyin ve `auth-rates`'i açık tutun. Dışarıdan erişilebilen her ortamda `Auth.captchaBypassInDevelopment` değerinin `false` (varsayılan) olduğunu teyit edin.

### Login ve hesap numaralandırması

Başarısız bir login artık her zaman aynı genel hata mesajını (`Auth.loginFailed`) döner — gerçek sebep ne olursa olsun: yanlış şifre, var olmayan bir kullanıcı adı/e-posta, ya da banlı/pasif bir hesap, formu gönderen kişiye aynı görünür. Gerçek sebep bunun yerine `writable/logs/` içine yazılır (gönderilen kimlik kısmen maskelenmiş olarak); yani operatör olarak login formunun ziyaretçiye hangi hesapların var olduğunu söylemesine gerek kalmadan belirli bir başarısız denemeyi hâlâ teşhis edebilirsiniz.

**Hâlâ açık: mesaj sızdırmasa da bir zamanlama yan kanalı devam ediyor.** CodeIgniter Shield'in şifre kontrolü, eşleşen bir hesap bulduğunda yavaş `password_verify()` (bcrypt) fonksiyonunu çalıştırır, bulamadığında ise hemen döner — yani sunucunun yanıt verme *süresi*, metin artık sızdırmasa bile hesabın var olup olmamasına göre hâlâ farklıdır. Bu kurulumda ölçüldüğünde, var olan bir hesaba karşı login denemesi yaklaşık 357 ms sürerken var olmayan bir hesap için bu yaklaşık 8 ms sürdü — iki örneklem arasında örtüşme yoktu. Yeterince login denemesi gönderebilen bir saldırgan, yanıtı zamanlayarak hangi kullanıcı adı/e-postaların var olduğunu hâlâ çıkarabilir.
`CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` — **5.3 (Orta/Medium)**.

`auth-rates` filtresi `backend/login*` ve `backend/register`'a yönelik tekrarlı denemeleri throttle eder, bu da bunu yavaşlatır ama kapatmaz — throttle, bir saldırganın birim zamanda toplayabileceği zamanlama örneği sayısını sınırlar, iki yanıt süresini eşitlemez. Kurulumunuzda kullanıcı adı/e-posta gizliliği önemliyse, bunu kalıcı bir risk olarak değerlendirin: daha sıkı bir rate limit veya başarısızlık yolunda sabit yapay bir gecikme bunu daha da kapatır, ama ikisi de bugün gelmiyor.

#### Magic link ucu (`backend/login/magic-link`)

Aynı hesap numaralandırma sorununun kardeşi (GHSA-cwhg-7xgv-343q), magic link formunda da vardı: form, girilen adresin kayıtlı olup olmamasına göre farklı yanıt veriyordu — hesap yoksa hata mesajıyla bir 302 yönlendirmesi (girilen e-posta mesajın içine gömülü), hesap varsa 200 ile "e-posta gönderildi" sayfası. Ayrıca SMTP hatası yalnızca hesap varken üretilebildiği için ikinci bir ipucu veriyordu.

Artık üç durumda da (hesap var, hesap yok, SMTP hatası) **aynı sayfa** dönüyor: *"Bu adrese kayıtlı bir hesap varsa giriş bağlantısı gönderildi."* Hesap yoksa hiç e-posta gönderilmiyor ve veritabanına kayıt yazılmıyor. SMTP hataları yalnızca `writable/logs/` içine düşüyor — magic link e-postalarının gitmediğinden şüpheleniyorsanız ekranda hata görmeyeceksiniz, log'a bakın. Biçim doğrulamasından geçemeyen girdi (geçersiz e-posta) hâlâ form hatasıyla yönlendirilir; bu hesabın varlığı hakkında bilgi vermez.

**Etkilenen sürümler:** `>= 0.31.0.0, <= 0.35.0.0`. **Yamalı sürüm `0.36.0.0`'dır.** `0.35.0.0` veya daha eski bir sürümü çalıştırıyorsanız bu uç numaralandırmaya açıktır; güncelleyin ya da bu arada kapatmak için aşağıdaki geçici çözümü kullanın.

**Bot koruması geri geldi.** `verify()` (e-postadaki bağlantının açıldığı uç) artık robot user-agent'lara 404 dönüyor. Upstream Shield'da bulunan ama bu fork'ta kaymış bir kontroldü. Pratik etkisi işlevsel bir iyileşme: link önizlemesi yapan botlar ve bağlantıyı önceden açan e-posta istemcileri artık tek kullanımlık token'ı tüketmiyor.

**Hâlâ açık: magic link ucunda zamanlama kanalı.** Mesajlar eşitlendi ama hesap varsa SMTP gönderimi yapıldığı için yanıt daha yavaş dönüyor; hesap yoksa anında dönüyor. Yani adres numaralandırması **zamanlama üzerinden hâlâ mümkün**. Kapatmak, gönderimi isteğin dışına alan bir kuyruk mimarisi gerektiriyor ve proje sahibinin kararını bekliyor. Bu uçta zamanlama ölçümü yapılmadı; yukarıdaki login ölçümü başka bir uca ait, buraya uygulanamaz. CVSS skoru bu uç için henüz hesaplanmadı. `auth-rates` filtresi bu yolu da kapsar (`backend/login*` deseni) ve örnek toplamayı yavaşlatır, ama kapatmaz.

**Geçici çözüm:** magic link girişine ihtiyacınız yoksa `modules/Auth/Config/Auth.php` içinde kapatın (varsayılan `true`):

```php
public bool $allowMagicLinkLogins = false;
```

Kapalıyken form, gönderim ve `verify()` ucu, giriş sayfasına flash hata mesajıyla yönlendirir; hesap sorgulaması yapılmaz.

#### Uyarı kutularının çıktısı (flash mesajlar)

Giriş, kayıt, kilit ve kurulum ekranlarında gösterilen uyarı kutuları artık HTML meta-karakterlerini kaçırıyor (`Modules\Auth\Views\_message_block`). Bu **yalnızca 8 view** için geçerli: `login`, `register`, `lock`, `install`, `magic_link_form`, `email_2fa_verify`, `email_activate_show` ve `temp-settings`.

**Hâlâ açık — backend panel flash mesajları escape'siz.** Backend panel sayfaları kapsam dışı; bunlar farklı bir partial (`sweetalert_message_block`) kullanıyor ve bu partial flash değerlerini bir JavaScript string'inin içine ham basıyor; her backend sayfasına yükleniyor. Bu ayrı bir bulgu olarak **inceleme altında**; CVSS skoru henüz hesaplanmadı.

### Kilit ekranı ve CSRF

**Yapmanız gereken bir şey yok — operatör aksiyonu gerekmiyor.** Kod tarafında kapandı, yapılandırma değişikliği istemiyor.

Backend kilit ekranı uçları (`backend/lock` ve `backend/lock/*`) CSRF muafiyet listesindeydi; yani durum değiştiren iki POST CSRF doğrulamasından muaftı: kilit ekranındaki parola doğrulama isteği (3 hatalı denemede oturum tamamen kapanır) ve oturumu kilitleme isteği. Tek savunma `SameSite=Lax` çerez politikasıydı (`app/Config/Cookie.php` içindeki varsayılan). Muafiyet kaldırıldı; iki uç da artık backend'in geri kalanı gibi CSRF doğrulamasına tabi.

**Bunun ne olduğu ve ne olmadığı.** Bu bir **derinlikte-savunma** düzeltmesidir; varsayılan kurulumda sömürülebilir bir açık değildi: `SameSite=Lax` düz cross-site POST'u zaten engelliyordu. Sömürü için aynı-site bir dayanak (kardeş alt alan adı ya da başka yerde bir XSS) ya da Lax uygulamayan bir tarayıcı gerekirdi. Sömürülseydi kullanıcının oturumu zorla kapatılabilir ya da zorla kilitlenebilirdi — veri okuma veya değiştirme **yok**.

**Kullanıcı için kilit ekranı davranışı değişmedi.** Parolayı birkaç kez denemek hâlâ çalışır: hatalı parola kilit ekranına geri yönlendirir ve kilit formu, her çizildiğinde taze bir token içeren CSRF alanı taşıyan düz bir formdur. (`X-CSRF-TOKEN` yanıt header'ı yalnız AJAX çağrıları için geçerlidir; oturumu kilitleyen istek gibi.)

**Etkilenen sürümler:** bu bir iç bulgudur ve etkilenen sürüm aralığı ölçülmedi. Düzeltme `0.36.0.0` sürümüyle geliyor.

**Önem derecesi:** `CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:N/I:N/A:L` — **3.1 (Düşük/Low)**.

### DevGate

DevGate, tüm sitenin önünde ayrı, **yalnızca geliştirme ortamı için** bir Basic-Auth kapısıdır (`modules/DevGate/`), admin hesabınızla ilgisi yoktur. `CI_ENVIRONMENT`, `development` dışında herhangi bir değer olduğunda hiçbir etkisi yoktur — production'da hiçbir şey çalışmaz.

Her iki kurulum yolu da — web installer ve `php spark ci4ms:setup` — otomatik olarak yeni ve bağımsız bir DevGate kimlik bilgisi üretir (admin kullanıcı adınızı yeniden kullanır, ama kendi üretilmiş, hash'lenmiş şifresiyle — asla admin şifreniz değildir, bu yüzden bir DevGate kimlik bilgisi sızıntısı admin hesabını tehlikeye atamaz). **Bu şifre yalnızca bir kez gösterilir** — web installer'dan sonra tek seferlik bir açıklama sayfasında, ya da CLI setup çalışmasının sonunda yazdırılır — **ve sonrasında geri alınamaz.** Hemen kaydedin. Kaydetmediyseniz veya DevGate'in config dosyası güncellenemediyse (örneğin yazılabilir değilse), kendiniz yeni bir hash üretebilirsiniz:

```bash
php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
```

...ve bunu `modules/DevGate/Config/DevGate.php` içine yapıştırın. Tam yapılandırma detayları (düz metin ve hash'lenmiş şifreler, yol hariç tutma) için [DevGate modülü README](../modules/DevGate/README.md) dosyasına bakın.

### Denetim bildirimleri

Yetkiyi etkileyen işlemler, `superadmin` grubuna uygulama içi bildirim (zil menüsü) olarak iletilen bir `ci4ms.audit` olayı üretir. Bunu açmanız gerekmez — varsayılan olarak bağlıdır.

Size iki önem derecesi ulaşır:

- **`critical`** — reddedilmiş bir yetki yükseltme denemesi. İzin düzenleme yetkisi olan bir hesap, kendisine veya başka bir kullanıcıya sahip olduğundan fazla yetki vermeye çalıştığında tetiklenir. İstek her hâlükârda reddedilir; bildirim, denemenin yapıldığını öğrenme yolunuzdur. **Bunlar susturulamaz**: kullanıcı bazlı bildirim kapatma ekranı `critical` olayları bilinçli olarak filtreleyemez.
- **`warning`** — yetkiyi etkileyen başarılı bir değişiklik: grup oluşturma veya güncelleme, bir kullanıcının gruplarının veya parolasının değişmesi, izin sayfası ekleme/düzenleme, veritabanı yedeği geri yükleme (geri yüklemenin uygulamayı reddettiği RBAC ifadelerinin sayısı dahil), migration veya seed koşusu, backend dosya düzenleyicisinden dosya değişikliği.

Operatör açısından bundan iki sonuç çıkar. Birincisi, deneme başarısız olsa bile bir `critical` bildirimi incelemeye değer — backend erişimi olan bir hesabın delegasyon tavanını yokladığı anlamına gelir. İkincisi, sizin başlatmadığınız bir `warning` yığını, bir backend hesabının ele geçirildiğine dair alacağınız en erken sinyaldir.

Superadmin'ler tam listeyi zil menüsünden inceleyebilir; `Backup::restore()` de geri yüklenen bir dump içindeki RBAC satırlarının uygulanmayıp atlandığını size bu bildirimlerle söyler.

### Modül ve izin yönetiminin kilitlenmesi

Backend Methods bölümündeki modül yaşam döngüsü işlemleri — bir modül ZIP'i yükleme, UI'dan modül oluşturma ve kurulu bir modülü silme — artık yalnızca bir izin verilmesini değil, özel olarak `superadmin` grubunu gerektiriyor; çünkü bir modül yüklemek veya oluşturmak, bir sonraki istekte `require` edilecek yeni PHP kodunun diske inmesi demek. Bir `auth_permissions_pages` satırı oluşturma/güncelleme (Methods formu) ve Methods liste sayfasındaki modül/sayfa aktif-pasif anahtarı da aynı sebebin diğer yönünden superadmin'e kapalı: bu satırlar, backend yetkilendirme filtresinin bir isteğe izin verilip verilmeyeceğine karar vermek için okuduğu tam olarak o veridir. Sabit bir **korumalı modüller** listesi (`Auth`, `Backend`, `Install`, `Methods`, `Settings`, `LanguageManager`, `Pages`, `Blog`, `Theme`, `Users`, `DashboardWidgets`, `Media`, `Menu`) bu ekrandan **superadmin tarafından bile** asla düzenlenemez veya silinemez — backend'in kendisinin bağımlı olduğu bir şeyi devre dışı bırakmaya karşı bilinçli bir koruma.

**Hâlâ açık — Methods izinlerini superadmin olmayan bir hesaba devrederseniz bilmeniz gereken iki boşluk:**

- **`Module Scan`'in superadmin kapısı yok.** Upload/create/delete'in aksine, `Methods::moduleScan()` yalnızca `methods.modulescan.create` izniyle korunuyor — bu izne sahip herhangi bir hesap bir tarama tetikleyebilir, ve bir tarama, `modules/` altında zaten duran ama henüz kaydedilmemiş herhangi bir modül klasörü için migration ve seeder çalıştırır. Methods izinlerini dar kapsamlı verecekseniz, hesabın bekleyen migration/seeder'ları da çalıştırabilmesini istemiyorsanız Module Scan'i dışarıda bırakın.
  `CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:N/I:H/A:L` — **7.1 (Yüksek/High)**.
- **Korumalı modüller listesi eksiksiz değil.** `Fileeditor`, `Backup`, `Notifications`, `MigrationManager` veya `Logs`'u kapsamıyor — superadmin (yukarıdaki kilitlenmeye göre modül silme/pasifleştirmeye zaten tek ulaşabilen rol) bu modüllerden herhangi birini Methods ekranından, ek bir onay olmadan pasifleştirebilir veya silebilir. Bu, yukarıdakinden daha dar bir risktir çünkü zaten superadmin gerektiriyor, ama Fileeditor/Backup/MigrationManager modüllerinizin "her zaman orada olacağına" güveniyorsanız, bunların yukarıdaki modüller gibi özel olarak korunmadığını bilin.

### RBAC izin verisi bütünlüğü

`auth_permissions_pages.typeOfPermissions`, backend yetkilendirme filtresinin her istekte bir route'un hangi eylemi gerektirdiğine karar vermek için okuduğu küçük bir JSON nesnesi (`create_r`/`read_r`/`update_r`/`delete_r`) taşır. Methods formu artık bunu yazma tarafında doğruluyor ve `create`/`read`/`update`/`delete`'ten oluşan boş olmayan bir listeden başka her şeyi reddediyor. Okuma tarafında, bozuk bir değer (geçersiz JSON, ya da nesne olmayan geçerli JSON) artık isteği çökertmiyor — ama fallback **`read`'e fail-open**, yani en kısıtlayıcı olmayan eyleme düşüyor, fail-closed değil. Pratikte bu, bozulmuş veya elle düzenlenmiş bir izin satırının erişimi engellemek yerine sessizce *daha izinli* hale gelmesi demek.

`auth_permissions_pages`'i Methods UI dışında doğrudan veritabanında düzenlerseniz, bozuk satırları şu sorguyla kontrol edin (`database.default.DBPrefix` ayarlıysa tablo prefix'inizi ekleyin, örn. `ci4ms_auth_permissions_pages`):

```sql
SELECT id, pagename FROM auth_permissions_pages
WHERE JSON_VALID(typeOfPermissions) = 0 OR JSON_TYPE(typeOfPermissions) <> 'OBJECT';
```

### Medya yöneticisi (elFinder) yüklemeleri

Backend Media modülü elFinder 2.1.70'i (`studio-42/elfinder`) kendi write-path doğrulaması üzerine katmanlanmış şekilde paketler: her yükleme, yeniden adlandırma veya yeni dosya/klasör adı, elFinder'ın `acceptedName` hook'u üzerinden bir uzantı deny-list'ine (`Modules\Media\Controllers\Media::DENIED_EXTENSIONS`) karşı kontrol edilir — yalnızca `accessControl` hook'u üzerinden değil; upload işlemleri `acceptedName`'den geçer ama `accessControl`'den geçmez, dolayısıyla yalnızca ikincisine güvenmek bu işlemleri gözden kaçırırdı. Bu aynı zamanda `shell.php.png` gibi çok parçalı bir dosya adının, yalnızca son uzantıyı kontrol eden bir denetimden sıyrılmasını sağlayan bypass sınıfını da kapatır. `public/media/.htaccess`, aynı uzantı listesini web-sunucusu seviyesinde yansıtır (**yalnızca Apache** — nginx/Caddy/FrankenPHP için aşağıdaki [Web-server sertleştirme](#web-server-sertleştirme) bölümüne bakın). Medya yöneticisinde yalnızca `media.media.create` iznine (veya superadmin'e) sahip hesaplar write komutu çalıştırabilir; diğer herkes salt-okunur bir dosya tarayıcısı görür.

### Web-server sertleştirme

Uygulama seviyesindeki ayarların ötesinde, ci4ms yükleme dizinleri içinde (`public/templates/`, `public/media/`, `public/uploads/`) PHP çalıştırmayı engelleyen derinlemesine savunma `.htaccess` kuralları ile gelir — ama **bunlar yalnızca Apache'de çalışır**. nginx, Caddy veya FrankenPHP üzerinde deploy ediyorsanız `.htaccess` sessizce yok sayılır ve eşdeğer kuralları sunucu yapılandırmanıza elle eklemeniz gerekir. [docs/web-server-hardening.md](../docs/web-server-hardening.md) şunları kapsar:

- Apache kurallarının aynısını sağlayan tam nginx `location` blokları
- FlyEnv/PhpWebStudy'de bilinen bir PHP-location yanlış yapılandırması, path-info tabanlı çalıştırma hilelerine (`/media/photo.jpg/x.php`) izin verebilir — nasıl kapatılacağı dahil
- HSTS, `X-Frame-Options`, `Referrer-Policy` ve diğer production yanıt başlıkları
- Deploy sonrası yüklenen payload'ların gerçekten çalıştırılamadığını doğrulamak için bir `curl` komutu

**Apache dışında herhangi bir şey üzerine deploy ediyorsanız bu dokümanı okuyun.**

### Güvenlik açığı bildirimi

ci4ms'te bir güvenlik açığı bulursanız, herkese açık bir issue açmayın. [SECURITY.md](../SECURITY.md) içinde açıklandığı şekilde e-posta ile bildirin. Geçmiş bildirenler README'nin [Security Hall of Fame](../README.md#-security-hall-of-fame) bölümünde onurlandırılır.
