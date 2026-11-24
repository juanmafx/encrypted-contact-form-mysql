# Architecture

## Goals

1. Keep the scope of a contact form: collect a message, notify the owner, let the owner read it.
2. Assume every request is hostile and every stored field could leak.
3. Stay small enough to audit: three direct runtime dependencies and no framework.

## Layout

```text
backend/
  bin/admin.php             Operator CLI
  public/index.php          Front controller and only web-exposed PHP file
  src/
    Application.php         Composition root
    Admin/                  Admin identity, authentication flow, audit contract
    Cli/                    Admin and retention commands
    Config/                 Fail-closed environment parsing
    Contact/                Validation, submission service, repository contracts
    Http/                   Request, response, kernel, headers, templates
      Controller/           Contact and admin controllers
    Mail/                   PHPMailer notifier
    Persistence/            PDO repositories and rate limiter
    Security/               Cipher, hashing, TOTP, CSRF, sessions, throttling
  tests/
    Unit/                   Isolated component tests
    Functional/             Full kernel flows over in-memory adapters
    Integration/            PDO adapters against real MySQL
    Support/                Fakes and harness
frontend/
  assets/css/app.css        Stylesheet
  templates/                Server-rendered views
db/schema.sql               Database contract
```

Dependencies point inward. `Contact` and `Admin` define interfaces such as `SubmissionRepository`, `AdminRepository` and `SubmissionNotifier`. `Persistence` and `Mail` implement them, and only `Application` wires concrete classes together. This is what allows the full HTTP flows to be tested without a database.

The frontend is server-rendered and ships no JavaScript, which lets the Content Security Policy forbid scripts entirely.

## Request lifecycle

1. `index.php` loads configuration. Invalid configuration returns a generic 503.
2. `Kernel` rejects oversized bodies.
3. Every POST must pass Fetch Metadata, Origin and CSRF checks.
4. The route runs its controller.
5. Security headers are applied to every response, including errors.
6. Unhandled exceptions are logged by type only, so personal data does not reach the logs.

## Submission flow

1. The validator normalizes input and rejects control characters.
2. A rate limit keyed by an HMAC of the client IP is checked.
3. Each field is encrypted with a fresh nonce. Associated data binds it to the key id, submission id and field name.
4. The row is committed **before** mail is attempted, so an SMTP outage cannot lose a message.
5. Delivery success is recorded in `notified_at`. A failure is logged and visible to the admin.

## Admin authentication

```text
password (Argon2id) ──> pending session (5 min) ──> TOTP ──> admin session
```

- Unknown usernames perform equivalent Argon2id work to reduce user enumeration through timing.
- The session id is regenerated and the CSRF token rotated at each transition.
- A TOTP step is claimed with a conditional `UPDATE`, so concurrent requests cannot reuse a code.
- Sessions expire after 15 idle minutes or 8 hours in total.

## Data model

| Table | Purpose | Sensitive content |
| --- | --- | --- |
| `contact_submissions` | Messages | Ciphertext only |
| `admin_users` | Admin credentials | Argon2id hash, encrypted TOTP secret |
| `admin_audit_log` | Admin actions | Ids and action names |
| `rate_limit_buckets` | Abuse counters | HMAC of the subject, no raw IP |

Encrypted fields cannot be searched or sorted in SQL. That is an accepted cost: the admin list is ordered by time and paginated.

## Recommended deployment

```text
Internet ──> TLS reverse proxy ──> PHP-FPM (backend/public) ──> private MySQL 8.4
                                          └──> authenticated SMTP over TLS
```

- Expose only `backend/public`.
- Give the database user only `SELECT`, `INSERT`, `UPDATE` and `DELETE` at runtime, and apply the schema with a separate account.
- Keep keys in a secret manager or protected environment file, and back them up separately from the database.
- Set `TRUSTED_PROXIES` to the exact proxy addresses.
- Set `post_max_size` close to the application's 64 KiB limit.
- Schedule `backend/bin/admin.php purge` daily.

## Known trade-offs

- Sessions use PHP's native handler, so multiple application servers need shared session storage.
- Changing a password or TOTP secret does not end sessions that are already active; they expire through the timeouts.
- Mail is sent synchronously with a 10-second timeout, which suits low volume. A queue would be the next step for higher traffic.
