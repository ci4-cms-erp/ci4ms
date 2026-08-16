# CI4MS Developer Handbook

This handbook captures the workflows, conventions, and tooling you need to extend or maintain CI4MS with confidence. Use it alongside the README (project overview) and the architecture guide (runtime flow) for a complete picture.

---

## 1. System Requirements

| Layer      | Required                                | Notes                                                                                                 |
| ---------- | --------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| PHP        | **8.2+**                                | Enable `intl`, `json`, `mbstring`, `gd`, `curl`, `openssl` extensions. Matches `composer.json` (8.2). |
| Composer   | 2.5+                                    | Used for all PHP dependencies.                                                                        |
| Database   | MySQL / MariaDB (or supported CI4 driver) | Configure via `.env`.                                                                                 |
| Web server | Apache / Nginx / `php spark serve`      | Production deploys should point to the `public/` directory.                                           |
| Docker     | Docker Engine 24+ / Docker Desktop      | Optional but recommended for local development and CI environments.                                   |

---

## 2. Repository Layout Highlights

```text
app/                 Application code (controllers, config, libraries, filters)
modules/             Feature modules (Auth, Backend, Blog, etc.)
public/
  index.php          Front controller
  be-assets/         Admin UI build artifacts (CSS/JS)
  templates/         Front-end themes (default shipped)
  media/             Media storage (ensure writable)
writable/            Cache, logs, temporary files (must be writable)
vendor/              Composer packages
.docker/             Dockerfile, Apache vhost, and php.ini
.github/workflows/   GitHub Actions CI pipeline
docs/                Developer documentation (this file and companions)
```

Key config files:

- `composer.json` — PHP dependencies and scripts.
- `app/Config/DefaultRoutes.php` — Routes template; copy to `Routes.php` on setup.
- `app/Config/Paths.php` — Path constants including `$supportDirectory` (required by CI4 4.4+).
- `app/Config/*.php` — Framework configuration; many classes consume cached settings populated at runtime.
- `.env` — Environment overrides; generated from the `env` template.

---

## 3. Getting Started

### 3.1 Standard (local PHP)

1. **Clone & install**

   ```bash
   git clone <repo-url> ci4ms
   cd ci4ms
   composer install
   ```

2. **Environment**

   ```bash
   cp env .env
   ```

   Update: `app.baseURL`, `database.default.*`, mail credentials, cookie/security settings as needed.

3. **Prepare routes**

   ```bash
   cp app/Config/DefaultRoutes.php app/Config/Routes.php
   ```

4. **One-command setup**

   ```bash
   php spark ci4ms:setup
   ```

   This single command runs all migrations across every module, seeds default data (modules, permissions, admin user, sample pages/blog entries, and settings), and prepares the application for first use. No separate migrate, seed, or key:generate commands are needed.

5. **Serve**

   ```bash
   php spark serve
   ```

   Visit `http://localhost:8080` (frontend) and `/backend` (admin panel).

### 3.2 Docker

```bash
cp env .env
cp app/Config/DefaultRoutes.php app/Config/Routes.php
# Edit .env: set database.default.hostname=db and other values
docker compose up -d --build
docker exec ci4ms_app composer install
docker exec ci4ms_app php spark ci4ms:setup
```

Refer to `DOCKER_SETUP.md` for full configuration details, including environment variables for the containerized database.

---

## 4. Dependency Management

### 4.1 Composer packages

The project depends on CodeIgniter 4 and several packages that power key features:

- `codeigniter4/framework` — Core framework (4.7.1+).
- `codeigniter4/shield` — Authentication and authorization (Shield-based RBAC).
- `bertugfahriozer/ci4commonmodel` — Database abstraction helpers used across modules.
- `bertugfahriozer/sql2migration` — CLI tooling for migrations.
- `bertugfahriozer/ci4seopro` — SEO, JSON-LD, and feed generation.
- `ci4-cms-erp/ext_module_generator` — Module scaffolding exposed as `php spark make:module`.
- `claviska/simpleimage` — Image manipulation and WebP conversion for media uploads.
- `gregwar/captcha` — CAPTCHA generation for login forms.
- `studio-42/elfinder` — File manager integration for the Media module.

Install/update:

```bash
composer install              # first-time setup
composer update vendor/package  # update a specific dependency
composer outdated             # check for newer versions
```

### 4.2 Frontend Assets

