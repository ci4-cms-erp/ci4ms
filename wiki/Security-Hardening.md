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

The web installer writes `CI_ENVIRONMENT = production` into `.env` automatically during setup. Still, confirm it directly in `.env` after installation — this matters because running in `development` mode enables the debug toolbar and detailed stack traces, which leak internal application details to anyone who can reach the site. See [Configuration](Configuration.md#environment) for where this lives.

### Captcha

Login (and comment) captcha checks are controlled by the `Auth.captchaBypassInDevelopment` setting. The bypass only ever applies when **both** `ENVIRONMENT === 'development'` **and** the setting is enabled — the default seeded value is `false`, so captcha is enforced even in development unless you explicitly turn the bypass on. In production, `ENVIRONMENT` is never `'development'`, so the bypass path can never trigger regardless of the setting — captcha is effectively always required.

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

Web installer, kurulum sırasında `.env`'e otomatik olarak `CI_ENVIRONMENT = production` yazar. Yine de kurulumdan sonra bunu `.env` içinde doğrudan teyit edin — çünkü `development` modunda çalışmak debug toolbar'ı ve ayrıntılı stack trace'leri etkinleştirir, bu da siteye ulaşabilen herkese uygulamanın iç detaylarını sızdırır. Bunun nerede olduğu için [Yapılandırma](Configuration.md#ortam) sayfasına bakın.

### Captcha

Login (ve yorum) captcha kontrolleri `Auth.captchaBypassInDevelopment` ayarıyla kontrol edilir. Bypass yalnızca **hem** `ENVIRONMENT === 'development'` **hem de** ayar etkinleştirilmişse devreye girer — seed edilen varsayılan değer `false`'tur, yani bypass'ı açıkça açmadığınız sürece development ortamında bile captcha zorunludur. Production'da `ENVIRONMENT` asla `'development'` olmayacağından, ayarın değeri ne olursa olsun bypass yolu hiçbir zaman tetiklenemez — captcha fiilen her zaman zorunludur.

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

### Web-server sertleştirme

Uygulama seviyesindeki ayarların ötesinde, ci4ms yükleme dizinleri içinde (`public/templates/`, `public/media/`, `public/uploads/`) PHP çalıştırmayı engelleyen derinlemesine savunma `.htaccess` kuralları ile gelir — ama **bunlar yalnızca Apache'de çalışır**. nginx, Caddy veya FrankenPHP üzerinde deploy ediyorsanız `.htaccess` sessizce yok sayılır ve eşdeğer kuralları sunucu yapılandırmanıza elle eklemeniz gerekir. [docs/web-server-hardening.md](../docs/web-server-hardening.md) şunları kapsar:

- Apache kurallarının aynısını sağlayan tam nginx `location` blokları
- FlyEnv/PhpWebStudy'de bilinen bir PHP-location yanlış yapılandırması, path-info tabanlı çalıştırma hilelerine (`/media/photo.jpg/x.php`) izin verebilir — nasıl kapatılacağı dahil
- HSTS, `X-Frame-Options`, `Referrer-Policy` ve diğer production yanıt başlıkları
- Deploy sonrası yüklenen payload'ların gerçekten çalıştırılamadığını doğrulamak için bir `curl` komutu

**Apache dışında herhangi bir şey üzerine deploy ediyorsanız bu dokümanı okuyun.**

### Güvenlik açığı bildirimi

ci4ms'te bir güvenlik açığı bulursanız, herkese açık bir issue açmayın. [SECURITY.md](../SECURITY.md) içinde açıklandığı şekilde e-posta ile bildirin. Geçmiş bildirenler README'nin [Security Hall of Fame](../README.md#-security-hall-of-fame) bölümünde onurlandırılır.
