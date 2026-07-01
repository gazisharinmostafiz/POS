# Production Deployment

Production PosLAB must run on VPS/cloud infrastructure with workers, scheduler, Redis, database, and WebSocket support. Shared hosting is not suitable for production SaaS.

## Required Services

- Nginx reverse proxy.
- PHP-FPM application container.
- Queue worker: `php artisan queue:work redis --sleep=3 --tries=3 --timeout=120 --max-time=3600`.
- Scheduler process: runs `php artisan schedule:run` every minute.
- PostgreSQL database.
- Redis for cache, sessions, queues, and optional broadcasting.
- WebSocket provider: Pusher, Soketi, or Reverb.
- Private backup storage and optional remote backup destination.
- Monitoring and error tracking.

## Deployment Assets

- `deployment/production/Dockerfile`: production PHP-FPM app image.
- `deployment/production/Nginx.Dockerfile`: Nginx image with built public assets.
- `docker-compose.production.yml`: production-style Compose stack.
- `deployment/nginx/poslab.conf`: Nginx reverse proxy and PHP-FPM config.
- `deployment/cron/laravel-scheduler`: cron line for non-container scheduler setups.
- `deployment/scripts/deploy-production.sh`: deployment command sequence.
- `deployment/scripts/healthcheck.sh`: health endpoint check.

## Environment and Secrets

Secrets must come from environment variables or a secret manager. Do not commit real values.

Minimum production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`
- `APP_URL=https://your-domain.example`
- `TENANCY_ROOT_DOMAIN=your-domain.example`
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis`
- `BROADCAST_DRIVER=pusher`
- Broadcast credentials for Pusher/Soketi/Reverb
- `BACKUP_ENCRYPTION_KEY`
- Stripe/Teya/Worldpay secrets and webhook secrets
- `MAIL_*` production SMTP settings
- Optional `ERROR_TRACKING_DSN`

## SSL and HTTPS

Terminate SSL at Cloudflare, a host load balancer, or Nginx. Use one of:

- Cloudflare Full Strict with an origin certificate on the VPS.
- Let's Encrypt certificate mounted into `nginx_certs`.
- Managed load balancer certificate in front of the VM.

Set:

- `APP_URL=https://...`
- `SESSION_SECURE_COOKIE=true` if added to config.
- `VITE_PUSHER_SCHEME=https`
- `PUSHER_SCHEME=https`

Redirect HTTP to HTTPS at the edge or Nginx when certificates are configured.

## Cloudflare DNS and Security

- Use proxied `A` or `CNAME` records for app domains.
- Use Full Strict SSL mode.
- Enable WAF managed rules.
- Enable bot protection/rate limiting for `/login`, `/api/webhooks/*`, and printer bridge endpoints where appropriate.
- Keep `/health` accessible to uptime monitors, but do not expose sensitive details.
- Set DNS records for tenant subdomains or wildcard tenant routing if using subdomain tenancy.
- Do not cache authenticated app pages. Cache only static assets.

## Database and Redis

For production SaaS, managed PostgreSQL and managed Redis are preferred. If running in Compose:

- Use persistent volumes.
- Restrict database and Redis ports to the Docker network or firewall.
- Use strong database and Redis passwords.
- Back up PostgreSQL outside the app-level backup as well.

## Queue, Scheduler, and WebSockets

The Compose file includes:

- `queue` service for Redis queue jobs.
- `scheduler` service that runs scheduler every minute.
- Optional `websocket` service under the `reverb` profile.

If using external Pusher or Soketi, do not start the Reverb profile. Configure the Pusher-compatible environment variables instead.

## Backup Cron

The Laravel scheduler already runs `backups:run` daily. Ensure the scheduler service is always running:

```bash
docker compose -f docker-compose.production.yml ps scheduler
```

For non-container servers, install:

```cron
* * * * * cd /var/www/html && php artisan schedule:run --no-interaction >> /dev/null 2>&1
```

Use encrypted backups with `BACKUP_ENCRYPTION_KEY`. Configure remote backup storage before relying on app-level backups for disaster recovery.

## Monitoring and Error Tracking

Minimum:

- Uptime monitor against `/health`.
- Disk usage alerts for app storage, database volume, and backup volume.
- CPU/RAM alerts on queue, database, Redis, and web containers.
- Log aggregation for Laravel logs and Nginx logs.
- Error tracking provider, for example Sentry or Bugsnag, wired through environment variables and a package when selected.
- Alert on queue failures and failed backup jobs.

## Deployment Checklist

1. Confirm branch, tag, and `APP_VERSION`.
2. Confirm `.env` values are present on the server and no secrets are committed.
3. Run `php artisan test` and `npm run build` in CI.
4. Build production images.
5. Run `php artisan release:backup-before-update`.
6. Enable maintenance: `php artisan release:maintenance on`.
7. Deploy containers.
8. Run `php artisan migrate --force`.
9. Run config/route/view/event cache commands.
10. Restart queue workers with `php artisan queue:restart`.
11. Verify `/health`.
12. Verify login, tenant isolation, waiter order, kitchen ready, counter payment, backup, and print job queue.
13. Disable maintenance: `php artisan release:maintenance off`.
14. Publish vendor release notes.
15. Monitor logs, queues, WebSockets, and payment webhooks for at least 30 minutes.
