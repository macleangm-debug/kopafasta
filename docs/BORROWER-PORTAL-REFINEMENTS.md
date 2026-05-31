# Borrower Portal Review — Additional Refinements

Structured implementation note from product review. Status as of 2026-05-29.

| # | Area | Status | Notes |
|---|------|--------|-------|
| 1 | Loan qualification amount | **Done** | `LoanQualificationService`, admin **Settings → Loan Rules → Loan qualification**, dashboard hero with factor chips |
| 2 | Dashboard loan cards (horizontal swipe) | **Done** | Snap-scroll carousel on borrower dashboard |
| 3 | All 10 loan products | **Done** | `PublicLoanProductsSeeder` (IL, GL, AL, FC, KB, BP, EL, EM, WL, AB); runs on deploy |
| 4 | Notification center | **Done** | Bell + unread badge in topbar; sidebar link; `read_at` on `notification_logs` |
| 5 | Face verification status page | **Done** | Pending/approved states show submitted photo grid via `face-verification-status` component |
| 6 | NIDA capture flow banner | **Done** | Post–registration-fee onboarding banner on dashboard (NIDA → Face steps) |
| 7 | Registration middle name | **Done** | `middle_name` on customers; register + profile forms |
| 8 | NIDA integration | **Existing** | CRB verify, format validation, auto-populate + lock; NIDA photo from CRB pending live API |
| 9 | Face verification approval | **Done** | Admin queue compares 4 live photos + NIDA reference panel |
| 10 | Profile completion logic | **Done** | `ProfileCompletionService`; % on dashboard; threshold in loan settings |
| 11 | KYC freshness | **Existing** | `KycFreshnessService`, admin KYC settings, reconfirm flow |
| 12 | Application locking rules | **Done** | `ApplicationRequirementsService` checklist on dashboard; gates in `ApplyController` |

## Key services

- `App\Services\LoanQualificationService` — configurable loan limit
- `App\Services\ApplicationRequirementsService` — apply checklist + onboarding steps
- `App\Services\ProfileCompletionService` — weighted profile %

## Admin configuration

**Settings → Loan Rules → Loan qualification**

- Income multiplier, max cap, repaid-loan bonus, profile incomplete factor, min profile % to apply

**Settings → KYC**

- `require_nida`, `freshness_days` (unchanged)

## Remaining / Phase 3

- Live NIDA bureau photo storage when CRB credentials provide image payload
- Automated notification dispatch for all event types (templates exist; wire on domain events)
- WebAuthn / biometric login (placeholder on login page)

---

*Deploy: run `./scripts/deploy.sh` — migrates, seeds public products, rebuilds assets.*
