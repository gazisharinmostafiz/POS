# Development Rules

## Branch naming
- `feature/<short-description>`
- `fix/<short-description>`
- `chore/<short-description>`
- `docs/<short-description>`
- `release/<version>`

## Code hygiene
- Do not modify unrelated modules
- Keep changes modular and isolated to the target domain
- Avoid cross-cutting changes unless required and documented
- Do not hardcode restaurant names, demo values, or Pounds Sterling in application logic

## Tenant requirements
- All tenant-scoped tables must include `tenant_id`
- All queries and services must filter by current tenant
- Policies must deny access across tenants
- No tenant data leakage is permitted

## Role enforcement
- Backend policies are authoritative
- Frontend gating is supportive only
- Each UI page must be protected by role and tenant scope

## Testing
- Every feature change must include automated tests
- Tests must cover tenant isolation, role authorization, business rules, and security constraints
- Prefer feature tests for end-to-end flow and unit tests for service logic

## Security baseline
- Sensitive card data must never be stored
- Use transaction references instead of raw card details
- Validate payment totals strictly
- Protect all tenant-scoped endpoints
