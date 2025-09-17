# CI4MS Architecture Guide

This document explains how the components inside `app/` and `modules/` work together, how requests flow through the system, and where you can extend the platform.

## Application bootstrap & request lifecycle
- Every request passes through `app/Filters/Ci4ms.php`:
  - Redirects to `/install` if `.env` is missing (fresh setup).
  - Redirects to `maintenance-mode` if the cached settings flag is enabled.
  - On `after()` it caches the menu tree if missing (24h TTL).
- `app/Config/Filters.php` performs dynamic filter discovery:
  - Scans `modules/*/Filters` and the active theme filters to register aliases.
  - Merges backend CSRF exceptions from `Modules\Backend\Config\BackendConfig::$csrfExcept`.
- `app/Config/Routes.php` preloads settings, loads template routes, then includes each module’s routes before defining front-end routes.

## CommonModel abstraction
- Almost every module uses `bertugfahriozer/ci4commonmodel`’s `CommonModel` for CRUD.
- Core helpers: `lists`, `selectOne`, `create`, `createMany`, `edit`, `remove`, `isHave`, `count`.
- Backend `BaseController` instances instantiate `CommonModel` once and share it via `$this->commonModel`.

## Authentication & authorization
- `Modules\Auth\Libraries\AuthLibrary`:
  - Handles login/logout, remember-me cookies, lockouts, password reset tokens, and mail notifications.
  - Sets session keys (`logged_in`, `redirect_url`) and caches user permissions per user ID.
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

## Module pattern
Each module (under `modules/<Name>/`) includes:
- `Config/Routes.php` with backend routes and metadata (`role`, etc.).
- `Config/*.php` for module-specific configuration.
- `Controllers/` (usually extend the backend base controller).
- `Models/` for data access.
- `Views/` for backend UI templates.
- Optional `Libraries/`, `Helpers/`, `Language/`, `Filters/`, `Database/` directories.

Use `php spark module:create Foo` to scaffold a new module skeleton.

## Installation flow
- **Web installer** (`Modules\Install`):
  - Copies `env` to `.env`, updates base settings, triggers migrations, and seeds defaults via `InstallService`.
  - Regenerates `app/Config/Routes.php` from the command template.
- **CLI**: `Ci4msDefaultsSeeder` prompts for admin info and calls `InstallService::createDefaultData()`.
  - Seeds initial modules, permissions, admin user, sample pages/blog entries, and settings.

## Caching & configuration
- `settings`: JSON values are decoded and cached for 24h.
- `menus`: cached for 24h after edits.
- `{userId}_permissions`: cached per user; cleared when permissions change.
- Mail settings: stored encrypted in `settings.mail`, decoded in backend base controller and `CommonLibrary::phpMailer()`.

## Theme system
- Themes live under `public/templates/<theme>/` (plus optional app-level template directories).
- `Modules\Theme` handles ZIP uploads to `writable/tmp/`, detects duplicates, and installs assets/views/helpers.
- Backend filter warns administrators if required files are missing.

## Content & SEO
- `App\Controllers\Home` renders front-end pages/blogs:
  - Parses inline shortcodes via `CommonLibrary::parseInTextFunctions()`.
  - Builds meta tags and JSON-LD with `Ci4msseoLibrary`.
  - Loads categories/tags, authors, breadcrumbs, and comment data.
- Blog/Pages modules store SEO data as JSON (`coverImage`, `description`, `keywords`).

## Media & file management
- `Modules\Media` integrates elFinder, with MIME allowlist from settings and optional WebP conversion (`claviska/simpleimage`).
- `Modules\Fileeditor` provides project-level file browse/edit operations with `realpath` guardrails.

## CLI & automation
- `app/Commands/*` includes module scaffolding (`ModuleCreate`), backend generators (`make:acontroller`, etc.), and route regeneration (`create:route`).
- `Modules\Methods::moduleScan()` inspects the router to align routes with permission records.

## Development tips
- Extend the backend base controller for consistent session and view data.
- Update `Modules\Methods` when adding new secured routes.
- Clear caches (`php spark cache:clear`) after updating settings, menus, or permissions.
- Keep theme directories synchronized with required assets (`info.xml`, `screenshot.png`).
- Captcha auto-disables in development via the environment check in the login controller.

## Common data tables
- `users`, `auth_groups`, `auth_permissions_pages`, `auth_users_permissions`, `modules`, `pages`, `blog`, `blog_categories_pivot`, `tags`, `tags_pivot`, `menu`, `settings`, `login_rules`, etc.

This document should help you navigate, extend, and maintain CI4MS safely. For clarification or enhancements, use the project issue tracker.