The admin interface and default templates use static JS/CSS packages (Tagify, Monaco Editor, Bootstrap, etc.).
To keep the repository lean, third-party libraries are hosted statically within `public/be-assets/plugins/` (backend) and `public/templates/default/assets/vendor/` (frontend) instead of using `node_modules`.

If you wish to introduce a bundler (Webpack or Vite), add a `package.json` to the appropriate asset directory, compile assets, and exclude `node_modules` from version control.

---

## 5. Coding Guidelines

- **Namespaces**: PSR-4, enforced via Composer. Modules live under `Modules\<Name>\...`, app-level code under `App\`.
- **Controllers**: Backend controllers must extend `Modules\Backend\Controllers\BaseController` to inherit auth, config, and view data. Frontend controllers extend `App\Controllers\BaseController`.
- **Views**: Stored in module-specific `Views/` paths; reference using the full namespace (`view('Modules\Blog\Views\list')`).
- **Helpers/Filters**: Place module-specific helpers in `modules/<Module>/Helpers`, filters in `modules/<Module>/Filters`. Register filters dynamically via `app/Config/Filters.php`.
- **Configuration**: Module configs belong in `modules/<Module>/Config`. Avoid editing core `app/Config` unless the behaviour is global.
- **Language strings**: Use `modules/<Module>/Language/<locale>` for translations. 11 languages are currently supported.
- **Docblocks**: Keep concise PHPDoc on public methods; avoid redundant descriptions.

Formatting/testing:

```bash
composer test     # runs PHPUnit (configure test suite under tests/)
```

---

## 6. Modules & Permissions

- Permissions live in `auth_permissions_pages` (CRUD flags stored as JSON) and `auth_users_permissions` (user overrides).
- `Modules\Methods\Controllers\Methods::moduleScan()` inspects defined routes and maps them to permission records.
- After adding a backend route, either run the module scan to sync permissions, or insert a record manually into `auth_permissions_pages` with matching controller/method names.
- Clear the RBAC cache after permission/group changes: `php spark cache:clear` or `cache()->delete('shield_auth_dynamic_config')` (Shield's dynamic groups/permissions config — the actual RBAC cache key; `{$id}_permissions` is legacy and nothing populates it).

Recommended workflow when adding a module:

1. Scaffold with `php spark make:module <Name>`.
2. Add routes in `modules/<Name>/Config/Routes.php` (include `role` metadata).
3. Implement controllers, models, and views.
4. Register permissions via module scan or manually.
5. Write migrations if the module introduces new tables.

---

## 7. Configuration & Settings Cache

Application settings are persisted in the `settings` table and cached for 24 hours.

- Use `cache()->delete('settings')` after updating settings programmatically.
- **Canonical decode:** The `settings` cache can be warmed by whichever entry point hits it first while the key is cold (`app/Config/Filters.php`, the generated `app/Config/Routes.php`, or `modules/Auth/Controllers/BaseController.php`). Every filler must use the same decode — `json_decode($value)` gated on `json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded))` — so a cold cache always warms to a consistent `stdClass` shape. Consume nested settings via object access (`$settings->group->key`); only JSON-array levels stay PHP arrays and remain `foreach`-iterable. Do not `(object) json_decode(...)`-cast the top level: that leaves nested levels as arrays and produces a fragile mixed shape.
- Menu structures are cached as `menus_{locale}` (per locale); cleared automatically via the Menu module, or manually with `cache()->delete('menus_en')` etc.
- Maintenance mode flag lives under `settings.maintenanceMode`. When set, `App\Filters\Ci4ms` redirects all traffic to `maintenance-mode`. Read it as a raw scalar string (`$settings->maintenanceMode`), not via a `->scalar` wrapper.

---

## 8. Media, File, and Theme Handling

### Media (`Modules\Media`)

- elFinder configuration resides in `Modules\Media\Controllers\Media::elfinderConnection()`.
- Allowed MIME types come from `settings.allowedFiles`.
- Optional WebP conversion uses `claviska/simpleimage` when enabled in settings.
- elFinder's internal CSRF validation is bypassed because the connector runs behind CI4 Shield's session-based authentication and the `backendGuard` filter already verifies user access.
- Write/delete access is gated by a layered access-control chain: the elFinder write commands are declared once as the `Media::WRITE_COMMANDS` class constant (single source of truth) and shared by both the controller-level 403 gate (via the pure, unit-testable `isWriteBlocked()` helper) and elFinder's disabled-commands list, so a "read" permission cannot silently grant write/delete access. The elFinder `debug` flag is gated to non-production (`ENVIRONMENT !== 'production'`) to avoid leaking connector debug data in production.
- **What may be written is a separate gate from who may write it.** A user holding `media.media.create` passes the permission chain entirely, so the MIME allowlist is not the last line of defence — and on its own it is porous: elFinder's internal extension→MIME table maps only `php` to `text/x-php`, so every other script extension resolves to `text/plain`, which `settings.allowedFiles` permits. Measured against the shipped settings, `shell.php` is refused while `shell.phtml`, `shell.php5`, `shell.pht` and `shell.phar` are accepted. `Media::DENIED_EXTENSIONS` closes this in the `elfinderAccess()` callback on the `write` attribute — command-independent, so it covers `upload`, `mkfile`, `put`, `paste`, `rename` and `extract` alike. **Every dot-separated segment is checked, not only the last**, so `shell.php.jpg` is refused too: which segment a server treats as the handler is a configuration detail this gate must not depend on. Names are lowercased and stripped of trailing dots and spaces first. Reads are deliberately untouched so an administrator can still see and delete anything already present.
- Upload-security options live in `Media::volumeSecurityOptions()` and are shared by both volumes: `uploadDeny => ['all']`, `uploadAllow` from settings, `uploadOrder => ['deny','allow']` and `uploadMaxSize` (32M). **`uploadOrder` must stay deny-first** — flipped to allow-first, elFinder defaults to permitting anything the allowlist does not mention. `tests/Modules/Media/MediaUploadGateTest.php` pins all of the above.
- Media root: `public/media/`. Ensure the directory (and `.trash`) are writable. `public/media/.htaccess` blocks script execution there, but **only on Apache** — nginx, Caddy and FrankenPHP ignore `.htaccess`, so those deployments must mirror the rules in the vhost; see [web-server-hardening.md](web-server-hardening.md).

### File Editor (`Modules\Fileeditor`)

- Provides tree/file editing within the project root. `realpath` checks prevent path traversal outside `ROOTPATH`.
- A `$dangerousExtensions` blacklist blocks creating, writing, or renaming executable files (`.php`, `.phtml`, `.phar`, `.htaccess`, etc.) via the editor.
- Only files with extensions in `$allowedExtensions` (`css`, `js`, `html`, `txt`, `json`, `sql`, `md`) can be read and edited.
- Restrict access to trusted roles only; changes are immediate and irreversible via the UI.

### Themes (`Modules\Theme`)

- Themes live in `public/templates/<theme>/` plus optional app-level overrides.
- Upload flow: ZIP → `writable/tmp/` → install helper → final directories.
- Required files per theme: `info.xml`, `screenshot.png`. Missing assets trigger warnings via `BackendAfterLoginFilter`.
- Themes can ship database migrations inside `Database/Migrations/`; these are automatically copied and run on activation.
- The Theme Manager supports generating a starter boilerplate ZIP directly from the admin panel.

### Backup (`Modules\Backup`)

- Generates full database ZIP archives in `writable/uploads/backups/`.
- Uses `mysqldump` if available, falls back to a PHP-based export.
- Restore from server-stored archives directly within the backend.
- **Security:** SQL restore enforces a statement whitelist (only `INSERT`, `CREATE TABLE`, `DROP TABLE`, `ALTER TABLE`, `SET`, `UPDATE`, `DELETE` are allowed). Dangerous commands (`LOAD_FILE`, `INTO OUTFILE`, `GRANT`, `CREATE USER`, stored procedures) are blocked and logged. Backup files must reside within `WRITEPATH`.

---

## 9. Front Controller & Public Assets

- `public/index.php` bootstraps CodeIgniter. Point the web server document root to `public/` only.
- `public/maintenance/` contains the splash view served during maintenance mode.
- `public/be-assets/` holds backend CSS/JS, images, third-party plugins.
- `public/uploads/` is user-generated content; back it up regularly.
- `public/templates/default/` is the bundled frontend theme; use it as a reference when building new themes.

---

## 10. Testing & QA

- Unit tests live under `tests/`. Add module-specific tests in `tests/Modules/<Module>`.
- The GitHub Actions workflow (`.github/workflows/docker-test.yaml`) runs on every push to `master`:
  - Builds the Docker image.
  - Waits for MariaDB to be healthy.
  - Runs `composer install`.
  - Executes `php spark ci4ms:setup` for migrations and seeding.
  - Performs a PHP syntax check across `app/` and `modules/`.
  - Verifies HTTP responses for the homepage and backend.
- For manual QA, use the maintenance mode toggle to hide changes until they are ready.

### Testing against the live schema without touching it

There is no separate test database: `CommonModel` hardcodes the `default` connection group, so a test that exercises a real query-builder path runs against your development database. Two patterns keep that non-destructive, and new tests should follow whichever fits:

- **Marker-scoped rows.** Write only rows carrying a per-run marker (a random `type` value, a throwaway high user id), assert counts as **deltas** so unrelated existing rows cannot skew them, and delete exactly those rows in `tearDown()`. Never mutate a pre-existing row. See `tests/Modules/Notifications/NotifierTest.php`.
- **Shadow tables (`CREATE TEMPORARY TABLE`).** When a test needs a schema the development database does not have — e.g. a column or table added by a migration that has not run there — do **not** run the migration: create a `TEMPORARY` table of the same name on the **same** connection the production code uses. In MySQL/MariaDB a temporary table masks a permanent one of the same name for that connection only, so the real schema is never altered, real rows are neither read nor written, and the shadow disappears when the connection closes. `tearDown()` still drops it explicitly, always with the `TEMPORARY` keyword (which makes the statement a no-op against a permanent table). Foreign keys are omitted — temporary tables cannot carry them, which is also what lets such tests address throwaway user ids. If the MySQL user lacks `CREATE TEMPORARY TABLES`, skip the test rather than weakening it. `tests/_support/Notifications/ShadowSchemaTrait.php` implements this (including a pre-migration variant used to assert fail-closed behaviour), and `assertPermanentSchemaIntact()` proves the shadow never leaked into real DDL.
- Any request-scoped schema memoisation (e.g. `Modules\Notifications\Libraries\SchemaGuard`) must be reset both when a shadow is created and when it is dropped; otherwise a stale `true` leaks into later test classes and they emit SQL for columns the real table does not have.

---

## 11. Debugging Tips

- Enable the toolbar in development: set `CI_ENVIRONMENT = development` in `.env`.
- Logs reside in `writable/logs/`. Review the daily log file for stack traces, or use the backend log viewer at `/backend/logs`.
- Cache issues? Run `php spark cache:clear` or delete files in `writable/cache/`.
- Migration failures? Check `writable/logs/` and confirm the migration batch table is in sync.
- Mail issues? Use `Modules\Settings\Controllers\Settings::testMail()` (AJAX) after configuring SMTP.
- Docker issues? Run `docker compose logs app` to inspect the container output.

## 12. Security Architecture

CI4MS implements modern security practices to protect the application and user data:

- **CSRF Protection:** Enabled globally. For AJAX requests, `public/be-assets/js/ci4ms.js` automatically reads the CSRF token from the `meta` tag and injects it into all AJAX requests via the `X-CSRF-TOKEN` header and POST parameters. Do not disable CSRF per module unless absolutely necessary (e.g. external webhooks or elFinder uploads).
- **XSS & HTML Sanitization:** Content editors utilize `CustomRules::getClean()` to scrub HTML through HTMLPurifier. Dangerous schemes like `data:` and properties like `CSS.Trusted` are disabled by default. Persisted, backend-editable values must also be output-encoded with `esc()` when rendered — both in backend views and frontend templates (e.g. Blog/Pages cover-image `<img src>` attributes, category titles, SEO `description` blobs). Cover-image URL fields additionally enforce a strict input regex that accepts only `http(s)://` or `/`-relative URLs ending in a known image extension, rejecting `"`, `=`, whitespace, and `()` to prevent attribute breakouts.
- **File System Integrity:** The Fileeditor module enforces strict blacklisting for executable extensions (`.php`, `.phtml`, `.phar`, etc.) to prevent Remote Code Execution (RCE). Operations are restricted strictly to safe paths using `realpath()` boundary validations.
- **Rate Limiting:** Protects the application against brute-force and DDoS attacks via `ThrottleFilter` and `BackendThrottleFilter` (HTTP 429 Too Many Requests).
- **Session Security:** Inactive administrative sessions are automatically locked by `LockController` to prevent unauthorized access.

