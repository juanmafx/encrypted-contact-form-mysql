# Security Audit

**Scope:** full repository and its Git history, reviewed during the 2026 modernization.
**Method:** manual code review, pattern-based secret scan of history, `composer audit`, PHPStan, automated tests and an end-to-end run against disposable MySQL and SMTP containers.
**Limits:** this is a self-review. It is not an independent penetration test.

## Findings in the 2015 code

| # | Severity | Finding | Status |
| --- | --- | --- | --- |
| 1 | Critical | Unrestricted file upload into a web-served directory | Fixed: feature removed |
| 2 | Critical | Bundled 2001-2003 mailer with known vulnerabilities | Fixed: replaced by PHPMailer 7 |
| 3 | High | Reflected XSS in form fields | Fixed: contextual escaping and CSP |
| 4 | High | Unescaped user input in HTML email | Fixed: plain text email |
| 5 | High | Mail header injection through sender fields | Fixed: strict validation, fixed sender |
| 6 | High | Sender address spoofing using visitor input | Fixed: configured sender only |
| 7 | Medium | No CSRF protection | Fixed |
| 8 | Medium | No rate limiting, allowing mail abuse | Fixed |
| 9 | Medium | No server-side validation of email format | Fixed |
| 10 | Medium | Predictable upload names from `rand()` | Fixed: feature removed |
| 11 | Low | Short open tags and undefined variables on modern PHP | Fixed: rewritten |

## Controls added

Argon2id hashing, TOTP with replay protection, field-level authenticated encryption, prepared statements, security headers, hardened sessions, audit logging, data retention, and least-privilege CI with actions pinned to commit hashes.

## Secret scan

No private keys, cloud or platform tokens, or hardcoded passwords were found in the current tree or in history. Scanning was pattern-based, so it cannot prove absence.

## Open items

| Severity | Item | Owner action |
| --- | --- | --- |
| Info | The removed legacy mailer remains in Git history | Do not deploy old revisions |
| Info | Commit metadata and one early revision of the legacy script contain personal email addresses | Decide whether this is acceptable; changing it requires a history rewrite |
| Low | Active sessions survive a password or TOTP reset until they time out | Planned hardening |
| Low | No independent penetration test has been performed | Commission one before handling sensitive data |

## Dependency status

`composer audit` reported no known advisories for the locked dependencies at the time of this audit.
