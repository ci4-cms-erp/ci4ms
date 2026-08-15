One-sentence purpose: common problems after install/upgrade and their verified, source-backed fixes.

## English

### Blank page or endless redirect to `/install`

`app/Filters/Ci4ms.php` redirects every request to `/install` whenever `.env` does not exist at the project root. If you've already run the installer but still land on `/install`, confirm `.env` actually exists in the project root (not just in a subdirectory) and that the web server user can read it.

If the site loads but looks broken/unrouted, confirm you copied the routes template — this is a required manual step on both installation paths:

```bash
cp app/Config/DefaultRoutes.php app/Config/Routes.php
```

Also confirm `writable/`, `public/uploads/`, and (if you use themes) `public/templates/` are writable by the web server user.

### 403 / Permission errors

ci4ms's backend permission filter (`Modules\Auth\Filters\Ci4MsAuthFilter`) looks up every controller/method pair against the `auth_permissions_pages` table. **If a route has no matching record there, the filter returns 403 for everyone — including superadmin.** This is the single most common cause of "I just added/updated a module and now it 403s":

1. Go to the Methods section in the backend and run a **Module Scan**, which inspects the router and inserts missing permission records.
2. Grant the relevant permission to the appropriate group(s) if the scan alone doesn't cover it.
3. Clear the permission cache: `php spark cache:clear` (or the per-user `{userId}_permissions` key will keep serving stale data).

If you are logged in but redirected straight to the login page, your session may have been flagged banned/inactive, or you're simply not authenticated — `Ci4MsAuthFilter` redirects to `login` whenever `auth()->loggedIn()` is false.

### Migration errors

- **`php spark migrate --all` aborts partway through.** Check `writable/logs/` for the specific error; the log will name the offending migration and table.
- **A specific past example, fixed in the codebase but useful for diagnosing similar issues:** a migration lacking a `fieldExists()` guard tried to add a column that already existed, aborting the whole `--all` run. If you hit this on a **custom or third-party** module's migration, running that module's migrations scoped to its own namespace (`php spark migrate -n "Modules\<Name>"`) rather than `--all` can get you past an unrelated broken migration while you fix it.
- **Fresh-install migration failure on a `TEXT` column with a default value, or a `NOT NULL` timestamp column with no value supplied at seed time.** Both are strict-SQL-mode issues — MySQL 5.7+ and many MariaDB builds reject these by default. If you're running your own custom migrations/seeders (not the ones shipped with ci4ms, which have been fixed for this), either supply explicit values for `NOT NULL` columns or disable strict mode as a last resort.
- Always re-run `php spark cache:clear` after a successful migration that touches settings, permissions, or menu data.

### Theme upload rejected

Every theme ZIP must contain **both** `info.xml` (with a valid `<slug>`, matching `[a-z0-9_-]+` and at most 64 characters) and a genuine `screenshot.png` (a real PNG, not just a file with that extension — this is checked, not just assumed from the filename) at the expected location inside the archive. An upload missing either file, with an invalid slug, or with a `screenshot.png` that isn't actually a valid PNG image, is rejected outright.

### Module install / migration issues

Dropping a module folder under `modules/<Name>/` is enough for it to be auto-discovered — no `Autoload.php` edit needed. If the module's migrations don't seem to run:

- Confirm the module has a `Database/Migrations/` directory with correctly named/dated migration files.
- Run a Module Scan (Methods section) so any new routes get permission records — a module with working migrations but no permission records will 403 on every screen.
- After adding or updating a module, always run `php spark cache:clear`.

### Where to look when none of the above fits

- `writable/logs/` — the daily application log, or use the in-backend log viewer at `/backend/logs`.
- Confirm you're on a recent release; several install-blocking regressions (a web-installer 404, a strict-mode migration failure, a geo-lookup crash on login when an external DNS resolver blocks `ip-api.com`) have already been fixed — see the Bug Reporters table in [README.md](../README.md#-bug-reporters) and [CHANGELOG.md](../CHANGELOG.md).
- If you believe you've found a new, non-security bug, [open an issue](https://github.com/ci4-cms-erp/ci4ms/issues) with reproduction steps. For security vulnerabilities, do not open a public issue — follow [SECURITY.md](../SECURITY.md) instead.

## Türkçe

### Boş sayfa veya sürekli `/install`'a yönlenme

`app/Filters/Ci4ms.php`, proje kökünde `.env` dosyası bulunmadığında her isteği `/install`'a yönlendirir. Installer'ı zaten çalıştırdıysanız ama hâlâ `/install`'a düşüyorsanız, `.env` dosyasının gerçekten proje kökünde (bir alt dizinde değil) bulunduğunu ve web sunucusu kullanıcısının onu okuyabildiğini teyit edin.

Site açılıyor ama bozuk/route edilmemiş görünüyorsa, routes şablonunu kopyaladığınızı teyit edin — bu her iki kurulum yolunda da gereken manuel bir adımdır:

```bash
cp app/Config/DefaultRoutes.php app/Config/Routes.php
```

Ayrıca `writable/`, `public/uploads/` ve (tema kullanıyorsanız) `public/templates/` dizinlerinin web sunucusu kullanıcısı tarafından yazılabilir olduğunu teyit edin.

