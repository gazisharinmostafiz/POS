# Rollback Guide

1. Enable release maintenance with `php artisan release:maintenance on --message="Rollback in progress."`
2. Identify the last known good application version and deployment artifact.
3. Confirm the pre-update backup checksum from the failed release.
4. Restore code or container artifact to the last known good version.
5. Review database migration impact before rolling back data. Laravel down migrations may be destructive; prefer restoring from the verified backup when schema/data cannot be safely reversed.
6. Run required cache rebuild commands for the restored version.
7. Verify `/health` returns `status: ok`.
8. Smoke test login, tenant access, order flow, payment flow, printer queue, backups, and reports.
9. Disable release maintenance with `php artisan release:maintenance off --message="Rollback complete."`
10. Publish a vendor release note if the rollback changes vendor-visible behavior.

Rollback must never expose secrets. Use `.env.example` as a checklist only and load real credentials from the deployment environment.
