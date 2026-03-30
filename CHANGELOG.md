# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) conventions adapted to the existing four-component version numbers.

## [0.31.1.0] - 2026-03-30

### Added

- **Theme Manager:** Added `downloadStarter` feature offering automated, memory-based ZIP creation to provide a standardized starter boilerplate theme directly from the admin panel.
- **Theme Manager:** Smart deletion confirmation GUI that parses theme migration files and allows users to drop associated database tables selectively.
- **Theme Manager:** Added a standalone `remove_theme_files` helper specifically designed to clean up MVC templates and public asset files safely from the project root.
- **Theme Manager:** Automated schema migration implementation inside the Settings module that runs database setups upon theme activation via configuration logic.
- **Core UI:** Integrated multiple message list support into the SweetAlert `_message_block` component for enhanced feedback logs.

### Changed

- **Theme Manager:** Enhanced `install_theme_from_tmp` to support and copy the `Database/Migrations` directory automatically upon extracting a new `.zip` template.
- **Settings UI:** Upgraded backend settings view to elegantly display an extra "Delete Theme" action under inactive template cards.

### Fixed

- **Theme Manager:** Fixed recursive directory deletion method (`deleteFldr`) in `themes_helper.php` to resolve missing directory exceptions (by correcting parameter count based on recent code deprecations) and during legacy theme updates.

## [0.31.0.0] - 2026-03-29

### Security

- **CodeIgniter Shield Integration:** Fully replaced custom authentication migrations with Shield-compatible structures (`auth_groups`, `auth_identities`, `auth_groups_users`). Removed 14 legacy migration files and introduced 6 new Shield-aligned migrations with proper foreign keys.
- **BackendLogFilter:** Added `modules/Backend/Filters/BackendLogFilter.php` to record detailed user activities (IP, user agent, action, module) in the backend for audit trail and security monitoring.
- **XSS Protection:** Implemented global input validation using `regex_match[/^[<>{}]*$/u]` for common fields to prevent HTML/Script injection.
- **CSRF Protection:** Verified and refined CSRF settings. Enhanced `mergeCsrfExcept` method for improved robustness. Added logic to update CSRF tokens in the UI after AJAX operations to prevent token expiration.
- **Improved Validation:** Relaxed `seflink` regex to allow natural characters while strictly forbidding dangerous ones. Added `is_natural_no_zero` and `valid_email` checks where missing.
- Removed 'seunmatt/codeigniter-log-viewer' vendor dependency.
- Implemented `Modules\Logs\Libraries\LogViewer` for better performance and CI4 integration.
- Standardized log deletion with AJAX POST and SweetAlert2 confirmation.
- Improved security by escaping log content and removing external vendor code.
- Updated Logs controller and views to follow internal architecture patterns.

### Added

- **Framework Configurations:** Added `WorkerMode.php` and `Hostnames.php` to support high-performance environments (e.g., Swoole, FrankenPHP).
- **Migration Safeguards:** Implemented `$lock` feature in `app/Config/Migrations.php` to prevent concurrent migration execution conflicts.
- **Dynamic Sidebar:** Implemented auto-configuration for sidebar menus and icons, populated directly from module `Config` parameters.
- **Shared Logic:** Introduced `CommonBackendLibrary` to centralize common backend operational logic across controllers.
- **Template Settings UI:** Comprehensive user-friendly interface for:
  - Dynamically managing theme assets (CSS, JavaScript).
  - Injecting custom CSS and JavaScript code globally.
  - Configuring footer content, including copyright and navigation links.
  - Selecting and previewing Google Fonts.
  - Toggling general display features (breadcrumbs, back-to-top button, dark mode).
  - Controlling sidebar widgets visibility.
- **Version Checker:** Implemented version checking mechanism to notify administrators of available application updates.
- **Development Tools:** Added a custom module generator hook for streamlined backend module creation.
- **Dynamic Confirmation:** Integrated SweetAlert2 for all delete operations across the dashboard.
- **Localization:** Added new translation keys (`areYouSure`, `youWillNotBeAbleToRecoverThis`, `ok`, `success`, `error`) to all 11 supported languages:
  - Turkish (tr), English (en), Arabic (ar), German (de), Spanish (es), French (fr), Hindi (hi), Japanese (ja), Portuguese (pt), Russian (ru), Chinese (zh).
- **Project Hygiene:** Added `CONTRIBUTORS.md` to `.gitignore`.

### Changed

- **System Requirements:** Upgraded minimum PHP requirement to **8.2** across `composer.json`, `public/index.php`, and `spark` to comply with CodeIgniter 4.7.1 standards.
- **Core Dependencies:** Bumped `codeigniter4/framework` to `4.7.1`, `codeigniter4/shield` to `1.3.0`, `codeigniter4/translations` to `4.7.0`, and `claviska/simpleimage` to `4.4.0`.
- **Module Management:** Refined `moduleScan` capabilities and introduced new interactive UI elements for better backend module oversight.
- **Auth System Overhaul:** Refactored user and permission group management to fully leverage CodeIgniter Shield's capabilities. Removed legacy `Backend/Models/UserModel.php` in favour of Shield's built-in user entity.
- **Standardized API Responses:** Unified response formats across backend Settings endpoints using `ResponseTrait`.
- **Cache Invalidation:** Ensured proper sidebar menu cache invalidation upon permission page creation.
- **Asset Optimization:** Migrated heavy frontend dependencies from `node_modules` to standalone `vendor` and `plugins` directories in `be-assets` and `templates`. Drastically reduced repository size (~147MB saved) by removing source maps, unminified files, and unused package logic.
- **Fileeditor Enhancements:**
  - Implemented robust client-side alphabetical sorting (folders first, with Turkish locale character support).
  - Enhanced Monaco Editor integration with accurate language detection (PHP, JS, CSS, MD, ENV) and `vs-dark` theme optimization.
  - Cleaned up redundant logic and syntax issues in the file tree view.
