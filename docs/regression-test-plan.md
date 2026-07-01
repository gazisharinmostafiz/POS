# Regression Test Plan

## Automated Command Set

Run before every merge or release:

```bash
php artisan test
npm run build
npx cap sync android
```

Run focused suites while developing:

```bash
php artisan test --filter=PosLabEndToEndHappyPathTest
php artisan test --filter=SecurityHardeningTest
php artisan test --filter=AuthAccessAuditTest
php artisan test --filter=WaiterPosScreenTest
php artisan test --filter=KitchenDisplayScreenTest
php artisan test --filter=CounterBillingCoreTest
php artisan test --filter=DiscountSplitPaymentTest
php artisan test --filter=PrinterFoundationTest
```

## Coverage Map

- Tenant isolation: `TenancyFoundationTest`, `MenuManagementTest`, `CounterBillingCoreTest`, `DashboardReportsTest`, `InternalChatTest`, `SecurityHardeningTest`, `PosLabEndToEndHappyPathTest`
- Role access: `AuthAccessAuditTest`, `PlatformOwnerDashboardTest`, `KitchenDisplayScreenTest`, `DashboardReportsTest`, `UserManagementTest`, `PosLabEndToEndHappyPathTest`
- Settings save: `RestaurantSettingsTest`, `PosLabEndToEndHappyPathTest`
- Menu CRUD: `MenuManagementTest`, `PosLabEndToEndHappyPathTest`
- Waiter order create: `WaiterPosScreenTest`, `OrderDomainCoreTest`, `PosLabEndToEndHappyPathTest`
- Add-on order: `WaiterPosScreenTest`, `OrderDomainCoreTest`, `KitchenDisplayScreenTest`, `PosLabEndToEndHappyPathTest`
- Kitchen FIFO: `KitchenDisplayScreenTest`, `OrderDomainCoreTest`, `PosLabEndToEndHappyPathTest`
- Kitchen status changes: `KitchenDisplayScreenTest`, `PosLabEndToEndHappyPathTest`
- Counter billing: `CounterBillingCoreTest`, `PosLabEndToEndHappyPathTest`
- Discount calculations: `DiscountSplitPaymentTest`, `DashboardReportsTest`, `PosLabEndToEndHappyPathTest`
- Split bill calculation: `DiscountSplitPaymentTest`, `PosLabEndToEndHappyPathTest`
- Mixed cash/card payment: `DiscountSplitPaymentTest`, `PrinterFoundationTest`, `PosLabEndToEndHappyPathTest`
- Invoice generation: `PosLabEndToEndHappyPathTest`
- Chat message: `InternalChatTest`, `PosLabEndToEndHappyPathTest`
- Backup permission: `BackupSystemTest`, `SecurityHardeningTest`, `PosLabEndToEndHappyPathTest`
- Printer job creation: `PrinterFoundationTest`, `EpsonNetworkPrinterSupportTest`, `StarCloudPrntBridgeTest`, `PosLabEndToEndHappyPathTest`

## Release Regression Policy

- Use factories for local test data and seeders for shared catalog data such as subscription plans.
- Prefer backend feature tests for business behavior, tenant boundaries, roles, billing, orders, printing, and backups.
- Add UI/browser tests only where frontend behavior cannot be verified through backend contracts.
- Every production release must include a passing full backend suite and frontend build.
- Any bug fix must include a regression test in the closest feature test class or the end-to-end happy path when the bug spans modules.
