# ACL & loan application workflow

This document describes permission-based access control and the admin loan application workflow introduced after the platform review.

## Permission system

Permissions are defined in `config/permissions.php` and stored on `roles.permissions` (JSON array).

Runtime checks go through:

- `App\Services\PermissionService` — `has()`, `hasAny()`, `forUser()`
- `User::hasPermission()` — convenience wrapper
- Laravel `Gate` — one gate per permission key
- Blade — `@perm('applications.view')` and `@permany('applications.view', 'applications.edit')`

**Admin bypass:** users with role `admin` or `super_admin` receive all permissions automatically.

**Fallback:** if no row exists in `roles` for a user's role, defaults from `config/permissions.php` → `defaults` are used.

### Default roles (seeded)

| Role | Typical use |
|------|-------------|
| `officer` | Acknowledge, screen, request documents |
| `manager` | Full application workflow through disbursement |
| `credit_analyst` | Credit review and document requests |
| `admin` / `super_admin` | Full access |

Seed roles on deploy or manually:

```bash
php artisan db:seed --class=RoleSeeder
```

## Admin navigation

Sidebar sections are hidden when the user lacks any required permission for that section:

| Section | Required permission (any) |
|---------|---------------------------|
| Applications | `applications.view` |
| Customers | `customers.view`, `kyc.review`, `membership.approve_payments` |
| Loans | `loans.view` |
| Loan Products | `settings.manage` |
| Compliance | `audit.view` |
| Settings | `settings.manage` |

Other sections remain visible to all authenticated admin users until finer permissions are added.

## Loan application workflow

Stages: **submitted → screening → credit_appraisal → pre_approval → approval → disbursement** (or **rejected**).

Actions are defined in `LoanApplicationWorkflowService::ACTIONS` and exposed on the admin application **show** page.

| Action | From stage(s) | To stage | Permission |
|--------|---------------|----------|------------|
| Acknowledge receipt | submitted | screening | `applications.acknowledge` |
| Complete screening | screening | credit_appraisal | `applications.review` |
| Send to pre-approval | credit_appraisal | pre_approval | `applications.pre_approve` |
| Final approve | pre_approval | approval | `applications.approve` |
| Mark ready for disbursement | approval | disbursement | `applications.disburse` |
| Reject | submitted … approval | rejected | `applications.reject` |

Each transition:

1. Validates stage and permission
2. Writes `application_stage_histories`
3. Writes `audit_logs` via `AuditService`
4. Updates `current_stage` and related status fields

**API:** `POST /api/loan-applications/{id}/transition` uses the same service via `transitionToStage()`.

**Admin UI:** `POST admin/loan-applications/{id}/workflow` with `action` and optional `remarks` (required for reject).

## Managing roles

Admin → Settings → Roles & Permissions. The form shows a grouped checkbox list from the permission catalog. Changes are saved to `roles.permissions` and take effect on next request (cached per request in `PermissionService`).

## Branch scoping

`LoanApplicationPolicy` still enforces branch matching for non-admin users. Workflow actions additionally respect approval limits inside `LoanApplicationWorkflowService` where configured.
