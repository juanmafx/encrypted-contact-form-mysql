# Formulario Dorado

A self-hosted PHP contact form that stores messages encrypted and exposes them only through an admin area protected by a password and TOTP two-factor authentication.

> **Project history:** originally created in 2015, modernized in 2026.

## Purpose

Small sites often need one thing from a backend: let a visitor send a message and let the owner read it. That job looks trivial, yet a contact form handles personal data from anonymous users, sends email, and is a constant target for spam and injection.

This project exists to do that one job carefully. The 2015 version was a single PHP script that passed raw input into an HTML email. Version 2 keeps the same small scope and rebuilds it with explicit security boundaries.

## What it does

- Serves a contact form collecting name, phone, email and an optional message.
- Validates input on the server and rejects control characters in single-line fields, which blocks mail header injection.
- Encrypts every personal field with XChaCha20-Poly1305 before it reaches the database.
- Notifies a configured inbox over SMTP. By default the email contains only a link, not the personal data.
- Provides an admin area to list, read and permanently delete submissions.
- Requires Argon2id password verification followed by a TOTP code for admin access.
- Records admin logins, reads and deletions in an audit table.
- Purges submissions past a configurable retention period.

It does not accept file uploads. The 2015 upload handling was removed because it wrote user-named files into a directory under the web root.

## Architecture

```text
Browser ──> backend/public/index.php ──> Kernel (body limit, origin + CSRF checks, routing)
                                           ├─ ContactController ─> Validator ─> SubmissionService
                                           └─ AdminController ──> AdminAuthenticator
                                                                       │
                     FieldCipher (encrypt/decrypt) <───────────────────┤
                     MySQL (ciphertext, hashes, audit, rate limits) <──┤
                     SMTP via PHPMailer (notification) <───────────────┘
```

```text
backend/
  bin/admin.php       Admin and retention CLI
  public/index.php    Front controller (the only web root)
  src/                Admin, Cli, Config, Contact, Http, Mail, Persistence, Security
  tests/              Unit, Functional, Integration, Support
frontend/
  assets/css/         Stylesheet
  templates/          Escaped server-rendered views
db/schema.sql         MySQL schema
docs/                 Architecture and security audit
reports/tests/        Committed output of a real test run
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the design and its trade-offs.

## Quick start

Requirements: PHP 8.4 or 8.5 with `mbstring`, `pdo_mysql`, `session` and `sodium`, PHP built with Argon2 support, Composer 2, and MySQL 8.4.

```bash
composer install --no-dev --no-scripts --no-plugins
cp .env.example .env
```

Generate keys and put them in `.env`:

```bash
echo "DATA_ENCRYPTION_KEYS=v1:$(head -c 32 /dev/urandom | base64 -w0)"
echo "IP_HASH_KEY=$(head -c 32 /dev/urandom | base64 -w0)"
```

Create the database objects, load the environment and create the first admin:

```bash
mysql -u root -p formulario_dorado < db/schema.sql
set -a; . ./.env; set +a
php backend/bin/admin.php create your-admin-name
```

The command prompts for a password of at least 12 characters and prints an `otpauth://` URI **once**. Add it to an authenticator app.

For local development only, set `APP_ENV=development`, `APP_URL=http://127.0.0.1:8080` and `SMTP_ENCRYPTION=none` with a local mail catcher, then run:

```bash
php -S 127.0.0.1:8080 backend/public/index.php
```

In production, point the web server document root at `backend/public` and route all requests to `index.php`. With PHP-FPM, pass the variables through the pool configuration, because `clear_env` hides them by default.

## Usage guide

| Task | How |
| --- | --- |
| Send a message | Open `/` and submit the form |
| Sign in as admin | Open `/admin`, enter the password, then the six-digit code |
| Read or delete a submission | Use the list at `/admin/submissions` |
| Change a password | `php backend/bin/admin.php set-password <username>` |
| Replace a lost authenticator | `php backend/bin/admin.php reset-totp <username>` |
| Apply retention | `php backend/bin/admin.php purge` (schedule it daily) |

