# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2025-12-13
### Added
- Three-option multiple-choice validation question for competitions.
- Admin Flatpickr date/time picker with timezone selector.
- Draw date stored as UTC ISO (`YYYY-MM-DDTHH:MM:SSZ`) with timezone meta.
- Frontend countdown updated to parse UTC ISO timestamps.
- Assets organized under `assets/` (frontend JS/CSS and admin initializer).
- Winners shortcode heading changed to "Recent Winners".

### Changed
- Product fields updated to support multiple-choice options and correct option selection.
- Validation logic updated to prefer multiple-choice and fall back to legacy free-text.
- Ticket cap and percent-sold rendering improvements.

### Fixed
- Various admin/frontend parsing and formatting edge cases for draw dates.

## [1.1.0] - 2025-11-XX
### Added
- Initial plugin core: competition product meta, ticket allocation, instant-win ledger, winners CPT, audit logging, GDPR anonymization, shortcodes, account endpoints, countdown and percent-sold bar.

