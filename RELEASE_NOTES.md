# Formulario Dorado 2.0.0

Version 2.0.0 rebuilds the 2015 single-file contact form as a small, tested application with encrypted storage and a two-factor admin area.

## Highlights

- Personal data is encrypted per field before storage.
- Admin access requires an Argon2id-verified password and a single-use TOTP code, and credential changes end active sessions.
- Requests are protected by CSRF, origin, rate limit and input validation checks.
- The legacy bundled mailer is replaced by maintained PHPMailer 7.
- The codebase, interface and documentation are fully in English.

## Breaking changes

- Requires PHP 8.4 or later, MySQL 8.4, and environment configuration.
- The web root is now `backend/public`.
- Form field names and routes changed.
- File uploads are no longer supported.

## Upgrading

The previous version kept no database, so there is nothing to migrate. If an old deployment has files in `archivos/`, review and remove them. Follow the quick start in `README.md`, apply `db/schema.sql` and create an admin.

## Verification

Results of the test run for this release are in `reports/tests/`. The contact, sign-in, TOTP, read, delete, sign-out and session revocation flows were also run in headless Chromium through a local TLS proxy. The CI workflow is included but has not run on GitHub yet, and no production deployment has been tested.
