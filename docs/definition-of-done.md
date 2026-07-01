# Definition of Done

## Completion criteria
- Feature is implemented only after design approval
- All tenant-scoped data includes `tenant_id`
- Role-based access enforced in backend and reflected in frontend
- No hardcoded restaurant branding or demo-only values
- Business rules are implemented correctly
- Kitchen FIFO uses creation time
- Split bills are by number of people
- Payments support cash, card, and mixed payments
- No sensitive card data stored

## Testing
- Automated tests cover the new behavior
- Regression tests for tenant isolation and roles
- Security-related tests included where applicable
- Tests pass in the local environment

## Review
- Code review completed
- Policies and services reviewed for tenant safety
- Documentation updated for the change
- No unrelated files modified

## Release readiness
- Branch name follows conventions
- PR description references docs and test coverage
- Environment checklist validated
