# ACL & loan application workflow

This document describes permission-based access control, the role matrix, and the admin loan application workflow.

## Role matrix

Canonical definitions live in `config/roles.php` and are accessed via `App\Services\RoleService`.

| Role | Console login | Typical use |
|------|---------------|-------------|
| `admin` | Yes | Full access — hardcoded permission and policy bypass |
| `super_admin` | Yes | Console access; permissions from `roles` table; **not** treated like `admin` in policies (branch scoping applies) |
| `manager` | Yes | Approve transitions, disbursement, restructures (within limits) |
| `officer` | Yes | View/edit applications, request documents — **no** workflow stage transitions |
| `collector` | No | API only — repayments and arrears |
| `credit_analyst` | No | API only — underwriting via policies/permissions |
| `agent` | No | Support tickets (API) |
| `auditor` | No | Audit log access (API); appears in admin users **filter** only |
| `borrower` / `customer` | Portal | Borrower portal |
| `vendor` | Portal | Vendor portal |
| `investor` | Portal | Investor portal |

### Admin user form roles

Settings → Users allows assigning only console roles: `admin`, `super_admin`, `manager`, `officer`.

Staff roles such as `collector`, `credit_analyst`, and `agent` are assigned via the API (`POST /api/system/users/{id}/assign-role`).

### API capability groups

API middleware accepts capability tokens defined in `config/roles.php` → `api_capabilities`:

| Capability | Roles |
|------------|-------|
| `core` | officer, manager, admin, super_admin, credit_analyst |
| `collections` | officer, manager, admin, super_admin, collector |
| `reports` | officer, manager, admin, super_admin |
| `system` | manager, admin, super_admin |
| `security` | **admin only** |
| `audit` | auditor, admin, super_admin |
| `support` | agent, manager, admin, super_admin |
| `portal` | customer, borrower |

## Permission system

Permissions are defined in `config/permissions.php` and stored on `roles.permissions` (JSON array).

Runtime checks go through:

- `App\Services\PermissionService` — `has()`, `hasAny()`, `forUser()`
- `User::hasPermission()` — convenience wrapper
- Laravel `Gate` — one gate per permission key
- Blade — `@perm('applications.view')` and `@permany('applications.view', 'applications.edit')`

**Admin bypass:** only role `admin` receives automatic permission bypass.

**Super admin:** all permissions are seeded on the `super_admin` role row, but checks run through `PermissionService` (no bypass).

**Fallback:** if no row exists in `roles` for a user's role, defaults from `config/permissions.php` → `defaults` are used.

Seed roles on deploy or manually (all 12 roles from `config/roles.php`):

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
| Finance | `finance.accounts`, `finance.methods`, `finance.operations` |
| Reports | `reports.view`, `finance.reports` |
| Compliance | `audit.view` |
| Settings | `settings.manage`, `users.view`, `users.manage` |

Within **Finance** and **Reports**, individual tabs are filtered by the specific permission on each menu item (see below).

### Finance & reports permissions

Configure these under **Settings → Roles & Permissions** in the **Finance** and **Reports & analytics** modules:

| Permission | Controls |
|------------|----------|
| `finance.accounts` | Chart of accounts, bank accounts, mobile money accounts |
| `finance.methods` | Disbursement/repayment methods, charges & fees, write-off rules |
| `finance.operations` | Expenses, settlements, reconciliations, journal entries |
| `finance.reports` | Trial balance, income statement, balance sheet, cash flow, NPL, financial overview |
| `reports.view` | Portfolio, disbursements, repayments, arrears, PAR, vendor performance, customer reports |

**Default role access:**

| Role | Finance / reports |
|------|-------------------|
| `manager` | All finance + reports permissions |
| `officer` | `reports.view` only |
| `auditor` | `finance.reports`, `reports.view`, `audit.view` |
| `collector` | `reports.view` |
| `admin` | All (bypass) |

Other sections remain visible to all authenticated console users until finer permissions are added.

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

**Officers** do not receive workflow transition permissions by default.

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

Only role `admin` bypasses branch checks in policies (`StaffAccess` trait). All other staff — including `super_admin` — are branch-scoped when `branch_id` is set.

Workflow actions additionally respect approval limits inside `LoanApplicationWorkflowService` where configured.
