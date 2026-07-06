# PosLAB AI Handover

This file is for the next AI agent or developer taking over the project. It captures the local setup, current state, demo data, known issues, and important implementation details.

## Project Location

Local path:

```text
C:\Users\sharin\Desktop\SOFT\POS
```

Stack:

- Laravel backend
- Vue 3 frontend mounted inside Blade pages
- Tailwind CSS
- MySQL locally through Laragon
- Redis intended for production, but local `.env` currently uses file/sync drivers
- Vite build assets

## Local Environment

The user is running the project with Laragon on Windows.

Current working local database settings in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=poslab
DB_USERNAME=root
DB_PASSWORD=
```

Local Redis was disabled because the PHP Redis extension was missing:

```env
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
REDIS_HOST=127.0.0.1
```

For local development, use:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Then open:

```text
http://127.0.0.1:8000
http://127.0.0.1:8000/login
```

Do not browse to `http://127.0.0.1:5173`; that is the Vite dev server only.

## Frontend Asset Warning

The local browser previously showed unstyled pages and blank Vue screens because of Vite hot mode and Vue runtime-only build.

Important fixes already applied:

- `vite.config.js` aliases Vue to `vue/dist/vue.esm-bundler.js` so Blade inline Vue templates can compile.
- `vite.config.js` Vite server host is set to `127.0.0.1`.
- `resources/js/app.js` unregisters service workers on localhost to avoid stale cached JS.
- The unsafe generic Vue mount on `#app` was removed.

If pages are unstyled:

1. Stop `npm run dev`.
2. Delete `public/hot`.
3. Run:

```powershell
npm run build
php artisan optimize:clear
```

For this local handover, the safest mode is built assets only:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Only use `npm run dev` after the app is stable.

## Demo Login Accounts

All demo accounts use:

```text
password
```

Accounts:

```text
platform@poslab.test   platform_owner
admin@poslab.test      admin
waiter@poslab.test     waiter
counter@poslab.test    counter
kitchen@poslab.test    kitchen
```

Role routes:

```text
platform_owner -> /platform
admin          -> /tenant/admin
waiter         -> /waiter/pos
counter        -> /counter
kitchen        -> /kitchen
```

The `/home` route has been added as a compatibility redirect to the current user's role screen.

## Demo Data

Seeders added for local demo use:

- `database/seeders/DemoUserSeeder.php`
- `database/seeders/DemoDataSeeder.php`

`DatabaseSeeder` calls:

```php
SubscriptionPlanSeeder::class
DemoTenantSeeder::class
DemoUserSeeder::class
DemoDataSeeder::class
```

Demo data includes:

- PosLAB tenant
- Main branch
- Restaurant settings for "PosLAB Demo Restaurant"
- 8 tables
- 4 menu categories
- 12 menu items
- Browser receipt and kitchen printers
- Sample table, add-on, cooking, ready, and paid takeaway orders

Rebuild database from scratch:

```powershell
php artisan migrate:fresh --seed
```

Seed demo data into an existing migrated database:

```powershell
php artisan db:seed --class=DemoUserSeeder
php artisan db:seed --class=DemoDataSeeder
```

## Main Screens To Check

Use the matching role account for each screen:

```text
Admin dashboard:  http://127.0.0.1:8000/tenant/admin
Waiter POS:       http://127.0.0.1:8000/waiter/pos
Kitchen display: http://127.0.0.1:8000/kitchen
Counter billing: http://127.0.0.1:8000/counter
Platform owner:  http://127.0.0.1:8000/platform
Health:          http://127.0.0.1:8000/health
```

## Current UX Concern

The user is unhappy with the current UI quality. The admin dashboard is functional and styled, but it still feels basic. Waiter, kitchen, and counter pages were previously blank due to the Vue issue. They now have a Vue compiler fix and simple top navigation, but they need a proper UX pass.

Recommended next UI work:

- Verify waiter, kitchen, and counter pages render after hard refresh.
- Improve navigation globally with a proper authenticated app shell.
- Add a clear "switch area" menu for users who can access multiple screens.
- Improve admin dashboard visual hierarchy.
- Make waiter POS feel like a real tablet POS, not a developer prototype.
- Make kitchen screen readable from distance.
- Add clear back/logout controls everywhere.

## Vue Mount Points

Vue is mounted inside Blade pages from `resources/js/app.js`.

Current component mount points:

```text
#waiter-pos        resources/views/areas/waiter.blade.php
#kitchen-display   resources/views/areas/kitchen.blade.php
#counter-billing   resources/views/areas/counter.blade.php
#internal-chat     resources/views/partials/internal-chat.blade.php
```

