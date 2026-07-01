# AI Agent Rules

## AI workflow
1. Architect agent defines design and boundaries
2. Implementation agent codes only after design approval
3. Testing agent validates tests and integration
4. Review agent checks for security, tenant scope, and policy compliance

## Module-by-module workflow
- Work per module or domain at a time
- Design one domain before implementing
- Avoid broad cross-module changes
- Keep AI changes scoped to:
  - `Order`
  - `Kitchen`
  - `Billing`
  - `Payment`
  - `Auth`
  - `Tenant`
  - `Policies`
  - UI pages for the affected role

## Rules for AI agents
- Do not create application features until architecture is agreed
- Do not modify unrelated domains
- Always preserve multi-tenancy
- Every tenant-owned update must include `tenant_id`
- Backend policies are the authority
- Do not hardcode demo branding or currency
- Document planned changes before coding
- Each change must include tests

## Approval flow
- Architect proposes plan
- Stakeholders review and approve
- Implementation proceeds only after approval
