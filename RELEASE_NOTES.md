# Formulario Dorado 2.0.1

Version 2.0.1 is a documentation release. It does not change code behaviour.

## Changes

- The changelog marks 2.0.0 as released on 2026-11-24.
- The README and security audit no longer say that pre-2026 code remains in Git history. On 2026-11-24 the public history was rewritten so that only the 2026 modernization commits remain.
- The audit items about the legacy mailer and personal email addresses in old commits are marked resolved. Anything that was ever published in the old history should still be treated as compromised.

## Upgrading

No action is needed when upgrading from 2.0.0. See the 2.0.0 entry in `CHANGELOG.md` for the changes in the rewrite.

## Verification

The automated tests were run for this release. The CI workflow runs on GitHub for every push to `master`.
