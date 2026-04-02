# CI4MS Architecture Guide

This document explains how the components inside `app/` and `modules/` work together, how requests flow through the system, and where you can extend the platform.

## Application Bootstrap & Request Lifecycle

Every request passes through `app/Filters/Ci4ms.php`:

- Redirects to `/install` if `.env` is missing (fresh setup).
- Redirects to `maintenance-mode` if the cached settings flag is enabled.
- On `after()`, caches the menu tree if it is absent (24h TTL).

`app/Config/Filters.php` performs dynamic filter discovery:

- Scans `modules/*/Filters` and the active theme filters to register aliases.
- Merges backend CSRF exceptions from `Modules\Backend\Config\BackendConfig::$csrfExcept`.

`app/Config/Routes.php` preloads settings, loads template routes, then includes each module's routes before defining front-end routes. A default template is shipped as `app/Config/DefaultRoutes.php` — copy it to `Routes.php` during setup.

## CommonModel Abstraction

Almost every module uses `bertugfahriozer/ci4commonmodel`'s `CommonModel` for CRUD. Core helpers: `lists`, `selectOne`, `create`, `createMany`, `edit`, `remove`, `isHave`, `count`. Backend `BaseController` instances instantiate `CommonModel` once and share it via `$this->commonModel`.

## Authentication & Authorization

Authentication is powered by **CodeIgniter Shield** (`codeigniter4/shield`):

- `Modules\Auth\Libraries\AuthLibrary` handles login/logout, remember-me cookies, lockouts, password reset tokens, and email notifications. It sets session keys (`logged_in`, `redirect_url`) and caches user permissions per user ID.
- Shield database tables: `auth_groups`, `auth_identities`, `auth_groups_users`, with proper foreign keys.
- Backend requests pass through `Modules\Backend\Filters\BackendAfterLoginFilter`:
  - Redirects to logout if the user is not authenticated.
  - Validates permissions with `AuthLibrary::has_perm()`; otherwise redirects to `/backend/403`.
  - Validates theme metadata (`info.xml`, `screenshot.png`) and warms the settings cache.
- `Modules\Backend\Controllers\BaseController` centralizes:
  - Logged-in user lookup via `Modules\Users\Models\UserscrudModel::loggedUser()`.
  - Navigation data (from `AuthLibrary::sidebarNavigation()`), settings, encrypter, mail config, and default view data (`$this->defData`).
- Authorization tables:
  - `auth_permissions_pages` defines module/page permissions (stored as JSON CRUD flags).
  - `auth_users_permissions` stores user-specific overrides.
  - `Modules\Methods` manages these tables and can auto-scan routes to populate permissions.
- Backend activity is logged via `Modules\Backend\Filters\BackendLogFilter` (IP, user agent, action, module) for audit trail purposes.

## Module Pattern

Each module (under `modules/<Name>/`) includes:

- `Config/Routes.php` — backend routes and metadata (`role`, etc.).
- `Config/*.php` — module-specific configuration.
- `Controllers/` — usually extend the backend base controller.
- `Models/` — data access layer.
- `Views/` — backend UI templates.
- Optional: `Libraries/`, `Helpers/`, `Language/`, `Filters/`, `Database/Migrations/`.

Use `php spark make:module Foo` (provided by `ci4-cms-erp/ext_module_generator`) to scaffold a new module skeleton.

## Installation Flow

### Web Installer (`Modules\Install`)

- Copies `env` to `.env`, updates base settings, triggers migrations, and seeds defaults via `InstallService`.
- Regenerates `app/Config/Routes.php` from the `DefaultRoutes.php` template.

### CLI (`php spark ci4ms:setup`)

The `Ci4msSetup` command provides a fully automated installation path:

- Accepts admin account details as command-line arguments.
- Runs all database migrations across every module (`--all`).
- Calls `InstallService::createDefaultData()` to seed modules, permissions, admin user, sample pages/blog entries, and settings.
- Designed for use in CI/CD pipelines (Docker, GitHub Actions) where a browser-based installer is not practical.

## Docker & CI/CD

