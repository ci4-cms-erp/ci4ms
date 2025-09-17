# CI4MS

CI4MS is a CodeIgniter 4-based CMS skeleton that delivers a production-ready, modular architecture with RBAC authorization and theme support. It combines CMS workflows, developer-focused CLI commands, an extensible module system, and customizable front-end themes in a single package.

## Key Features
- Authentication & RBAC: `Modules\Auth` handles user login, lockouts, and password resets, while permissions map to `auth_permissions_pages` records.
- Modular backend: Each feature ships as an independent module (Blog, Pages, Menu, Media, Users, Settings, Theme, etc.) under `modules/*`.
- Flexible content management: Page and blog entries include SEO metadata, categories, tags, and full comment workflows.
- Media & files: Includes elFinder-powered media management and a built-in file editor.
- Theme system: The `public/templates/*` structure and the `Modules\Theme` module enable installing or upgrading themes from ZIP packages.
- Setup & automation: Offers a web-based installer (`/install`) plus CLI commands for default data seeding, automatic route generation, and module scaffolding.
- SEO helpers: `Ci4msseoLibrary` builds meta tags and JSON-LD, while `CommonLibrary` centralizes email, breadcrumbs, and inline shortcode utilities.

## Requirements
- PHP 8.1 or newer (intl, json, mbstring, gd, curl, openssl recommended)
- Composer
- MySQL/MariaDB (or any CodeIgniter 4-supported driver)
- Writable directories: `writable/`, `public/uploads/`, optionally `public/templates/`

See `composer.json` for the full dependency list (e.g. `bertugfahriozer/ci4commonmodel`, `gregwar/captcha`, `jasongrimes/paginator`, `melbahja/seo`, `studio-42/elfinder`, `phpmailer/phpmailer`).

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

### Environment & Configuration
1. Create your `.env` and enable the development environment:
   ```bash
   cp env .env
   php spark env development
   ```
2. Update these core settings in `.env`:
   - `app.baseURL`
   - `database.default.*`
   - Optional: `cookie.*`, `honeypot.*`, `security.*`
3. If you prefer the web installer, open `/install` in the browser and follow the wizard. Use the CLI steps below if you want to skip the wizard.

### Database & Seed Data
```bash
php spark migrate
php spark db:seed Ci4msDefaultsSeeder   # You will be prompted for your name, email, and password
php spark create:route                  # Generates the default routes file
php spark key:generate                  # Creates an encryption key
```
The seeder provisions an active administrator account (group_id=1) and populates the initial module records.

### Run the Dev Server
```bash
php spark serve
```
Access the backend via: `https://<domain>/backend`

## Directory Layout
- `app/Controllers/Home.php` — Handles front-end pages, blog listings, details, and comments.
- `app/Libraries/` — Shared helpers (email, SEO, shortcodes).
- `app/Commands/` — CLI tooling (`module:create`, `make:a*`, `create:route`).
- `app/Filters/Ci4ms.php` — Install guard, maintenance mode redirect, menu cache.
- `modules/*` — Each module includes its own `Config/Routes.php`, `Controllers`, `Models`, `Views`, `Language`, `Libraries`, `Filters`.
- `public/templates/` — Theme assets; each theme requires `info.xml` and `screenshot.png`.
- `writable/` — Cache, logs, temporary files.

## Modules
| Module | Purpose | Highlights |
|--------|---------|------------|
| Auth | Authentication lifecycle | CAPTCHA, email activation, reset tokens |
| Backend | Admin shell | Dashboard stats, shared base controller |
| Blog | Blog CRUD | Categories, tags, comments, bad-word filters |
| Pages | Static page management | SEO fields, inline shortcode parsing |
| Menu | Menu builder | Drag-and-drop ordering, slug helpers |
| Media | Media manager | elFinder integration, optional WebP conversion |
| Fileeditor | Project file editor | Safe read/write/rename/move/delete |
| Settings | System configuration | Company/social/mail settings, encrypted SMTP password |
| Users | User & role management | Group-based permissions, reset tracking |
| Methods | Route → permission mapping | Module toggling, router scan |
| ModulesInstaller | Module ZIP installer | Upload + cache invalidation |
| Theme | Theme manager | ZIP upload, duplicate folder checks |
| Install | Web installer | Creates `.env`, triggers migrations |

See `docs/architecture.md` for deeper architectural notes.

## CLI Commands
- `php spark module:create Blog` — Scaffolds a module (`Controllers`, `Models`, `Views`, `Config/Routes.php`).
- `php spark make:acontroller Example` — Generates a backend controller template.
- `php spark make:amodel Example` — Generates a backend model (with options for table, return type).
- `php spark make:abview dashboard` — Generates a backend view from the AdminLTE template.
- `php spark create:route` — Rebuilds `app/Config/Routes.php` from the template.
- Standard CodeIgniter commands: `php spark migrate`, `php spark db:seed`, `php spark cache:clear`, etc.

## Developer Notes
- **Cache keys**: `settings` (24h), `menus` (menu tree, 24h), `{userId}_permissions`. Clear with `php spark cache:clear` or `cache()->delete()`.
- **Base controller**: Extend `Modules\Backend\Controllers\BaseController` for new backend controllers; it prepares session user, navigation, mail settings, and shared data.
- **Permissions**: Remember to register new secured routes in `Modules\Methods` (or via the database) so the permission filter recognizes them.
- **Slug generation**: `seflink()` handles transliteration (including Turkish characters).
- **Form security**: Global CSRF is enabled; backend AJAX endpoints opt out via `BackendConfig::$csrfExcept`.
- **Comment moderation**: `CommonLibrary::commentBadwordFiltering` handles bad word filtering and moderation rules.
- **Email delivery**: `CommonLibrary::phpMailer()` resolves SMTP settings from encrypted storage in `settings.mail`.
- **Theme uploads**: Each theme must include `info.xml` and `screenshot.png`; missing files trigger a backend warning.

## Testing & Maintenance
- `composer test`
- Add coding standards or static analysis as needed (not included by default).
- **Maintenance mode**: When `settings.maintenanceMode.scalar == 1`, the `Ci4ms` filter redirects visitors to `maintenance-mode`.
- **Security**: `Fileeditor` and `Media` enforce `realpath` guards. Limit access in production environments.

## Additional docs
- `docs/architecture.md` — Architecture, flow, permissions, and extension guidance.

Questions or contributions? Open an issue or pull request.
