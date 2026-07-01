# Manual QA Checklist

Run this checklist on staging before production release.

## Access and Tenant Context

- Log in as `platform_owner` and confirm platform pages load.
- Log in as tenant `admin`, `waiter`, `counter`, and `kitchen`.
- Confirm waiter cannot open tenant admin, counter, or kitchen-only pages.
- Confirm kitchen cannot open counter billing or tenant admin pages.
- Confirm tenant A users cannot see tenant B menu, orders, reports, backups, chat, or printers.

## Restaurant Setup

- Save restaurant settings with name, address, currency, service charge, VAT, table count, invoice footer, and theme color.
- Confirm table count creates the expected active table list.
- Upload a valid logo image and reject invalid file types.

## Menu

- Create, edit, hide, unhide, and sort categories.
- Create, edit, hide, unhide, and toggle availability for menu items.
- Confirm hidden or unavailable items do not appear in Waiter POS.
- Confirm delete is available only to `super_admin` or `platform_owner`.

## Waiter POS

- Select a table and create an order.
- Create takeaway and walk-in orders.
- Add items by tapping the item card and plus button.
- Confirm search text stays while typing and category changes do not clear search.
- Add a kitchen note and send to kitchen.
- Create an add-on order for an occupied table.

## Kitchen

- Confirm tickets appear in FIFO order.
- Confirm add-on tickets do not jump ahead.
- Move a ticket from New to Cooking to Ready.
- Confirm kitchen cannot see payment data.

## Counter and Billing

- Open active orders and apply filters.
- Combine unpaid table tickets into one bill.
- Apply flat and percentage discounts.
- Split a bill by number of people and verify rounding.
- Record cash, card, and mixed cash/card payments.
- Confirm overpaid cash calculates change.
- Confirm receipt print jobs are queued.
- Generate/check invoice artifact where enabled.

## Chat

- Send waiter to kitchen, waiter to counter, and counter to kitchen messages.
- Confirm sender name, role, timestamp, history after refresh, and unread count.

## Backups and Printing

- Create a tenant backup when plan allows it.
- Confirm unauthorized users cannot download another tenant backup.
- Create printer settings, test print job, receipt print job, and kitchen ticket print job.
- Retry a failed print job.

## PWA/APK Smoke

- Install the PWA in Chrome and confirm standalone launch.
- Open APK shell against staging backend.
- Confirm waiter, kitchen, and counter screens remain usable on tablet and phone viewports.
