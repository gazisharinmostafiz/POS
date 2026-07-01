# Tong POS Platform

Base Laravel application stack for the Tong POS platform. This repository currently contains only the application foundation; POS domain features have not been built yet.

## Stack

- Laravel 9
- Vue 3
- Tailwind CSS
- Vite
- PostgreSQL
- Redis
- Docker Compose

## Local Setup

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
npm run build
php artisan test
php artisan serve
```

The app serves the landing page at `http://localhost:8000` and exposes `GET /health`.

## Docker Setup

```bash
copy .env.example .env
php artisan key:generate
docker compose up --build
```

Docker Compose starts the Laravel app, PostgreSQL, and Redis. Local credentials in `.env.example` are development defaults only; production secrets must be supplied by the deployment environment.

## Documentation

- `docs/project-overview.md`
- `docs/development-rules.md`
- `docs/ai-agent-rules.md`
- `docs/definition-of-done.md`
- `docs/environment-checklist.md`
# POS
