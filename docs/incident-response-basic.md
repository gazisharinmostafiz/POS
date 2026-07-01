# Basic Incident Response

## 1. Identify

- Confirm the affected tenant, branch, user, route, file, or provider.
- Capture timestamps, user ids, tenant ids, request ids if available, and relevant audit log entries.
- Check `/health`, application logs, web server logs, queue logs, and provider dashboards.

## 2. Contain

- Disable affected user accounts or suspend the affected tenant if access is actively unsafe.
- Rotate exposed provider credentials, printer bridge tokens, API keys, and webhook secrets.
- Put the platform into release maintenance mode if customer-facing integrity is at risk.
- Block suspicious IPs at the edge or hosting firewall where available.

## 3. Preserve Evidence

- Export relevant audit logs and webhook events.
- Preserve backup checksums and deployment version information.
- Do not edit production data manually before taking a verified backup.

## 4. Eradicate and Recover

- Patch the vulnerable code path.
- Run the full test suite and targeted regression tests.
- Deploy through the production checklist, including backup before update and migrations.
- Verify tenant isolation, login, payments, backups, printers, and reports.

## 5. Notify and Review

- Notify affected vendors with clear impact, timing, and required action.
- Document root cause, data exposure assessment, fixes, and follow-up controls.
- Add or update tests so the incident class cannot silently return.
