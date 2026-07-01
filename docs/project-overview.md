# Project Overview

## What PosLAB is
PosLAB is a multi-tenant restaurant POS SaaS platform. Each restaurant tenant operates in isolation, and the platform supports multiple restaurants in the same application instance.

## Multi-tenant rules
- Every restaurant-owned record must include `tenant_id`
- Tenant ownership is enforced in database schema, models, services, and policies
- Demo branding is only example data; the codebase must support any restaurant identity
- No hardcoded restaurant, menu, or currency values in application logic

## Roles
- `super-admin`
- `admin`
- `manager`
- `counter`
- `waiter`
- `kitchen`
- `customer` (if applicable)

Each role must have explicit authorization rules for its permitted actions.

## Key domain modules
- `Order`
- `Kitchen`
- `Billing`
- `Invoice`
- `Payment`
- `Printer`
- `Subscription`
- `Auth`
- `Menu`
- `Reports`
- `Tenant`

## Security baseline
- Do not store card numbers, CVV, or expiry dates
- Only store non-sensitive payment metadata and transaction references
- Protect tenant boundaries at database, service, and policy layers
- Use role policies in backend as the authoritative enforcement point
