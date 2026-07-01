# Security Checklist

## Access Control

- Tenant routes must use `tenant.required` and role middleware.
- Platform routes must remain restricted to `platform_owner`.
- Tenant-owned records must include `tenant_id` and queries must scope by current tenant.
- Foreign keys submitted from forms must be validated against the current tenant, not only global existence.
- Waiter, counter, kitchen, admin, super admin, and platform owner access must stay separated by gates and middleware.

## Authentication

- Passwords must be hashed through the `User` model mutator.
- Login POST requests are rate limited.
- Inactive users must be rejected after authentication and logged out immediately.
- CSRF protection must stay enabled for all web forms.

## Data Handling

- Use Eloquent/query builder bindings; do not concatenate user input into SQL.
- Blade output must remain escaped with `{{ }}` unless HTML has been sanitized.
- File uploads must validate type, MIME, and size.
- Card numbers, CVV, PIN, track data, and raw PAN must never be stored.
- Provider credentials must use encrypted casts and must not be returned to frontend payloads.

## Webhooks

- Stripe Billing webhooks must verify `Stripe-Signature`.
- Worldpay webhooks must verify the configured HMAC secret before accepting the event.
- Failed signature events may be recorded for audit, but must return an error and must not trigger payment state changes.

## Files and Backups

- Backup files must stay on private storage disks.
- Backup and migration downloads must check role, scope, tenant ownership, private disk, path validity, and file existence.
- Invoices must not be exposed publicly without authorization.
- Encrypted backups require `BACKUP_ENCRYPTION_KEY`.

## Monitoring and Audit

- Login, logout, failed login, settings changes, delete actions, subscription changes, backup actions, migration actions, and release actions must create audit logs.
- Audit metadata must not include secrets or sensitive payment data.
- Review `/health` before and after deployments.