---

## 13. Deployment Checklist

1. Set `CI_ENVIRONMENT = production` in `.env`.
2. Ensure `app.baseURL` reflects the public domain (include protocol).
3. Configure the web server document root to `public/` and deny direct access to all other directories.
4. Run migrations: `php spark migrate --all`.
5. **Proxy IPs:** If behind Cloudflare or Nginx, configure `App.php::$proxyIPs` with trusted ranges (see the commented examples in the file) so that `$request->getIPAddress()` returns real client IPs.
6. Cache warm-up (optional): trigger the first page load or run custom warmers.
7. Disable the debug toolbar: set via `app/Config/Toolbar.php` or the environment flag.
8. Set proper permissions on writable directories (typically `775`/`664` depending on server user).
9. Back up `public/uploads/`, the database, and `.env` before major upgrades.
10. **Realtime notifications (optional).** The Redis-backed SSE bell (`Modules\Notifications`) is **self-contained — no external hub, JWT, or nginx config change**. To enable it, ensure `ext-redis` (phpredis) is installed and `Config\Cache::$redis` points at a reachable Redis, set the `notificationsconfig.*` env keys (`realtimeEnabled = true`, plus optional `realtimeStreamTtl` / `realtimeSignalTtl` / `realtimeConnCapDefault` / `realtimeConnCapByGroup.superadmin`), and register the `RealtimeController` stream route as a permission (`notifications.realtimecontroller.read` — the same permission the read endpoints use, no new one) via the Methods module scan, then `php spark cache:clear`. Because each open `backend/notifications/stream` connection holds a short-lived php-fpm worker for up to `realtimeStreamTtl` seconds (120 s ceiling), size `pm.max_children` for concurrent admins and set PHP `max_execution_time` / FPM `request_terminate_timeout` above `realtimeStreamTtl`. Concurrent streams **per identity** are capped (`realtimeConnCapDefault`, default `6`; `superadmin` `10`, highest matching group wins; a **negative** value means unlimited, while `0` is invalid — a non-numeric `.env` value is cast to `0`, so it falls back fail-closed to `6` with a warning), reserved atomically in Redis before the stream opens; over the cap the endpoint returns `429` and the client falls back to polling. The cap is per identity, not global, and it fails **closed** (`429`) when Redis is unreachable — deliberate, since without Redis the stream could not deliver anything anyway. Left disabled (the default), the bell keeps its 60 s polling behaviour. See the Notifications module README for full details.
11. **Notification targeting & preferences (upgrade step).** After `php spark migrate --all` has added `notifications.exclude_users` and created `notification_preferences`, run the Methods **Module Scan** so `notifications.preferencecontroller.read` / `.update` are registered, grant them to the groups that should manage their own opt-out, then `php spark cache:clear`. Until the scan runs the preference screen is fail-closed `403` for non-superadmins; until the migrations run the module still delivers ordinary notifications, and so does a dispatch whose exclusion was only **derived** from the user↔group overlap narrowing (fail-open, logged at `warning`; at worst the directly targeted user sees it twice) — but a dispatch carrying an **explicit** `exceptUser()` exclusion is **refused** (fail-closed, logged at `critical`) rather than delivered to an excluded user.

---

## 14. Contribution Workflow

- **Branching**: feature branches prefixed with module or scope (e.g., `feature/blog-scheduling`).
- **Commits**: reference modules or issues (e.g., `[Blog] Add scheduling support`).
- **Pull requests**: include setup notes (migrations, new env vars, asset changes).
- **Code review**: highlight permission updates, cache implications, and front-end asset changes.
- **Changelog**: update `CHANGELOG.md` following the Keep a Changelog format before merging.

---

## 15. Further Reading & Resources

- [CodeIgniter 4 Documentation](https://codeigniter4.github.io/userguide/)
- [CodeIgniter Shield Documentation](https://shield.codeigniter.com/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [CI4MS Architecture Guide](./architecture.md)
- [CI4MS User Guide (HTML)](./user-guide.html)
- [CI4MS Theme Development Guide](./theme_development.md)
- [DOCKER_SETUP.md](../DOCKER_SETUP.md) — Docker environment reference.
- Internal module documentation: check each module's docblocks for implementation details.

Maintain this handbook as you evolve the stack — update dependencies, asset workflows, or deployment scripts here so the next developer has a reliable source of truth.
