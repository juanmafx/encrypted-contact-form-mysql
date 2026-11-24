# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 2.x | Yes |
| Earlier, unversioned code | No. Do not deploy it. |

## Reporting a vulnerability

Please use GitHub's private vulnerability reporting on this repository, under the **Security** tab. If it is not enabled, open an issue that asks for a private contact and leave out all technical detail.

Include the affected version, the impact and the steps needed to reproduce the problem. Do not access data that is not yours, and do not run denial-of-service or spam tests against a live deployment.

This is a personal project, so responses are best effort and no response time is guaranteed.

## Operator checklist

- Serve only `backend/public`, over HTTPS.
- Generate unique keys and never commit `.env`.
- Back up encryption keys separately from the database.
- Use a least-privilege database account at runtime.
- Set `TRUSTED_PROXIES` only to proxies you control.
- Set `display_errors=Off` and `post_max_size=128K` in `php.ini`.
- Run `composer audit` regularly and schedule the `purge` command.
