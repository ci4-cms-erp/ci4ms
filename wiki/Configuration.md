One-sentence purpose: what to check and adjust after installation — environment mode, caches, themes, and modules.

## English

### Environment

`.env`'s `CI_ENVIRONMENT` setting controls whether the site runs in `development` or `production` mode. The web installer writes `CI_ENVIRONMENT = production` automatically during setup, but it's worth confirming the value directly in `.env` after installation, especially if you set it up via the CLI (`php spark ci4ms:setup`) rather than the web installer. Running in `development` enables the debug toolbar and detailed error/stack-trace output — see [Security Hardening](Security-Hardening.md) for why this matters in production.

### Caches

ci4ms caches several hot values so it doesn't re-query the database or re-render the same data on every request:

| Cache key | Contents | TTL |
|---|---|---|
| `settings` | Decoded application settings | 24h |
| `menus_{locale}` | Per-locale frontend menu tree | 24h |
| `notif_unread_{userId}` | Per-user unread notification count | 60s |
| `sidebar_menu` | Backend sidebar items | 24h |
| `shield_auth_dynamic_config` | Shield's dynamic RBAC config (group/permission matrix) | 24h |
| `backend_page_info_*` | Per-route permission row the authorization filter looks up | 1h |

Clear everything at once with:

```bash
php spark cache:clear
```

You'll need this after changing settings, permissions, or menus outside of the backend UI (the backend itself invalidates the relevant keys on save). The last two keys are the pair the authorization filter reads on every backend request, and the backend always clears them **together** — if you ever change permission rows directly in the database, clear both (or run `php spark cache:clear`), because dropping only one leaves the other in force for up to an hour. The backend Settings page also has a "Cache Management" panel that lets administrators selectively clear specific caches by logical name; `shield_auth_dynamic_config` is deliberately excluded from that selective-clear list and can only be reset through a full `php spark cache:clear`.

### Installing themes

Themes live under `public/templates/<theme>/`. To install one from the backend:

1. Go to the Theme module in the backend
2. Upload a theme ZIP

Every theme archive **must** contain both `info.xml` (metadata, including the theme's slug) and `screenshot.png` at the expected location — an upload missing either file is rejected. If a theme is missing these files after installation, the backend surfaces a warning. Themes may ship their own database migrations under `Database/Migrations/`, which run automatically on activation.

### Installing modules

New feature modules are dropped under `modules/<Name>/` — no code change is required elsewhere, since `app/Config/Autoload.php` scans the `modules/` directory at boot and registers each folder's namespace automatically. After adding a module with new backend routes, run a **Module Scan** from the Methods section of the backend (or insert the permission record manually) so the permission filter recognizes the new routes — otherwise every request to them returns a 403, even for superadmin. See [Troubleshooting](Troubleshooting.md).

## Türkçe

### Ortam

`.env` dosyasındaki `CI_ENVIRONMENT` ayarı sitenin `development` mi yoksa `production` modunda mı çalıştığını belirler. Web installer, kurulum sırasında `.env`'e otomatik olarak `CI_ENVIRONMENT = production` yazar; ancak özellikle CLI ile (`php spark ci4ms:setup`) kurulum yaptıysanız, kurulumdan sonra bu değeri `.env` içinde doğrudan teyit etmekte fayda var. `development` modunda çalışmak debug toolbar'ı ve ayrıntılı hata/stack-trace çıktısını etkinleştirir — bunun production'da neden önemli olduğu için [Güvenlik Sertleştirme](Security-Hardening.md) sayfasına bakın.

### Cache'ler

ci4ms, her istekte aynı veriyi tekrar sorgulamamak/render etmemek için birkaç sık kullanılan değeri cache'ler:

| Cache anahtarı | İçerik | TTL |
|---|---|---|
| `settings` | Decode edilmiş uygulama ayarları | 24s |
| `menus_{locale}` | Locale bazlı ön yüz menü ağacı | 24s |
| `notif_unread_{userId}` | Kullanıcı bazlı okunmamış bildirim sayısı | 60sn |
| `sidebar_menu` | Backend kenar çubuğu öğeleri | 24s |
| `shield_auth_dynamic_config` | Shield'in dinamik RBAC yapılandırması (grup/izin matrisi) | 24s |
| `backend_page_info_*` | Yetki filtresinin baktığı route bazlı izin satırı | 1s |

Hepsini tek seferde temizlemek için:

```bash
php spark cache:clear
```

Backend UI dışından ayar, izin veya menü değişikliği yaptıysanız buna ihtiyacınız olur (backend'in kendisi kayıt sırasında ilgili anahtarları zaten geçersiz kılar). Son iki anahtar, yetki filtresinin her backend isteğinde okuduğu çifttir ve backend bunları her zaman **birlikte** temizler — izin satırlarını doğrudan veritabanında değiştirirseniz ikisini birden temizleyin (ya da `php spark cache:clear` çalıştırın); yalnızca birini düşürmek diğerini bir saate kadar yürürlükte bırakır. Backend Settings sayfasında ayrıca yöneticilerin belirli cache'leri mantıksal isimle seçerek temizleyebildiği bir "Cache Management" paneli vardır; `shield_auth_dynamic_config` bu seçmeli temizleme listesinden bilinçli olarak hariç tutulmuştur ve yalnızca tam bir `php spark cache:clear` ile sıfırlanabilir.

### Tema yükleme

Temalar `public/templates/<theme>/` altında yaşar. Backend'den bir tema yüklemek için:

1. Backend'de Theme (Tema) modülüne gidin
2. Bir tema ZIP'i yükleyin

Her tema arşivi beklenen konumda hem `info.xml` (tema slug'ı dahil metadata) hem de `screenshot.png` içermek **zorundadır** — bunlardan biri eksikse yükleme reddedilir. Kurulumdan sonra bu dosyalar eksikse backend bir uyarı gösterir. Temalar `Database/Migrations/` altında kendi veritabanı migration'larını taşıyabilir; bunlar aktivasyonda otomatik çalışır.

### Modül yükleme

Yeni özellik modülleri `modules/<Name>/` altına bırakılır — başka hiçbir yerde kod değişikliği gerekmez, çünkü `app/Config/Autoload.php` başlangıçta `modules/` dizinini tarar ve her klasörün namespace'ini otomatik kaydeder. Yeni backend route'ları olan bir modül eklendikten sonra, izin filtresinin yeni route'ları tanıması için backend'deki Methods bölümünden bir **Module Scan** (Modül Tarama) çalıştırın (veya izin kaydını elle ekleyin) — aksi halde bu route'lara yapılan her istek, superadmin dahil, 403 döner. Bkz. [Sorun Giderme](Troubleshooting.md).
