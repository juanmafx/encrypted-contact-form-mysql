# Changelog

This project follows [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

Complete rewrite. There is no upgrade path from the 2015 script, which kept no database. Files it saved to the legacy upload directory on an old deployment are not migrated and should be reviewed and removed.

### Added

- Encrypted submission storage using XChaCha20-Poly1305 with key rotation support.
- Admin area with Argon2id passwords and TOTP two-factor authentication.
- CSRF protection, Origin and Fetch Metadata checks, rate limiting and a honeypot field.
- Security headers, including a Content Security Policy that allows no scripts.
- Admin audit log, retention purge and an operator CLI.
- Session revocation when an admin's password or TOTP secret changes.
- A same-origin continue page for admin links opened from other sites.
- MySQL schema, unit, functional and integration tests, static analysis and a CI workflow.
- Architecture, security and release documentation.

### Changed

- Separated the code into `backend/` and `frontend/`.
- Translated all identifiers, interface text and documentation to English.
- Moved configuration to validated environment variables.
- Notifications are plain text and omit personal data by default.
- Requires PHP 8.4 or later.

### Removed

- The bundled 2001-2003 PHPMailer copy, replaced by maintained PHPMailer 7 through Composer.
- File upload handling.

### Security

- Fixed reflected and email HTML injection, mail header injection through sender fields, and unrestricted file upload into the web root.
- Fixed form submissions failing in real browsers because of the referrer policy.
- Fixed username disclosure through the timing of oversized passwords.
- Rate limits now group IPv6 clients by /64 and stop counting after the first exceeded limit.
- Removed the `X-Powered-By` header and tightened `APP_URL` and trusted proxy parsing.
