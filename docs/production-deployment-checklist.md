# Production Deployment Checklist

Every production update must create a backup first.

1. Confirm `APP_VERSION` is set for the release.
2. Confirm release notes exist for the version vendors will see after update.
3. Run `php artisan release:backup-before-update`.
4. Verify the backup completed and has a checksum.
5. Enable release maintenance with `php artisan release:maintenance on`.
6. Deploy the application code or container artifact.
7. Run `php artisan migrate --force`.
8. Clear and rebuild runtime caches as needed.
9. Verify `/health` returns `status: ok`.
10. Smoke test platform owner login, tenant admin login, waiter POS, kitchen, counter, payments, and printers.
11. Disable release maintenance with `php artisan release:maintenance off`.
12. Record the deployment outcome and backup identifier.

Do not proceed with production deployment if the pre-update backup fails.