There are no web-based recovery codes. Recovery from a lost authenticator requires shell access to run `reset-totp`.

## Configuration

All configuration comes from environment variables. The application refuses to start when a required value is missing or unsafe.

| Variable | Required | Default | Meaning |
| --- | --- | --- | --- |
| `APP_ENV` | yes | | `production`, `development` or `test` |
| `APP_URL` | yes | | Public base URL; must be `https` in production |
| `DB_DSN`, `DB_USER` | yes | | MySQL connection |
| `DB_PASSWORD` | no | empty | Database password |
| `DATA_ENCRYPTION_KEYS` | yes | | Comma-separated `id:base64` 32-byte keys |
| `DATA_ENCRYPTION_ACTIVE_KEY` | yes | | Key id used for new writes |
| `IP_HASH_KEY` | yes | | Base64 32-byte HMAC key for rate limit buckets |
| `MAIL_TO`, `MAIL_FROM` | yes | | Notification recipient and fixed sender |
| `MAIL_INCLUDE_PERSONAL_DATA` | no | `false` | Include the message and Reply-To in the email |
| `SMTP_HOST` | yes | | SMTP server |
| `SMTP_PORT` | no | `587` | SMTP port |
| `SMTP_USER`, `SMTP_PASSWORD` | no | empty | SMTP credentials |
| `SMTP_ENCRYPTION` | no | `tls` | `tls`, `smtps`, or `none` (rejected in production) |
| `TRUSTED_PROXIES` | no | empty | Exact proxy IPs allowed to set `X-Forwarded-For` |
| `RETENTION_DAYS` | no | `365` | Age at which `purge` deletes submissions |

**Key rotation:** add a new key to `DATA_ENCRYPTION_KEYS` and point `DATA_ENCRYPTION_ACTIVE_KEY` at it. Keep old keys configured, because existing rows are not re-encrypted automatically. Losing a key makes the data encrypted under it unrecoverable.

## Testing

```bash
composer install --no-scripts --no-plugins
vendor/bin/phpunit --testsuite unit,functional
vendor/bin/phpstan analyse --memory-limit=512M
```

Integration tests need a **disposable** MySQL database, because they drop and recreate the application tables:

```bash
TEST_DB_DSN='mysql:host=127.0.0.1;dbname=fd_test;charset=utf8mb4' \
TEST_DB_USER=fd_test TEST_DB_PASSWORD=change-me vendor/bin/phpunit
```

Without `TEST_DB_DSN` the integration tests are reported as skipped. The output of the latest full run is in [reports/tests/](reports/tests/). Code coverage is not measured.

## Security

- **Passwords:** Argon2id (64 MiB, 3 iterations) with a unique random salt per hash.
- **Two-factor:** RFC 6238 TOTP, one step of clock drift, single-use codes enforced atomically in the database.
- **Data at rest:** authenticated encryption per field, bound to the record and field so ciphertext cannot be moved between them.
- **Web:** CSRF tokens, Origin and Fetch Metadata checks, contextual output escaping, and a strict Content Security Policy with no scripts.
- **Sessions:** `HttpOnly`, `SameSite=Strict`, `Secure` with the `__Host-` prefix over HTTPS, regenerated at each privilege change, with idle and absolute timeouts.
- **Abuse controls:** rate limits on submissions, password attempts and TOTP attempts, plus a honeypot field.
- **Database:** prepared statements only, with emulation disabled.

Encryption protects a stolen database or backup. It does not protect against a compromised application server, which holds the keys. See [SECURITY.md](SECURITY.md) to report a vulnerability and [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md) for the audit.

## Credits and license

- Email delivery uses [PHPMailer](https://github.com/PHPMailer/PHPMailer) (LGPL-2.1), installed through Composer. Versions before 2.0.0 bundled a 2001-2003 copy by Brent R. Matzelle under the LGPL; it was removed from the tree and remains in Git history under its original license.
- TOTP uses [spomky-labs/otphp](https://github.com/Spomky-Labs/otphp) (MIT).

This repository does not currently declare a license for its own code, so default copyright applies.
