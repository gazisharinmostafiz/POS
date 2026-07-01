# PosLAB Go-Live Runbook

## T-7 Days

1. Confirm target infrastructure: VPS/cloud host, PostgreSQL/MySQL, Redis, queue worker, scheduler, WebSocket process, Nginx, HTTPS.
2. Configure environment variables from `.env.example`; never copy secrets into source control or migration packages.
3. Configure vendor subscription provider, restaurant payment providers, printer settings, backup storage, and monitoring DSN.
4. Restore a sanitized staging database and run `php artisan migrate --force`.
5. Run `php artisan test` and `npm run build`.
6. Execute the staging smoke checks in `docs/launch-checklist.md`.

## T-1 Day

1. Freeze non-critical changes.
2. Confirm backup storage is private and downloadable only by authorized users.
3. Run a manual backup and verify checksum.
4. Generate a migration package dry run and inspect the manifest for `contains_secrets=false`.
5. Confirm queue, scheduler, and WebSocket workers restart under process supervision.
6. Confirm printer test jobs on each launch printer.
7. Confirm support contacts, incident owner, and rollback owner.

## Launch Window

1. Announce maintenance window to pilot vendors.
2. Enable maintenance mode if replacing an existing environment.
3. Run backup before update:
   ```bash
   php artisan backups:run --scope=platform
   ```
4. Deploy application image or release artifact.
5. Run database migrations:
   ```bash
   php artisan migrate --force
   ```
6. Clear and rebuild caches:
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
7. Restart PHP-FPM/app container, queue worker, scheduler, and WebSocket process.
8. Check `/health`.
9. Run the critical smoke path: login, waiter order, kitchen ready, counter payment, invoice, print job, backup creation.
10. Disable maintenance mode.
11. Monitor logs, error tracking, queue failures, WebSocket connections, and payment provider responses for at least 60 minutes.

## Rollback Triggers

- Tenant isolation or role access failure.
- Login unavailable for active users.
- Waiter-to-counter payment flow broken.
- Provider payment records unsafe or missing.
- Backup generation fails before or after deploy.
- Queue/WebSocket infrastructure cannot be restored quickly.

## Rollback Steps

1. Re-enable maintenance mode.
2. Stop workers to prevent new side effects.
3. Restore previous image/release artifact.
4. Restore database only if migrations or data changes require it, using the verified pre-update backup.
5. Run `php artisan migrate:status` and `/health`.
6. Restart workers.
7. Document incident timeline and open follow-up fixes before the next launch attempt.
