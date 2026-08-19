One-sentence purpose: how to move an existing ci4ms installation to a newer version, manually or via the in-app updater, without losing data.

## English

### Before you upgrade

Back up `public/uploads/`, your database, and `.env` before any major upgrade. This is a standing recommendation in the project's own deployment checklist, not upgrade-specific advice — treat every upgrade as a "major" one unless you know otherwise.

### Manual upgrade

1. Pull or deploy the new code (`git pull`, or replace the release archive), then `composer install` if dependencies changed.
2. Run any pending database migrations across every module:

   ```bash
   php spark migrate --all
   ```

   Two alternatives exist and both leave a record `migrate --all` does not. `php spark ci4ms:migrate` runs the same thing and additionally writes a row to `migration_runs`, so the run appears in the backend's history. The **Migration Manager** screen (superadmin only) does the same from the browser and lets you apply a single namespace instead of all of them; concurrent runs are serialised, so a CLI run and a panel run cannot overlap. Neither offers rollback — several ci4ms migrations have an intentionally empty `down()`, so restore your database backup instead if a migration goes wrong.

3. Clear all caches so stale settings/menus/permissions don't linger:

   ```bash
   php spark cache:clear
   ```

4. If the release added new backend routes or permissions (check [CHANGELOG.md](../CHANGELOG.md) for the version you're moving to), run a **Module Scan** from the Methods section of the backend so the new routes are recognized by the permission filter — otherwise they 403 for everyone, including superadmin, until scanned.

### The in-app one-click updater

ci4ms also ships a backend "one-click update" panel (`Modules\Settings`) that checks GitHub releases and can apply an update directly. It is **fail-closed by default**: every file it writes must be listed in a release manifest carrying a detached Ed25519 signature from a key your installation already trusts, and the repository ships with an **empty keyring** — so auto-update does nothing until you deliberately add a publisher's verified public key. Until then, using the manual `migrate --all` / `cache:clear` path above is the only way to upgrade.

If you do configure trust, the updater cannot be used to downgrade an installation, and every file update is checked against a SHA-256 entry in the signed manifest before anything is written. See [README.md — Release Signing & Trusted Keys](../README.md#release-signing--trusted-keys) and [docs/architecture.md — Auto-Update & Release Signing](../docs/architecture.md#auto-update--release-signing) for the full trust model, including how to check which keys your own installation currently trusts (backend Settings page).

### After upgrading

Check the specific version's [CHANGELOG.md](../CHANGELOG.md) entry for any manual follow-up steps — some past releases required an additional Module Scan or cache clear beyond the standard steps above (for example, when a release added new opt-in tables or permission-gated screens).

## Türkçe

### Yükseltmeden önce

Herhangi bir büyük yükseltmeden önce `public/uploads/`, veritabanınızı ve `.env` dosyasını yedekleyin. Bu, projenin kendi deployment kontrol listesindeki genel bir tavsiyedir, yükseltmeye özel değildir — aksini bilmediğiniz sürece her yükseltmeyi "büyük" kabul edin.

### Manuel yükseltme

1. Yeni kodu çekin veya deploy edin (`git pull`, ya da release arşivini değiştirin), bağımlılıklar değiştiyse `composer install` çalıştırın.
2. Her modülde bekleyen veritabanı migration'larını çalıştırın:

   ```bash
   php spark migrate --all
   ```

   İki alternatif var ve ikisi de `migrate --all`'ın bırakmadığı bir kayıt bırakır. `php spark ci4ms:migrate` aynı işi yapar, ek olarak `migration_runs` tablosuna bir satır yazar; böylece koşu backend'deki geçmişte görünür. **Migration Manager** ekranı (yalnızca superadmin) aynısını tarayıcıdan yapar ve hepsi yerine tek bir namespace'i uygulamanıza izin verir; eşzamanlı koşular sıraya alınır, yani bir CLI koşusu ile panel koşusu üst üste binemez. İkisi de geri alma (rollback) sunmaz — bazı ci4ms migration'larının `down()` metodu bilinçli olarak boştur, dolayısıyla bir migration ters giderse veritabanı yedeğinizi geri yükleyin.

3. Eski ayar/menü/izin verilerinin kalmaması için tüm cache'leri temizleyin:

   ```bash
   php spark cache:clear
   ```

4. Sürüm yeni backend route'ları veya izinleri eklediyse (geçtiğiniz sürüm için [CHANGELOG.md](../CHANGELOG.md) dosyasını kontrol edin), izin filtresinin yeni route'ları tanıması için backend'deki Methods bölümünden bir **Module Scan** çalıştırın — aksi halde taranana kadar bu route'lar superadmin dahil herkes için 403 döner.

### Uygulama içi tek tıkla güncelleme

ci4ms ayrıca GitHub release'lerini kontrol edip doğrudan güncelleme uygulayabilen bir backend "tek tıkla güncelleme" paneli (`Modules\Settings`) ile gelir. Bu panel **varsayılan olarak fail-closed'dır**: yazacağı her dosya, kurulumunuzun zaten güvendiği bir anahtardan gelen ayrık bir Ed25519 imzası taşıyan bir release manifest'inde listelenmiş olmalıdır ve repo **boş bir keyring** ile gelir — yani siz bilinçli olarak yayıncının doğrulanmış bir açık anahtarını eklemedikçe otomatik güncelleme hiçbir şey yapmaz. O zamana kadar, yukarıdaki manuel `migrate --all` / `cache:clear` yolu tek yükseltme yöntemidir.

Güveni yapılandırırsanız, updater bir kurulumu düşürmek (downgrade) için kullanılamaz ve her dosya güncellemesi, hiçbir şey yazılmadan önce imzalı manifest içindeki bir SHA-256 girdisine karşı kontrol edilir. Tam güven modeli, kurulumunuzun şu anda hangi anahtarlara güvendiğini nasıl kontrol edeceğiniz dahil (backend Settings sayfası), için bkz. [README.md — Release Signing & Trusted Keys](../README.md#release-signing--trusted-keys) ve [docs/architecture.md — Auto-Update & Release Signing](../docs/architecture.md#auto-update--release-signing).

### Yükseltmeden sonra

Manuel takip adımları için ilgili sürümün [CHANGELOG.md](../CHANGELOG.md) girdisini kontrol edin — bazı geçmiş sürümler, yukarıdaki standart adımların ötesinde ek bir Module Scan veya cache temizleme gerektirdi (örneğin bir sürüm yeni opt-in tablolar veya izin korumalı ekranlar eklediğinde).
