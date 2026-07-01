# PosLAB Launch Checklist

Use this checklist as the release gate before staging sign-off and production launch. Do not mark PosLAB launch ready while any Critical or High item remains open.

## Automated Gate

- [ ] Run `php artisan test`
- [ ] Run `npm run build`
- [ ] Confirm `LaunchReadinessTest` passes:
  - role access across platform owner, admin, waiter, counter, kitchen
  - tenant isolation for menu and tenant context
  - waiter order to kitchen queue to counter payment to invoice artifacts
  - add-on kitchen FIFO ordering
  - mixed cash/card payment
  - invoice text/PDF artifact generation and receipt print job creation
  - subscription restriction for expired vendors
  - add-on feature enablement
  - payment provider sandbox adapter path
  - printer test job creation
  - backup creation and restore dry-run audit
  - migration package dry run
  - tenant private broadcast event dispatch
  - queue dispatch and job handler probe
  - scheduler registration for `backups:run`
  - health and monitoring configuration check

## Staging Smoke Checks

- [ ] Login/logout works for every role.
- [ ] Platform owner can create, edit, suspend, and reactivate a vendor.
- [ ] Tenant admin can save restaurant settings and table count creates active tables.
- [ ] Waiter can create table, takeaway, walk-in, and add-on orders on tablet viewport.
- [ ] Kitchen display receives new/cooking/ready updates through WebSockets.
- [ ] Counter combines unpaid table tickets and completes mixed payment.
- [ ] Receipt print job is created; browser print works on target device.
- [ ] PDF invoice opens from private storage through authorized workflow.
- [ ] Expired/suspended tenant is blocked except billing recovery paths.
- [ ] Backup package is created and checksum is recorded.
- [ ] Migration package dry run creates a package with restore guide and no secrets.
- [ ] Queue worker, scheduler, and WebSocket worker are running under process supervision.
- [ ] `/health` returns `ok` from the production-like network path.
- [ ] Error tracking receives a test event.

## Launch Blocker Rules

- Critical: data leak, tenant isolation failure, payment data sensitivity issue, backup exposure, broken login, broken order/payment flow.
- High: role bypass, subscription restriction bypass, failed queue/WebSocket process in staging, backup failure, invoice/receipt generation failure.
- Medium: non-blocking printer adapter issue, cosmetic mobile layout issue, missing monitoring tag, operational documentation gap.
- Low: copy polish, minor empty-state inconsistency, non-launch placeholder wording.

## Current Launch Decision

Launch readiness is conditional on the automated gate and staging smoke checks passing. If any Critical or High issue is found, fix it and rerun the full gate before approval.
