# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) conventions adapted to the existing four-component version numbers.

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

[0.26.3.1]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.1
[0.26.3.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.3.0
[0.26.2.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.2.0
[0.26.1.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.1.0
[0.26.0.0]: https://github.com/ci4-cms-erp/ci4ms/releases/tag/0.26.0.0
