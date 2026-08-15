One-sentence purpose: get ci4ms from a fresh clone/composer install to a running site, via either the web installer or the one-command CLI setup.

## English

### Requirements

- PHP **8.2** or newer, with the `intl`, `json`, `mbstring`, `gd`, `curl`, and `openssl` extensions enabled
- Composer **2.5+**
- MySQL / MariaDB (or another CodeIgniter 4-supported database driver)
- Writable directories: `writable/`, `public/uploads/`, and optionally `public/templates/`
- *Optional:* Redis + `ext-redis` (phpredis), only if you enable the realtime notifications feature (off by default)

### 1. Get the code

**Fresh project (recommended):**

```bash
composer create-project ci4-cms-erp/ci4ms myproject
cd myproject
```

**Clone an existing repository:**

```bash
git clone <repo-url> ci4ms
cd ci4ms
composer install
```

### 2. Create your `.env` file

```bash
cp env .env
```

Update at least these settings in `.env`:

- `app.baseURL`
- `database.default.*` (hostname, database, username, password, driver, prefix, port)
- Optionally: `cookie.*`, `honeypot.*`, `security.*`

### 3. Copy the routes template

```bash
cp app/Config/DefaultRoutes.php app/Config/Routes.php
```

This step is required before either installation path below — `app/Config/Routes.php` does not ship pre-populated in the repository.

### 4. Install: pick one path

#### Option A — Web installer

Open `/install` in your browser and follow the wizard. It will:

1. Create and configure `.env` (including generating an encryption key)
2. Run all database migrations
3. Insert default data (settings, languages, permission groups)
4. Create the initial administrator account
5. Regenerate `app/Config/Routes.php`
6. Create required runtime directories (`writable/backups`, `public/media/.tmb`, `public/media/.trash`)

The installer writes `CI_ENVIRONMENT = production` into `.env` as part of this process, and shows a **one-time** DevGate credential disclosure page before redirecting you to the homepage — see [Security Hardening](Security-Hardening.md) for what both of these mean and why you should save that password immediately.

#### Option B — One-command CLI setup

```bash
php spark ci4ms:setup
```

This single command runs all migrations, seeds default data (modules, permissions, sample content), and creates the initial administrator account — no separate `migrate` or `seed` commands are needed. This is the path used in Docker/CI environments where a browser-based installer isn't practical.

### 5. Serve the application

```bash
php spark serve
```

The frontend is served at the site root; the backend admin panel is available at `/backend`.

### Docker

```bash
cp env .env
cp app/Config/DefaultRoutes.php app/Config/Routes.php
docker compose up -d --build
docker exec ci4ms_app composer install
docker exec ci4ms_app php spark ci4ms:setup
```

Full Docker instructions, including service ports and the required `hostname = db` database setting, are in [DOCKER_SETUP.md](../DOCKER_SETUP.md).

### Notes

- If you skip step 3 (copying `Routes.php`), the site will not route correctly.
- Known past installer regressions worth being aware of (already fixed, see [CHANGELOG.md](../CHANGELOG.md)): a web-installer 404 on the configuration step, and a CLI setup failure on the `users.profileIMG` migration under MySQL/MariaDB strict mode. If you hit an installer failure, check you are on a current release before filing a report.

## Türkçe

### Gereksinimler

- PHP **8.2** veya üzeri, `intl`, `json`, `mbstring`, `gd`, `curl`, `openssl` uzantıları etkin
- Composer **2.5+**
- MySQL / MariaDB (veya CodeIgniter 4'ün desteklediği başka bir veritabanı sürücüsü)
- Yazılabilir dizinler: `writable/`, `public/uploads/`, isteğe bağlı olarak `public/templates/`
- *İsteğe bağlı:* Redis + `ext-redis` (phpredis) — yalnızca gerçek zamanlı bildirim özelliğini açarsanız gerekir (varsayılan kapalı)

### 1. Kodu edinin

**Yeni proje (önerilen):**

```bash
composer create-project ci4-cms-erp/ci4ms myproject
cd myproject
```

**Mevcut repoyu klonlayın:**

```bash
git clone <repo-url> ci4ms
cd ci4ms
composer install
```

### 2. `.env` dosyanızı oluşturun

```bash
cp env .env
```

`.env` içinde en az şu ayarları güncelleyin:

- `app.baseURL`
- `database.default.*` (host, veritabanı adı, kullanıcı adı, şifre, driver, prefix, port)
- İsteğe bağlı: `cookie.*`, `honeypot.*`, `security.*`

### 3. Routes şablonunu kopyalayın

```bash
cp app/Config/DefaultRoutes.php app/Config/Routes.php
```

Bu adım aşağıdaki iki kurulum yolundan önce de gereklidir — `app/Config/Routes.php` repoda hazır gelmez.

### 4. Kurulum: birini seçin

#### Seçenek A — Web installer

Tarayıcınızda `/install` adresini açın ve sihirbazı takip edin. Sihirbaz şunları yapar:

1. `.env` dosyasını oluşturur ve yapılandırır (şifreleme anahtarı üretimi dahil)
2. Tüm veritabanı migration'larını çalıştırır
3. Varsayılan verileri ekler (ayarlar, diller, izin grupları)
4. İlk yönetici hesabını oluşturur
5. `app/Config/Routes.php` dosyasını yeniden üretir
6. Gerekli çalışma zamanı dizinlerini oluşturur (`writable/backups`, `public/media/.tmb`, `public/media/.trash`)

Installer bu süreçte `.env` dosyasına `CI_ENVIRONMENT = production` yazar ve anasayfaya yönlendirmeden önce **tek seferlik** bir DevGate kimlik bilgisi açıklama sayfası gösterir — bunların ne anlama geldiği ve o şifreyi neden hemen kaydetmeniz gerektiği için [Güvenlik Sertleştirme](Security-Hardening.md) sayfasına bakın.

#### Seçenek B — Tek komutla CLI kurulumu

```bash
php spark ci4ms:setup
```

Bu tek komut tüm migration'ları çalıştırır, varsayılan verileri (modüller, izinler, örnek içerik) ekler ve ilk yönetici hesabını oluşturur — ayrıca `migrate` veya `seed` komutlarına gerek yoktur. Bu yol, tarayıcı tabanlı bir installer'ın pratik olmadığı Docker/CI ortamlarında kullanılır.

### 5. Uygulamayı çalıştırın

```bash
php spark serve
```

Ön yüz site kökünde, backend yönetim paneli ise `/backend` adresinde sunulur.

### Docker

```bash
cp env .env
cp app/Config/DefaultRoutes.php app/Config/Routes.php
docker compose up -d --build
docker exec ci4ms_app composer install
docker exec ci4ms_app php spark ci4ms:setup
```

Servis portları ve gerekli `hostname = db` veritabanı ayarı dahil tam Docker talimatları [DOCKER_SETUP.md](../DOCKER_SETUP.md) içindedir.

### Notlar

- 3. adımı (`Routes.php` kopyalama) atlarsanız site doğru şekilde route edilmez.
- Bilinen geçmiş installer regresyonları (zaten düzeltildi, bkz. [CHANGELOG.md](../CHANGELOG.md)): web installer'ın yapılandırma adımında 404 vermesi ve CLI kurulumunun `users.profileIMG` migration'ında MySQL/MariaDB strict mode nedeniyle başarısız olması. Bir installer hatasıyla karşılaşırsanız, rapor açmadan önce güncel bir sürümde olduğunuzu kontrol edin.
