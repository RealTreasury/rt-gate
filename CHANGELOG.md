# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - v1.0.0

### Added
- Added submit honeypot support (`honeypot` or `fields._rtg_hp`) so bot-like submissions return an empty payload without creating leads, issuing tokens, or logging events.
- Initial repository scaffolding and deployment/documentation foundations.
- Implemented `includes/class-token.php` with secure 32-byte token generation, SHA-256 token hashing for storage, and expiry validation helpers.
- Implemented `includes/class-rest.php` registering `/wp-json/rtg/v1` endpoints for `/submit`, `/validate`, and `/event`.
- Added transient-based REST rate limiting (10 requests/minute per hashed IP per route).
- Added `rest_pre_serve_request` CORS allowlist behavior for `github.io` origins.
- Added Assets admin URL helper with Media Library picker; selected URL is merged into asset config (`file_url`, `embed_url`, or `target_url`) by asset type.
