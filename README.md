# CI4MS

CI4MS is a CodeIgniter 4-based CMS skeleton that delivers a production-ready, modular architecture with RBAC authorization and theme support. It combines CMS workflows, developer-focused CLI commands, an extensible module system, and customizable front-end themes in a single package.

[![Patreon](https://img.shields.io/badge/Patreon-Support%20Us-F96854?style=for-the-badge&logo=patreon&logoColor=white)](https://patreon.com/cw/bertugfahriozer)


## Key Features

- **Authentication & RBAC:** `Modules\Auth` handles user login, lockouts, and password resets via CodeIgniter Shield. Permissions map to `auth_permissions_pages` records.
- **Modular backend:** Each feature ships as an independent module (Blog, Pages, Menu, Media, Users, Settings, Theme, etc.) under `modules/*`.
- **Flexible content management:** Page and blog entries include SEO metadata, categories, tags, and full comment workflows.
- **Media & files:** Includes elFinder-powered media management, a built-in file editor, and an in-panel log viewer.
- **Theme system:** The `public/templates/*` structure and the `Modules\Theme` module enable installing or upgrading themes from ZIP packages.
- **Setup & automation:** Offers a web-based installer (`/install`) plus a single CLI command (`php spark ci4ms:setup`) for automated installation, default data seeding, and route generation. Module scaffolding is available via `php spark make:module`.
- **Docker support:** Ships with a production-ready `Dockerfile`, `docker-compose.yml`, and a GitHub Actions CI workflow out of the box.
- **SEO helpers:** `ci4seopro` builds meta tags and JSON-LD, while `CommonLibrary` centralizes email, breadcrumbs, and inline shortcode utilities.

## Requirements

- PHP **8.2** or newer (`intl`, `json`, `mbstring`, `gd`, `curl`, `openssl` extensions required)
- Composer 2.5+
- MySQL / MariaDB (or any CodeIgniter 4-supported driver)
- Writable directories: `writable/`, `public/uploads/`, optionally `public/templates/`

See `composer.json` for the full dependency list (e.g. `bertugfahriozer/ci4commonmodel`, `bertugfahriozer/sql2migration`, `ci4-cms-erp/ext_module_generator`, `claviska/simpleimage`, `gregwar/captcha`, `studio-42/elfinder`).

# 🪴 Project Activity

![Alt](https://repobeats.axiom.co/api/embed/9f2631ce1dcfae3db84f5113fea08ac0c7ae8d29.svg "Repobeats analytics image")

## Installation

### Fresh Project (recommended)

```bash
composer create-project ci4-cms-erp/ci4ms myproject
cd myproject
```

### Clone Existing Repository

```bash
git clone <repo-url> ci4ms
cd ci4ms
composer install
```

### Docker (recommended for development & CI)

```bash
cp env .env           # configure database, baseURL, etc.
cp app/Config/DefaultRoutes.php app/Config/Routes.php
docker compose up -d --build
docker exec ci4ms_app composer install
docker exec ci4ms_app php spark ci4ms:setup
```

Refer to `DOCKER_SETUP.md` for full Docker configuration details.

### Environment & Configuration

1. Create your `.env` from the template:

```bash
cp env .env
```

2. Update these core settings in `.env`:
   - `app.baseURL`
   - `database.default.*`
   - Optional: `cookie.*`, `honeypot.*`, `security.*`

3. Prepare the routes file:

```bash
cp app/Config/DefaultRoutes.php app/Config/Routes.php
```

4. If you prefer the web installer, open `/install` in the browser and follow the wizard. Use the CLI step below to skip the wizard.

### One-Command Setup (CLI)

```bash
php spark ci4ms:setup
```

This single command runs all migrations, seeds default data (modules, permissions, sample content), and creates the initial administrator account. No separate migrate or seed commands are needed.

### Run the Dev Server

```bash
php spark serve
```

Access the backend via: `https://<domain>/backend`

## Directory Layout

```
app/                 Application code (controllers, config, libraries, filters)
modules/             Feature modules (Auth, Backend, Blog, etc.)
public/
  index.php          Front controller
  be-assets/         Admin UI build artifacts (CSS/JS)
  templates/         Front-end themes
  media/             Media storage (must be writable)
writable/            Cache, logs, temporary files (must be writable)
vendor/              Composer packages
.docker/             Dockerfile, Apache, and PHP configuration
docs/                Developer documentation
```

Key files:

- `app/Commands/` — CLI tooling (`make:a*`, `create:route`, `ci4ms:setup`).
- `app/Filters/Ci4ms.php` — Install guard, maintenance mode redirect, menu cache.
- `app/Config/DefaultRoutes.php` — Routes template; copy to `Routes.php` on setup.
- `modules/*` — Each module includes its own `Config/Routes.php`, `Controllers`, `Models`, `Views`, `Language`, `Libraries`, `Filters`.
- `public/templates/` — Theme assets; each theme requires `info.xml` and `screenshot.png`.
- `writable/` — Cache, logs, temporary files.

## Modules

| Module           | Purpose                    | Highlights                                            |
| ---------------- | -------------------------- | ----------------------------------------------------- |
| Auth             | Authentication lifecycle   | Shield-based, CAPTCHA, email activation, reset tokens |
| Backend          | Admin shell                | Dashboard stats, shared base controller               |
| Blog             | Blog CRUD                  | Categories, tags, comments, bad-word filters          |
| Pages            | Static page management     | SEO fields, inline shortcode parsing                  |
| Menu             | Menu builder               | Drag-and-drop ordering, slug helpers                  |
| Media            | Media manager              | elFinder integration, optional WebP conversion        |
| Fileeditor       | Project file editor        | Safe read/write/rename/move/delete                    |
| Settings         | System configuration       | Company/social/mail settings, encrypted SMTP password |
| Users            | User & role management     | Shield groups, reset tracking                         |
| Methods          | Route → permission mapping | Module toggling, router scan                          |
| Logs             | Log viewer                 | Browses CodeIgniter log files inside the backend      |
| ModulesInstaller | Module ZIP installer       | Upload + cache invalidation                           |
| Theme            | Theme manager              | ZIP upload, DB migration support, duplicate checks    |
| Install          | Web installer              | Creates `.env`, triggers migrations                   |
| Backup           | Database backup manager    | Create, download, and restore backups                 |
| DashboardWidgets | Dashboard statistics       | Modular widget system for admin overview              |
| LanguageManager  | Language file manager      | Edit and manage translation files from the backend    |

See `docs/architecture.md` for deeper architectural notes.

## CLI Commands

| Command | Description |
|---|---|
| `php spark ci4ms:setup` | Full automated installation: migrations, seeding, default data |
| `php spark make:module Blog` | Scaffold a new module (Config, Controllers, Views, language files) |
| `php spark make:abview dashboard` | Generate a backend view from the AdminLTE template |
| `php spark create:route` | Rebuild `app/Config/Routes.php` from the template |
| `php spark migrate --all` | Run all pending migrations across modules |
| `php spark cache:clear` | Clear all application caches |

Standard CodeIgniter commands (`php spark db:seed`, `php spark key:generate`, etc.) are also available.

## Developer Notes

- **Cache keys**: `settings` (24h), `menus` (24h), `{userId}_permissions`. Clear with `php spark cache:clear` or `cache()->delete()`.
- **Base controller**: Extend `Modules\Backend\Controllers\BaseController` for new backend controllers; it prepares session user, navigation, mail settings, and shared data.
- **Permissions**: Register new secured routes in `Modules\Methods` (or via the database) so the permission filter recognizes them.
- **Slug generation**: `seflink()` handles transliteration (including Turkish characters).
- **Form security**: Global CSRF is enabled; backend AJAX endpoints opt out via `BackendConfig::$csrfExcept`.
- **Comment moderation**: `CommonLibrary::commentBadwordFiltering` handles bad word filtering and moderation rules.
- **Theme uploads**: Each theme must include `info.xml` and `screenshot.png`; missing files trigger a backend warning.

## Testing & Maintenance

- `composer test` — runs PHPUnit.
- The GitHub Actions workflow (`.github/workflows/docker-test.yaml`) automatically builds the Docker image and runs migrations on every push to `master`.
- **Maintenance mode**: When `settings.maintenanceMode.scalar == 1`, the `Ci4ms` filter redirects visitors to `maintenance-mode`.
- **Security**: `Fileeditor` and `Media` enforce `realpath` guards. Limit access in production environments.

## Additional Docs

- `docs/architecture.md` — Architecture, flow, permissions, and extension guidance.
- `docs/developer-handbook.md` — Environment setup, coding standards, deployment checklist.
- `docs/theme_development.md` — Theme folder structure, routing, and `base.php` variables.
- `DOCKER_SETUP.md` — Docker environment configuration and usage.
- `CHANGELOG.md` — Full release history.

Questions or contributions? Open an issue or pull request.

## 🏆 Security Hall of Fame

A huge thank you to the security researchers who have helped make **ci4ms** more secure by finding and reporting vulnerabilities.

| Contributor | Contribution | Date |
| :--- | :--- | :--- |
| **[Lars van Mil](https://github.com/Far-Horizons)** | Identified Critical RCE and Information Disclosure vulnerabilities. | Jan 2026 |
| **[0xAlchemist \| Bugmith [BUGX]](https://github.com/bugmithlegend)** | Identified Critical Stored DOM XSS vulnerabilities across Company Info, Social Media, and Mail Settings modules, and a Session Invalidation flaw, leading to Account Takeover, Privilege Escalation, and potential Platform Compromise. | Feb 2026 |
| **[peeefour](https://github.com/peeefour)** | Identified Stored DOM XSS vulnerabilities leading to Account Takeover. | Feb 2026 |
| **[Hunter.](https://github.com/LAW6ZX7)** | Identified Critical Stored XSS in Backend & Blog modules allowing Session Hijacking. | Feb 2026 |
| **[m1scher](https://github.com/m1scher)** | Assisted with vulnerability triaging and security testing. | Feb 2026 |
| **[alpernae](https://github.com/alpernae)** | Assisted with vulnerability triaging and security testing. | Feb 2026 |
| **[offset](https://github.com/offset)** | Identified Critical vulnerabilities including multiple Stored XSS, Authorization Bypass in Fileeditor, Install Guard Bypass, and CRLF Injection. | Apr 2026 |
| **[fg0x0](https://github.com/fg0x0)** | Identified Critical Arbitrary File Write (Zip Slip RCE) vulnerabilities in Theme::upload and Backup::restore modules. | Apr 2026 |

> If you find a security vulnerability, please report it via [Security Policy](SECURITY.md).
