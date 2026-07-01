# Environment Checklist

## Local setup
- PHP and Composer installed
- Node.js and npm/yarn installed
- Database server configured and reachable
- `.env` copied from `.env.example`

## Required environment variables
- `APP_ENV`
- `APP_KEY`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SESSION_DRIVER`
- `QUEUE_CONNECTION`
- `BROADCAST_DRIVER`

## Startup
- Run database migrations
- Run seeders for tenant/demo data
- Start backend server
- Start frontend asset compilation
- Confirm the app loads for a tenant-aware login flow

## Testing
- Run unit tests
- Run feature tests
- Confirm no failing tests
- Confirm tenant isolation tests execute

## Security checks
- No secrets committed in source
- No hardcoded demo branding in code
- No sensitive payment data stored
- Validate role policy enforcement paths

## Documentation
- New features referenced in docs
- Contribution rules understood
- Branch naming and PR rules followed
