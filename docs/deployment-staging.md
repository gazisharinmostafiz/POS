# Staging Deployment

Staging should mirror production closely enough to test workers, scheduler, Redis, database migrations, and WebSockets. Shared hosting is acceptable only for a static demo or early preview, not for full POS staging.

## Recommended Host

- VPS or cloud VM with Docker and Docker Compose.
- 2 CPU / 4 GB RAM minimum for small staging.
- PostgreSQL and Redis can run in Compose for staging.
- Use a real staging domain, for example `staging.poslab.example.com`.

## Environment

Create `.env` from `.env.example` and set staging values:

- `APP_ENV=staging`
- `APP_DEBUG=false`
- `APP_URL=https://staging.example.com`
- `APP_KEY`
- `DB_CONNECTION=pgsql`
- `DB_HOST=database`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis`
- `REDIS_HOST=redis`
- `BROADCAST_DRIVER=pusher`
- Pusher/Soketi/Reverb-compatible broadcast keys
- `BACKUP_ENCRYPTION_KEY`
- Provider credentials and webhook secrets from environment variables only

## Compose Startup

```bash
docker compose -f docker-compose.production.yml build
docker compose -f docker-compose.production.yml up -d database redis app web queue scheduler
docker compose -f docker-compose.production.yml exec app php artisan migrate --force
docker compose -f docker-compose.production.yml exec app php artisan config:cache
docker compose -f docker-compose.production.yml exec app php artisan route:cache
docker compose -f docker-compose.production.yml exec app php artisan view:cache
```

## WebSockets

The app currently uses Pusher-compatible broadcasting. Use one of:

- External Pusher account.
- Self-hosted Soketi and set `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`.
- Laravel Reverb after adding/configuring the package, then start the Compose `websocket` service with `--profile reverb`.

```bash
docker compose -f docker-compose.production.yml --profile reverb up -d websocket
```

## Checks

- `/health` returns `status: ok`.
- Login works for platform owner and tenant users.
- Queue worker is running: `docker compose ps queue`.
- Scheduler is running: `docker compose logs scheduler`.
- WebSocket updates work from waiter to kitchen/counter.
- Backups can be created and downloaded only by authorized users.
- Printer jobs are queued without blocking order creation.

## Staging Data

Seed demo data only in staging:

```bash
docker compose -f docker-compose.production.yml exec app php artisan db:seed --force
```

Do not copy production provider secrets into staging unless explicitly approved and isolated.
