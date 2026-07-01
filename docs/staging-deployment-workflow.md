# Staging Deployment Workflow

1. Confirm the target branch and application version.
2. Run `composer install`, `npm ci`, `npm run build`, and `php artisan test`.
3. Deploy code to staging using the selected hosting mechanism.
4. Run `php artisan migrate --force`.
5. Confirm `/health` returns `status: ok`.
6. Log in as platform owner and review System health, Maintenance, Release notes, and Migration pages.
7. Publish or verify release notes for vendors before promoting the build.

Staging may use disposable data, but it should still exercise database migrations and the same build artifact shape used for production.
