# Security Audit

**Scope:** full repository and its Git history, reviewed during the 2026 modernization.
**Method:** manual code review, gitleaks with redaction over the tree and history, `composer audit`, PHPStan, automated tests on PHP 8.4 and 8.5, and end-to-end runs in headless Chromium through a local TLS proxy against disposable MySQL and SMTP containers.
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

## Findings in the first 2.0.0 candidate

A second review of the rewrite found and fixed the following before release.

| # | Severity | Finding | Status |
| --- | --- | --- | --- |
| 12 | High | `Referrer-Policy: no-referrer` made browsers send `Origin: null`, so every real form POST was rejected with 403 | Fixed: `same-origin` policy |
| 13 | Medium | An oversized password skipped Argon2id for existing usernames, exposing them through response timing | Fixed: hashing work always runs |
| 14 | Medium | Active sessions and pending logins survived a password or TOTP reset | Fixed: session version check |
| 15 | Medium | IPv6 clients could bypass rate limits by rotating addresses within a /64 | Fixed: limits apply per /64 |
| 16 | Medium | Blocked clients kept consuming the per-username budget and creating rate limit rows | Fixed: checks stop at the first exceeded limit |
| 17 | Low | Cross-site admin links replaced the `SameSite=Strict` session cookie and signed the admin out | Fixed: same-origin continue page |
| 18 | Low | `X-Powered-By` disclosed the PHP version | Fixed: header removed |
| 19 | Low | Equivalent IPv6 spellings of a trusted proxy were not recognized | Fixed: addresses compared in binary form |
| 20 | Low | `APP_URL` accepted paths and credentials, and an explicit default port broke the origin check | Fixed: strict origin parsing |
| 21 | Low | The documented `.env` loading truncated `DB_DSN` at its first semicolon | Fixed: value quoted |

## Controls added

Argon2id hashing, TOTP with replay protection, field-level authenticated encryption, prepared statements, security headers, hardened sessions, audit logging, data retention, and least-privilege CI with actions pinned to commit hashes.

## Secret scan

gitleaks reported one match in history: the public RFC 6238 test vector in a unit test, which is a false positive. The current tree no longer contains that literal. No private keys, cloud or platform tokens, or hardcoded passwords were found. Scanning is pattern-based, so it cannot prove absence.

## Open items

| Severity | Item | Owner action |
| --- | --- | --- |
| Info | The removed legacy mailer remains in Git history | Do not deploy old revisions |
| Info | Commit metadata and one early revision of the legacy script contain personal email addresses | Decide whether this is acceptable; changing it requires a history rewrite |
| Low | A distributed attacker can temporarily block sign-in for a known username through the per-username limit | Accepted trade-off; monitor failed sign-ins |
| Low | A tampered ciphertext row makes its admin list page return an error | Requires database write access; delete the affected row |
| Low | No independent penetration test has been performed | Commission one before handling sensitive data |

## Dependency status

`composer audit` reported no known advisories for the locked dependencies when last run in November 2026.