### 403 / İzin hataları

ci4ms'in backend izin filtresi (`Modules\Auth\Filters\Ci4MsAuthFilter`), her controller/method çiftini `auth_permissions_pages` tablosunda arar. **Bir route'un orada eşleşen bir kaydı yoksa, filtre superadmin dahil herkes için 403 döner.** Bu, "bir modül ekledim/güncelledim ve şimdi 403 veriyor" durumunun en yaygın nedenidir:

1. Backend'de Methods bölümüne gidin ve router'ı tarayıp eksik izin kayıtlarını ekleyen bir **Module Scan** çalıştırın.
2. Tarama tek başına yeterli değilse ilgili izni uygun gruba/gruplara verin.
3. İzin cache'ini temizleyin: `php spark cache:clear` (aksi halde kullanıcı bazlı `{userId}_permissions` anahtarı eski veriyi sunmaya devam eder).

Giriş yaptığınız halde doğrudan login sayfasına yönlendiriliyorsanız, oturumunuz banlı/pasif olarak işaretlenmiş olabilir veya basitçe kimliğiniz doğrulanmamıştır — `Ci4MsAuthFilter`, `auth()->loggedIn()` false olduğunda `login`'e yönlendirir.

### Migration hataları

- **`php spark migrate --all` yarıda duruyor.** Belirli hata için `writable/logs/` dizinine bakın; log, hatalı migration'ı ve tabloyu belirtir.
- **Kod tabanında düzeltilmiş ama benzer sorunları teşhis etmek için faydalı, geçmişten spesifik bir örnek:** `fieldExists()` koruması olmayan bir migration, zaten var olan bir sütunu eklemeye çalışıp tüm `--all` çalışmasını durdurdu. Bunu **özel veya üçüncü taraf** bir modülün migration'ında yaşarsanız, `--all` yerine o modülün migration'larını kendi namespace'ine sınırlı çalıştırmak (`php spark migrate -n "Modules\<Name>"`) siz düzeltirken ilgisiz bozuk bir migration'ı aşmanızı sağlayabilir.
- **Varsayılan değeri olan bir `TEXT` sütununda veya seed zamanında değer verilmeyen bir `NOT NULL` timestamp sütununda taze kurulum migration hatası.** İkisi de strict-SQL-mode sorunudur — MySQL 5.7+ ve birçok MariaDB derlemesi bunları varsayılan olarak reddeder. Kendi özel migration/seeder'larınızı çalıştırıyorsanız (ci4ms ile gelen ve bu sorun için zaten düzeltilmiş olanları değil), `NOT NULL` sütunlara açıkça değer verin ya da son çare olarak strict mode'u kapatın.
- Ayarlar, izinler veya menü verisine dokunan başarılı bir migration'dan sonra her zaman `php spark cache:clear` çalıştırın.

### Tema yükleme reddediliyor

Her tema ZIP'i, arşiv içinde beklenen konumda hem geçerli bir `<slug>` içeren `info.xml` (slug `[a-z0-9_-]+` desenine uymalı ve en fazla 64 karakter olmalı) hem de gerçek bir `screenshot.png` (sadece o uzantıya sahip bir dosya değil, gerçek bir PNG — bu kontrol edilir, dosya adından varsayılmaz) içermelidir. Bu dosyalardan biri eksikse, slug geçersizse veya `screenshot.png` gerçekte geçerli bir PNG görseli değilse, yükleme doğrudan reddedilir.

### Modül yükleme / migration sorunları

Bir modül klasörünü `modules/<Name>/` altına bırakmak otomatik keşif için yeterlidir — `Autoload.php` düzenlemesi gerekmez. Modülün migration'ları çalışmıyor gibi görünüyorsa:

- Modülün doğru isimlendirilmiş/tarihlendirilmiş migration dosyalarına sahip bir `Database/Migrations/` dizini olduğunu teyit edin.
- Yeni route'ların izin kaydı alması için bir Module Scan (Methods bölümü) çalıştırın — çalışan migration'ları olan ama izin kaydı olmayan bir modül her ekranda 403 verir.
- Bir modül ekledikten veya güncelledikten sonra her zaman `php spark cache:clear` çalıştırın.

### Yukarıdakilerden hiçbiri uymuyorsa nereye bakılır

- `writable/logs/` — günlük uygulama logu, veya backend içindeki log görüntüleyiciyi kullanın: `/backend/logs`.
- Güncel bir sürümde olduğunuzu teyit edin; birkaç kurulum-engelleyici regresyon (bir web installer 404'ü, strict-mode migration hatası, harici bir DNS çözücü `ip-api.com`'u engellediğinde login'de oluşan bir geo-lookup çökmesi) zaten düzeltildi — bkz. [README.md](../README.md#-bug-reporters) içindeki Bug Reporters tablosu ve [CHANGELOG.md](../CHANGELOG.md).
- Yeni, güvenlikle ilgisi olmayan bir hata bulduğunuzu düşünüyorsanız, tekrar üretme adımlarıyla [bir issue açın](https://github.com/ci4-cms-erp/ci4ms/issues). Güvenlik açıkları için herkese açık bir issue açmayın — bunun yerine [SECURITY.md](../SECURITY.md) dosyasını takip edin.