Do not reintroduce a generic Vue mount on `#app`. It caused Blade pages to be wiped/blanked.

Because templates are inline in Blade, Vue must use:

```js
vue: 'vue/dist/vue.esm-bundler.js'
```

in `vite.config.js`.

## Recent Fixes Applied

- Added `/home` route redirecting authenticated users to their role home.
- Changed `RouteServiceProvider::HOME` to `/`.
- Added demo user seeder.
- Added demo data seeder.
- Removed generic Vue `#app` mount.
- Added Vue compiler alias in Vite config.
- Disabled service worker registration on localhost and unregisters existing local service workers.
- Added navigation/logout bars to waiter, kitchen, and counter pages.
- Set Vite dev host/HMR host to `127.0.0.1`.
- Rebuilt assets with `npm run build`.

## Commands That Passed Recently

Focused affected screen tests:

```powershell
php artisan test --filter='WaiterPosScreenTest|KitchenDisplayScreenTest|CounterBillingCoreTest'
```

Result:

```text
13 passed
```

Frontend build:

```powershell
npm run build
```

Result:

```text
build passed
```

Earlier full suite:

```powershell
php artisan test
```

Result before latest UX/Vue fixes:

```text
135 passed
```

Run the full suite again after further changes.

## Known Local Troubleshooting

### Page Is Plain HTML With No Tailwind

Likely cause: `public/hot` exists and points Laravel to a Vite server that is not usable.

Fix:

```powershell
Remove-Item public/hot -Force
npm run build
php artisan optimize:clear
```

Then hard refresh browser:

```text
Ctrl + Shift + R
```

### Waiter/Kitchen/Counter Blank

Likely causes:

- Browser cached old JS through service worker.
- `public/hot` points to stale Vite server.
- Vue runtime-only build used instead of compiler build.

Check:

```powershell
Get-Content vite.config.js
Test-Path public/hot
npm run build
php artisan optimize:clear
```

Browser hard refresh:

```text
Ctrl + Shift + R
```

If still blank, open browser dev tools Console and inspect JavaScript errors.

### `/home` 404

This has been fixed by adding a `/home` route. If it returns, check `routes/web.php`.

### MySQL CLI Error

The command-line `mysql` client on this machine showed:

```text
Plugin caching_sha2_password could not be loaded
```

Laravel migrations still worked through PHP PDO, so use Laravel commands rather than relying on the MySQL CLI.

## Important Files

Routes:

```text
routes/web.php
routes/channels.php
routes/api.php
```

Auth and roles:

```text
app/Http/Controllers/Auth/AuthenticatedSessionController.php
app/Support/Roles.php
app/Models/User.php
```

Tenant context:

```text
app/Http/Middleware/SetTenantContext.php
app/Services/Tenancy/TenantContext.php
app/Services/Tenancy/TenantResolver.php
```

POS:

```text
app/Http/Controllers/Waiter/WaiterPosController.php
app/Http/Controllers/Kitchen/KitchenDisplayController.php
app/Http/Controllers/Counter/CounterBillingController.php
app/Services/Orders/OrderService.php
app/Services/Billing/BillingService.php
```

Frontend:

```text
resources/js/app.js
resources/css/app.css
vite.config.js
resources/views/areas/waiter.blade.php
resources/views/areas/kitchen.blade.php
resources/views/areas/counter.blade.php
resources/views/areas/tenant-admin.blade.php
resources/views/auth/login.blade.php
```

Seeders:

```text
database/seeders/DatabaseSeeder.php
database/seeders/DemoTenantSeeder.php
database/seeders/DemoUserSeeder.php
database/seeders/DemoDataSeeder.php
database/seeders/SubscriptionPlanSeeder.php
```

Tests:

```text
tests/Feature/WaiterPosScreenTest.php
tests/Feature/KitchenDisplayScreenTest.php
tests/Feature/CounterBillingCoreTest.php
tests/Feature/LaunchReadinessTest.php
tests/Feature/PosLabEndToEndHappyPathTest.php
```

## Suggested Next Agent Plan

1. Run the app locally with only `php artisan serve`.
2. Confirm `public/hot` does not exist.
3. Hard refresh browser and verify login, admin, waiter, kitchen, and counter pages.
4. If any screen is blank, inspect browser console first.
5. Fix UX with a shared authenticated navigation layout or partial.
6. Keep role restrictions intact.
7. Run:

```powershell
npm run build
php artisan test --filter='WaiterPosScreenTest|KitchenDisplayScreenTest|CounterBillingCoreTest'
php artisan test
```

8. Update this handover file with any new fixes or known issues.