CI4MS ships with a complete Docker environment:

- `.docker/Dockerfile` — PHP 8.2 + Apache, with all required extensions pre-installed.
- `.docker/apache/000-default.conf` — Apache virtual host pointing to `public/`.
- `.docker/php/php.ini` — PHP configuration tuned for CI4MS.
- `docker-compose.yml` — Orchestrates `app`, `db` (MariaDB), and `phpmyadmin` services.
- `.github/workflows/docker-test.yaml` — GitHub Actions pipeline that builds the image, waits for the database, runs `php spark ci4ms:setup`, performs a PHP syntax check, and validates HTTP responses.

Key `Paths.php` note: CI4 4.4+ requires a `$supportDirectory` property in `app/Config/Paths.php` pointing to the framework's ThirdParty directory. This is pre-configured in the repository:

```php
public string $supportDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system/ThirdParty';
```

## Caching & Configuration

| Cache key | Contents | TTL |
|---|---|---|
| `settings` | Decoded JSON settings values | 24h |
| `menus` | Sidebar menu tree | 24h |
| `{userId}_permissions` | Per-user permission flags | Until invalidated |

Clear all caches with `php spark cache:clear` or selectively via `cache()->delete($key)`.

## Theme System

- Themes live under `public/templates/<theme>/` (plus optional app-level template directories).
- `Modules\Theme` handles ZIP uploads to `writable/tmp/`, detects duplicates, installs assets/views/helpers, and copies `Database/Migrations/` if present.
- On theme activation, any bundled database migrations are executed automatically via the Settings module.
- The Theme Manager can generate a downloadable starter boilerplate ZIP directly from the admin panel.
- Backend filter warns administrators if `info.xml` or `screenshot.png` are missing.

## Content & SEO

`App\Controllers\Home` renders front-end pages/blogs:

- Parses inline shortcodes via `CommonLibrary::parseInTextFunctions()`.
- Builds meta tags and JSON-LD with `Ci4msseoLibrary`.
- Loads categories/tags, authors, breadcrumbs, and comment data.

Blog and Pages modules store SEO data as JSON (`coverImage`, `description`, `keywords`).

## Media, File Management & Logs

- `Modules\Media` integrates elFinder, with a MIME allowlist from settings and optional WebP conversion (`claviska/simpleimage`).
- `Modules\Fileeditor` provides project-level file browse/edit operations with `realpath` guardrails.
- `Modules\Logs` implements a custom `LogViewer` library so administrators can securely inspect `writable/logs/` from `/backend/logs` without shell access.
- `Modules\Backup` provides database backup and restore functionality, generating `.zip` archives in `writable/uploads/backups/`.

## CLI & Automation

| Command | Purpose |
|---|---|
| `php spark ci4ms:setup` | Full automated installation (migrations + seeding) |
| `php spark make:module <n>` | Scaffold a new module skeleton |
| `php spark make:abview <n>` | Generate a backend view from the AdminLTE template |
| `php spark create:route` | Rebuild `app/Config/Routes.php` from the template |
| `php spark migrate --all` | Run all pending migrations |
| `php spark cache:clear` | Clear all application caches |

`Modules\Methods::moduleScan()` inspects the router to align routes with permission records.

## Development Tips

- Extend the backend base controller for consistent session and view data.
- Update `Modules\Methods` when adding new secured routes.
- Clear caches (`php spark cache:clear`) after updating settings, menus, or permissions.
- Keep theme directories synchronized with required assets (`info.xml`, `screenshot.png`).
- Captcha auto-disables in development via the environment check in the login controller.
- Use `php spark ci4ms:setup` in CI pipelines instead of chaining multiple spark commands.

## Common Data Tables

`users`, `auth_groups`, `auth_identities`, `auth_groups_users`, `auth_permissions_pages`, `auth_users_permissions`, `modules`, `pages`, `blog`, `blog_categories_pivot`, `tags`, `tags_pivot`, `menu`, `settings`, `login_rules`, etc.

This document should help you navigate, extend, and maintain CI4MS safely. For clarification or enhancements, use the project issue tracker.
