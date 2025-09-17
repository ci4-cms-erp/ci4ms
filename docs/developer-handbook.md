# CI4MS Developer Handbook

This handbook captures the workflows, conventions, and tooling you need to extend or maintain CI4MS with confidence. Use it alongside the README (project overview) and the architecture guide (runtime flow) for a complete picture.

---

## 1. System Requirements

| Layer | Required | Notes |
|-------|----------|-------|
| PHP   | 8.1+     | Enable intl, json, mbstring, gd, curl, openssl extensions. Match the `composer.json` platform (8.1). |
| Composer | 2.5+ | Used for all PHP dependencies. |
| Node.js | 18 LTS | Required only if you maintain `public/be-assets` (backend UI assets). |
| npm    | 8+      | Node package manager for backend asset dependencies. |
| Database | MySQL/MariaDB (or supported CI4 driver) | Configure via `.env`. |
| Web server | Apache/Nginx/CI4 spark serve | Production deploys typically proxy to `public/index.php`. |

---

## 2. Repository Layout Highlights

```
app/                 Application code (controllers, config, libraries, filters)
modules/             Feature modules (Auth, Backend, Blog, etc.)
public/
  index.php          Front controller
  be-assets/         Admin UI build artifacts (CSS/JS)
  templates/         Front-end themes (default shipped)
  uploads/           Media storage (ensure writable)
writable/            Cache, logs, temporary files (must be writable)
vendor/              Composer packages
```

Key config files:
- `composer.json` – PHP dependencies and scripts.
- `app/Config/*.php` – Framework configuration; many classes consume cached settings populated at runtime.
- `.env` – Environment overrides; generated from `env` template.

---

## 3. Getting Started

1. **Clone & install**
   ```bash
   git clone <repo-url> ci4ms
   cd ci4ms
   composer install
   ```

2. **Environment**
   ```bash
   cp env .env
   php spark env development
   ```
   Update: `app.baseURL`, `database.default.*`, mail credentials, cookie/security settings as needed.

3. **Database**
   ```bash
   php spark migrate
   php spark db:seed Ci4msDefaultsSeeder  # prompts for admin account details
   php spark key:generate
   php spark create:route
   ```

4. **Serve**
   ```bash
   php spark serve
   ```
   Visit `http://localhost:8080` (frontend) and `/backend` (admin panel).

> Tip: If you rerun the seeder in an existing database, ensure duplicate records are handled or truncate the relevant tables first.

---

## 4. Dependency Management

### 4.1 Composer packages

The project depends on CodeIgniter 4 and several packages that power key features:
- `bertugfahriozer/ci4commonmodel` – Database abstraction helpers used across modules.
- `bertugfahriozer/sql2migration` – CLI tooling for migrations.
- `ci4-cms-erp/ext_module_generator` – Module scaffolding support.
- `gregwar/captcha`, `jasongrimes/paginator`, `melbahja/seo`, `phpmailer/phpmailer`, `studio-42/elfinder` – Authentication visuals, pagination, SEO metadata, mail transport, media manager.

Install/update:
```bash
composer install        # first-time setup
composer update vendor/package   # update specific dependencies
composer outdated        # check for new versions
```

### 4.2 Node dependencies (admin assets)

The admin interface bundles several JS packages under `public/be-assets/` (Tagify, elFinder themes, Monaco, etc.).

```bash
cd public/be-assets
npm install
# add your build/watch commands here (currently static assets live in css/js)
```

> There is no predefined bundler script yet. If you introduce Vite/Webpack, document the commands and output paths here.

---

## 5. Coding Guidelines