- **AJAX Refactoring:** Converted all "Delete" actions from `GET` routes to secure AJAX `POST` requests.
- **DataTables Improvements:** Fixed dynamic element initialization (Bootstrap Switch) by moving logic to the DataTables `drawCallback`.
- **Module Consistency:** Standardized variable names and status indicators across `Blog` and `Pages` modules.
- **Routes:** Updated `Routes.php` in multiple modules to support `POST` method for sensitive actions.
- **Frontend:** Updated `ci4ms.js` with improved global utilities and event handlers.
- **User Management:** Configured user 'group' assignments in `UserController` to support multiple groups via Select2 array inputs. Refactored validation rules (`group.*`) and role synchronization logic (`syncGroups`) for user creation and updating. Enhanced user statistics counters to dynamically exclude `superadmin` accounts.
- **Template Views:** Refined `base.php`, `pages.php`, `post.php`, and `temp-settings.php` for Shield compatibility and new settings UI.

### Fixed

- **Tags Architecture:** Fixed pivot table insertion logic in `CommonTagsLibrary` to ensure new tags are always correctly linked and persisted for blog posts.
- **Sidebar Navigation:** Fixed the sidebar menu auto-open and active state logic (AdminLTE 3) to accurately reflect the user's current module.
- **Fileeditor Module:** Fixed the path validation regex to correctly allow multi-level folder traversal (`/` character).
- **PHP Logic:** Fixed ternary operator precedence bugs that caused incorrect 'checked' states for status switches.
- **Database Search:** Resolved a linting error in `count()` method calls in controllers.
- **View Cleanup:** Deleted unused `commentList.php` and restructured comment management views.

### Removed

- Deleted 14 legacy authentication/authorization migration files replaced by Shield-compatible schemas.
- Removed `Backend/Models/UserModel.php` (superseded by Shield's user management).

## [0.26.3.4] - 2025-09-27

### Added

- Delivered full translation packs for every module in Spanish, French, German, Chinese, Russian, Japanese, Arabic, Portuguese, and Hindi, including validation to preserve existing placeholders and HTML tokens.

## [0.26.3.3] - 2025-09-26

### Added

- Seed missing default permissions for file editor actions, the backend theme manager, and the WebP toggle during installation.

### Changed

- Build the settings cache once during filter bootstrap to eliminate redundant database lookups.
- Move the WebP conversion toggle from the AJAX controller to the Settings controller so cache invalidation happens automatically after updates.
- Normalize blog `created_at` values to the standard `Y-m-d H:i:s` format before persisting entries.
- Use the correct language keys for blog category headings to resolve localization mismatches.

### Fixed

- Exclude matches that only appear inside HTML comments from frontend autocomplete suggestions and display category labels correctly.
- Remove the unused backend test route and broaden the blog module CSRF exceptions to cover the required endpoints.

## [0.26.3.2] - 2025-09-25

### Added

- Automatically add the Logs module to the admin menu during installation so the log viewer is available from the first run.

### Changed

- Ship the `.gitattributes` file inside distribution packages so attribute rules accompany exported archives.

## [0.26.3.1] - 2025-09-19

### Added

- Started maintaining this changelog to track release highlights.

### Changed

- Marked documentation and auxiliary files with `export-ignore` so Composer dist packages stay lean.
- Refreshed docs to cover the new module generator command, backend log viewer, and changelog access.

## [0.26.3.0] - 2025-09-19

### Added

- Integrated the CI Log Viewer package and exposed a dedicated backend module for reviewing application logs.
- Captured per-action permission flags as structured JSON when creating or updating backend methods.

### Changed

- Refreshed backend method management forms, navigation buttons, and module awareness.
- Loaded SweetAlert assets globally for backend pages and updated in-app documentation links to their GitHub sources.

### Removed

- Dropped the legacy `module:create` CLI command in favour of the composer-driven module generator dependency.

## [0.26.2.0] - 2025-09-17

### Changed

- Updated documentation links to reference the project root correctly.

## [0.26.1.0] - 2025-09-17

### Added

- Published the initial developer documentation set for CI4MS.

## [0.26.0.0] - 2025-09-17

### Added

- Expanded database migrations and introduced new supporting libraries.

[0.26.3.4]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.4
[0.26.3.3]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.3
[0.26.3.2]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.2
[0.26.3.1]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.1
[0.26.3.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.0
[0.26.2.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.2.0
[0.26.1.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.1.0
[0.26.0.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.0.0
