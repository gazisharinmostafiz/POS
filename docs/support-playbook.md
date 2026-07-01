# PosLAB Support Playbook

## Severity Levels

- P0 Critical: active data leak, unauthorized tenant access, payment-sensitive data exposure, total production outage.
- P1 High: core order/payment flow down, login blocked for a tenant, queue/WebSocket workers down, backups failing.
- P2 Medium: printer failures, degraded reports, provider sandbox/live issue with workaround, isolated UI break.
- P3 Low: cosmetic defects, copy issues, minor documentation gaps.

## First Response Checklist

1. Capture tenant, branch, user role, order number, payment id, printer id, and timestamp.
2. Check `/health`, application logs, queue failures, WebSocket process, Redis, database, and error tracking.
3. Confirm the user is in the correct tenant and role.
4. Reproduce in staging if the issue is not an active production incident.
5. Record whether the issue affects data integrity, payments, backups, or tenant isolation.

## Common Triage Paths

### Login Or Access Denied

- Confirm user is active and role is correct.
- Confirm tenant subscription is active or inside grace period.
- Review audit logs for login, failed login, role changes, impersonation placeholders, and user changes.

### Orders Not Reaching Kitchen Or Counter

- Check order, order_items, kitchen_tickets, and print_jobs for the tenant.
- Confirm WebSocket worker and queue worker are running.
- Use counter/kitchen refresh as a temporary workaround if realtime delivery is degraded.

### Payment Issue

- Confirm no card numbers, CVV, PIN, or track data were stored.
- Check payment record, provider transaction id, terminal id, status, and safe metadata.
- Check provider logs and webhook_events.
- For manual terminal flow, confirm cashier reference and terminal receipt.

### Printer Issue

- Confirm printer is active, tenant/branch scoped, and role is receipt, kitchen, or both.
- Create a test print job from settings.
- Retry failed print jobs.
- Browser print is the supported launch method; network/ePOS adapters should be validated per site.

### Backup Or Migration Issue

- Confirm backup disk is private.
- Verify checksum and status.
- Do not restore without explicit confirmation from the incident owner.
- Migration packages must include `.env.example` checklist only, not secrets.

## Escalation

- Escalate P0 immediately to engineering lead and platform owner.
- Escalate P1 within 15 minutes if no workaround is confirmed.
- For payment provider incidents, collect provider transaction ids and safe request/response logs before contacting provider support.
- For suspected data exposure, follow `docs/incident-response-basic.md` and preserve logs.

## Customer Update Template

Status: investigating / identified / monitoring / resolved

Impact: affected tenant, role, and workflow.

Workaround: current safe workaround, if available.

Next update: concrete time for the next update.
