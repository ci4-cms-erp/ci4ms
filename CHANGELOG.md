# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) conventions adapted to the existing four-component version numbers.

## [0.29.2.0] - 2026-02-22

### Security

- **XSS Protection:** Implemented global input validation using `regex_match[/^[^<>{}]*$/u]` for common fields to prevent HTML/Script injection.
- **CSRF Protection:** Verified and refined CSRF settings. Added logic to update CSRF tokens in the UI after AJAX operations to prevent token expiration.
- **Improved Validation:** Relaxed `seflink` regex to allow natural characters while strictly forbidding dangerous ones. Added `is_natural_no_zero` and `valid_email` checks where missing.
- Removed 'seunmatt/codeigniter-log-viewer' vendor dependency
- Implemented 'Modules\Logs\Libraries\LogViewer' for better performance and CI4 integration
- Standardized log deletion with AJAX POST and SweetAlert2 confirmation
- Improved security by escaping log content and removing external vendor code
- Updated Logs controller and views to follow internal architecture patterns

### Added

- **Dynamic Confirmation:** Integrated SweetAlert2 for all delete operations across the dashboard.
- **Localization:** Added new translation keys (`areYouSure`, `youWillNotBeAbleToRecoverThis`, `ok`, `success`, `error`) to all 11 supported languages:
  - Turkish (tr), English (en), Arabic (ar), German (de), Spanish (es), French (fr), Hindi (hi), Japanese (ja), Portuguese (pt), Russian (ru), Chinese (zh).
- **Project Hygiene:** Added `CONTRIBUTORS.md` to `.gitignore`.

### Changed

- **AJAX Refactoring:** Converted all "Delete" actions from `GET` routes to secure AJAX `POST` requests.
- **DataTables Improvements:** Fixed dynamic element initialization (Bootstrap Switch) by moving logic to the DataTables `drawCallback`. This ensures elements work after pagination or searching.
- **Module Consistency:** Standardized variable names and status indicators across `Blog` and `Pages` modules.
- **Routes:** Updated `Routes.php` in multiple modules to support `POST` method for sensitive actions.

### Fixed

- **PHP Logic:** Fixed ternary operator precedence bugs that caused incorrect 'checked' states for status switches.
- **Database Search:** Resolved a linting error in `count()` method calls in controllers.
- **View Cleanup:** Deleted unused `commentList.php` and restructured comment management views.


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
