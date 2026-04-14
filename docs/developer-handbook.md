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
- Clear cached permission keys after changes: `php spark cache:clear` or `cache()->delete("{$id}_permissions")`.

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
- Menu structures are cached as `menus`; cleared automatically via the Menu module, or manually with `cache()->delete('menus')`.
- Maintenance mode flag lives under `settings.maintenanceMode`. When set, `App\Filters\Ci4ms` redirects all traffic to `maintenance-mode`.

---

## 8. Media, File, and Theme Handling

### Media (`Modules\Media`)

- elFinder configuration resides in `Modules\Media\Controllers\Media::elfinderConnection()`.
- Allowed MIME types come from `settings.allowedFiles`.
- Optional WebP conversion uses `claviska/simpleimage` when enabled in settings.
- Media root: `public/media/`. Ensure the directory (and `.trash`) are writable.

### File Editor (`Modules\Fileeditor`)

- Provides tree/file editing within the project root. `realpath` checks prevent path traversal outside `ROOTPATH`.
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

---

## 11. Debugging Tips

- Enable the toolbar in development: set `CI_ENVIRONMENT = development` in `.env`.
- Logs reside in `writable/logs/`. Review the daily log file for stack traces, or use the backend log viewer at `/backend/logs`.
- Cache issues? Run `php spark cache:clear` or delete files in `writable/cache/`.
- Migration failures? Check `writable/logs/` and confirm the migration batch table is in sync.
- Mail issues? Use `Modules\Settings\Controllers\Settings::testMail()` (AJAX) after configuring SMTP.
- Docker issues? Run `docker compose logs app` to inspect the container output.

---

## 12. Deployment Checklist

1. Set `CI_ENVIRONMENT = production` in `.env`.
2. Ensure `app.baseURL` reflects the public domain (include protocol).
3. Configure the web server document root to `public/` and deny direct access to all other directories.
4. Run migrations: `php spark migrate --all`.
5. Cache warm-up (optional): trigger the first page load or run custom warmers.
6. Disable the debug toolbar: set via `app/Config/Toolbar.php` or the environment flag.
7. Set proper permissions on writable directories (typically `775`/`664` depending on server user).
8. Back up `public/uploads/`, the database, and `.env` before major upgrades.

---

## 13. Contribution Workflow

- **Branching**: feature branches prefixed with module or scope (e.g., `feature/blog-scheduling`).
- **Commits**: reference modules or issues (e.g., `[Blog] Add scheduling support`).
- **Pull requests**: include setup notes (migrations, new env vars, asset changes).
- **Code review**: highlight permission updates, cache implications, and front-end asset changes.
- **Changelog**: update `CHANGELOG.md` following the Keep a Changelog format before merging.

---

## 14. Further Reading & Resources

- [CodeIgniter 4 Documentation](https://codeigniter4.github.io/userguide/)
- [CodeIgniter Shield Documentation](https://shield.codeigniter.com/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [CI4MS Architecture Guide](./architecture.md)
- [CI4MS User Guide (HTML)](./user-guide.html)
- [CI4MS Theme Development Guide](./theme_development.md)
- [DOCKER_SETUP.md](../DOCKER_SETUP.md) — Docker environment reference.
- [security_audit_report.md](../security_audit_report.md) — Comprehensive security audit report and vulnerability mitigations.
- Internal module documentation: check each module's docblocks for implementation details.

Maintain this handbook as you evolve the stack — update dependencies, asset workflows, or deployment scripts here so the next developer has a reliable source of truth.