- **Namespaces**: Follow PSR-4 (already enforced via Composer). Modules live under `Modules\<Name>\...` and app-level code under `App\`.
- **Controllers**: Backend controllers must extend `Modules\Backend\Controllers\BaseController` to inherit auth, config, and view data. Frontend controllers extend `App\Controllers\BaseController`.
- **Views**: Stored in module-specific `Views/` paths; reference using full namespace (`view('Modules\Blog\Views\list')`).
- **Helpers/Filters**: Place module-specific helpers in `modules/<Module>/Helpers`, filters in `modules/<Module>/Filters`. Register filters dynamically via `app/Config/Filters.php`.
- **Configuration**: Module configs belong in `modules/<Module>/Config`. Avoid editing core `app/Config` unless behaviour is global.
- **Language strings**: Use `modules/<Module>/Language/<locale>` for translations.
- **Docblocks**: Keep concise PHPDoc on public methods; avoid redundant descriptions.

Formatting/testing:
```bash
composer test     # runs PHPUnit (configure test suite under tests/)
```
Add a linter (PHP-CS-Fixer, Pint) if you need formatting automation.

---

## 6. Modules & Permissions

- Permissions live in `auth_permissions_pages` (CRUD flags stored as JSON) and `auth_users_permissions` (user overrides).
- `Modules\Methods\Controllers\Methods::moduleScan()` inspects defined routes and maps them to permission records.
- After adding a backend route, either:
  - Run the module scan to sync permissions, or
  - Insert a record manually into `auth_permissions_pages` with matching controller/method names.
- Clear cached permission keys (`{userId}_permissions`) after changes: `php spark cache:clear` or `cache()->delete("{$id}_permissions")`.

Recommended workflow when adding a module:
1. Scaffold with `php spark module:create <Name>`.
2. Add routes in `modules/<Name>/Config/Routes.php` (include `role` metadata).
3. Implement controllers/models/views.
4. Register permissions (module scan or manual).
5. Write seeds/migrations if the module introduces new tables.

---

## 7. Configuration & Settings Cache

Application settings are persisted in the `settings` table and cached for 24 hours.
- Use `cache()->delete('settings')` after updating settings programmatically.
- Menu structures are cached as `menus`; cleared automatically when updating through the Menu module, or manually if needed.
- Mail credentials are encrypted (base64 + CodeIgniter encrypter). Use `CommonLibrary::phpMailer()` or backend base controller to fetch decrypted values.
- Maintenance mode flag lives under `settings.maintenanceMode`. When set, `App\Filters\Ci4ms` redirects all traffic to `maintenance-mode` except install/login.

---

## 8. Media, File, and Theme Handling

### Media (`Modules\Media`)
- elFinder configuration resides in `Modules\Media\Controllers\Media::elfinderConnection()`.
- Allowed MIME types come from settings (`settings.allowedFiles`).
- Optional WebP conversion uses `claviska/simpleimage` when enabled.
- Media root: `public/uploads/media/`. Ensure the directory (and `.trash`) are writable by the web server.

### File editor (`Modules\Fileeditor`)
- Provides tree/file editing within the project root. `realpath` checks prevent escaping `ROOTPATH`.
- Restrict access to trusted roles; any structural change is immediate.

### Themes (`Modules\Theme`)
- Themes live in `public/templates/<theme>/` plus optional app-level overrides (`app/Config/templates/<theme>`, etc.).
- Upload flow: ZIP → `writable/tmp` → install helper → final directories.
- Required files per theme: `info.xml`, `screenshot.png`. Missing assets trigger warnings via `BackendAfterLoginFilter`.

---

## 9. Front Controller & Public Assets

- `public/index.php` bootstraps CodeIgniter. Production setups should point the web server to the `public/` directory only.
- `public/maintenance/` contains the maintenance splash view served when maintenance mode is active.
- `public/be-assets/` holds backend CSS/JS, images, third-party plugins, and dependency manifests (`package.json`, `package-lock.json`).
- `public/uploads/` is user-generated content; backup and secure it accordingly.
- `public/templates/default/` is the bundled frontend theme; use it as a reference when building new themes.

---

## 10. Testing & QA

- **Unit tests** live under `tests/`. Add module-specific tests in `tests/Modules/<Module>`.
- Configure database or service mocks via `tests/_support` (autoloaded per `composer.json`).
- For manual QA, rely on the maintenance mode toggle to hide changes until ready.
- Consider adding integration tests for critical flows (authentication, page CRUD, menu updates).

---

## 11. Debugging Tips

- Enable the toolbar in development via `.env`: `CI_ENVIRONMENT = development`.
- Logs reside in `writable/logs/`. Always confirm the directory is writable; check the daily log file for stack traces.
- Cache issues? Clear with `php spark cache:clear` or delete cache files in `writable/cache/`.
- Database migrations failing? Inspect `writable/logs/` and confirm the migration batch table `ci_migrations` is in sync.
- Mail issues? Use the `Modules\Settings\Controllers\Settings::testMail()` endpoint (AJAX) after configuring SMTP.

---

## 12. Deployment Checklist

1. Set `CI_ENVIRONMENT = production` in `.env`.
2. Ensure `app.baseURL` reflects the public domain (include protocol).
3. Configure web server document root to `public/` and deny direct access to other directories.
4. Run migrations/seeds (`php spark migrate --all` / relevant seeders).
5. Cache warm-up (optional): trigger first page load or run custom warmers.
6. Clear debug toolbar: disable in production via `app/Config/Toolbar.php` or environment settings.
7. Secure writable directories with proper permissions (typically 775/664 or similar, depending on server user).
8. Back up `uploads/`, database, and `.env` before major upgrades.

---

## 13. Contribution Workflow

- **Branching**: feature branches prefixed with module or scope (e.g., `feature/blog-scheduling`).
- **Commits**: reference modules or issues (`[Blog] Add scheduling support`).
- **Pull requests**: include setup notes (migrations, new env vars, npm installs).
- **Code review**: highlight permission updates, cache implications, and front-end asset changes.
- **Changelog**: maintain a project changelog if versioning releases.

---

## 14. Further Reading & Resources

- [CodeIgniter 4 Documentation](https://codeigniter4.github.io/userguide/)
- [Composer](https://getcomposer.org/doc/)
- [CI4MS Architecture Guide](./architecture.md)
- [CI4MS User Guide (HTML)](./user-guide.html)
- Internal module documentation (check each module’s README or docblocks).

Maintain this handbook as you evolve the stack—update dependencies, asset workflows, or deployment scripts here so the next developer has a reliable source of truth.
