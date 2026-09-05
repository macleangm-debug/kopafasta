# KopaFasta Review – Phase 4 Implementation

Post-approval fees, tiered rates, referrals, asset marketplace, partner settlement, and guarantor workflow consolidation.

**Status key:** Done | Partial | Planned

---

## 1. Post-Approval Fees Module — Partial

**Trigger:** Loan approved → offer letter accepted → borrower signature → guarantor signature.

**Borrower next step:** Pay post-approval fees before disbursement prep.

**Admin path:** Settings → Loan Products → Edit → Post-Approval Fees

| Product type | Recommended fees |
|--------------|------------------|
| Individual | Disbursement, documentation, loan agreement |
| Asset lending | Comprehensive insurance, GPS device, GPS installation, asset registration, documentation |
| Asset-backed | Asset valuation, documentation, registration |
| Group | Group registration, documentation |

Each fee supports **fixed amount** or **percentage of approved principal**.

**Implementation:**
- Table: `loan_product_post_approval_fees`
- Table: `loan_application_post_approval_fees` (generated at approval)
- Service: `PostApprovalFeeService`
- Borrower route: `site.borrower.application.post-approval-fees`
- Admin: product edit form section

---

## 2. Tiered Monthly Rates — Partial

Higher loan amount → lower monthly rate. Configured per product.

**Example (Individual Loan):**

| Amount band | Monthly rate |
|-------------|--------------|
| TZS 100,000 – 500,000 | 15% |
| TZS 500,001 – 2,000,000 | 12% |
| TZS 2,000,001 – 5,000,000 | 10% |

**Implementation:**
- Table: `loan_product_rate_tiers`
- Service: `LoanRateTierService::resolveRate($product, $amount)`
- Used in apply wizard EMI preview, offer letter, schedule generation

---

## 3. Guarantor Requests Navigation — Done

Remove standalone **Guarantor requests** nav item.

**Loans** page sections:
1. Loans borrowed
2. Loans guaranteed
3. Pending guarantor requests

Legacy route `/borrower/guarantor-requests` redirects to `/borrower/loans#guarantor-requests`.

---

## 4. External Guarantor Workflow — Partial

**Settings → Company Profile → App base URL** (e.g. `https://www.kopafasta.co.tz`)

Generated link: `{base_url}/guarantor/{token}` — sent via SMS/email on external invite.

**Flow (external):**
1. Approve or reject (guest-accessible)
2. Register / login → session tracks invitation
3. Membership fee → identity verification → profile completion
4. Guarantor onboarding confirmation → `CustomerGuarantor` approved

**Remaining:** Guarantor signature on loan agreement for externals.

---

## 5. Proof of Income — Partial

**Profile → Activity information**

Required (one of):
- Bank statement (6 months)
- Mobile money statement (6 months)

Refresh cycle: **90 days** (KYC freshness setting).

**Implementation:** Wire `require_income_proof` KYC setting into `ApplicationRequirementsService` and activity profile checklist.

---

## 6. Referral System — Partial

Each member receives:
- **Referral code:** `KPF-MAGORI001`
- **Referral link:** `{app_url}/register/borrower?ref=KPF-MAGORI001`

**Referral wallet:**
- Credits from referral commissions
- Usable for: registration fees, application fees, post-approval fees
- Not usable for: loan repayments, interest, penalties
- Max wallet usage per fee: **50%** (configurable)

**Checkout (4B):** Registration fee and post-approval fees support referral discount + wallet checkbox. Application fee discount applied at disbursement.

---

## 7. Referral Rewards — Partial

Defaults (configurable in **Settings → Referrals**):

| Setting | Default |
|---------|---------|
| Referral discount | 10% |
| Referral commission | 10% |

Example: Registration fee TZS 10,000 → customer pays 9,000, referrer earns 900.

**Implementation:** `ReferralService::quoteFee()` / `settleFee()`, admin settings page, checkout on membership renew and post-approval fees.

---

## 8. Affiliate Partner Program — Partial

Partner type: **Affiliate** (individual or company) via `vendors.category = affiliate`.

Tracks: clicks, registrations, applications via `affiliate_events`. Commission accrues as pending `vendor_payments` on registration, post-approval, and application fees (referral takes precedence).

Separate discount rules for registration and application fees on vendor record.

**Routes:** `/aff/{code}` → registration with `?aff=`, admin Partners → Affiliates tab.

---

## 9. Asset Marketplace — Partial

Borrower portal **Marketplace** with DB-backed assets when seeded.

**Admin:** Settings area → Marketplace Assets CRUD.

---

## 10. Asset Request Feature — Partial

Borrower form + admin queue at `/admin/asset-requests` with supplier assignment and notification.

---

## 11. Supplier Portal — Partial

Partner type: **Supplier** — `/supplier` portal for assets, reservations, requests, settlements.

Admin can upload via Marketplace Assets on behalf of supplier (`vendor_id`).

---

## 12. KopaFasta Deposit Markup — Partial

`deposit_markup_percent` on vendors and marketplace assets. Admin vendor form + auto-calculated `customer_deposit`.

---

## 13. Asset Lending Workflow — Partial

Persisted `asset_reservations` state machine: viewing → reservation fee → viewing complete → deposit → apply for asset loan.

Borrower reservation flow at `/borrower/marketplace/{asset}/reserve`.

---

## 17. GPS Pricing Model — Partial

`GpsPricingService` + `config/gps_pricing.php` — device + monitoring × tenure + markup estimate.

---

## 14. Asset Ownership Model — Partial

KopaFasta remains legal owner during financing. Transfer after full repayment, no outstanding charges, transfer fee paid.

Included in offer letter and loan contract PDFs for asset product code `AL` via `LoanAgreementService` snapshot.

---

## 15. Supplier Settlement Model — Partial

Borrower repayment → KopaFasta receives → supplier settlement queue.

Payment states: **Pending → Approved → Paid**. Weekly batching via `partners:queue-weekly-settlements` (Fridays 08:00).

**Admin:** Finance → Partner payments, Partner settlements. Supplier deposit payouts accrue when borrower deposit is marked paid.

---

## 16. GPS Partner Workflow — Partial

GPS partner task statuses: Pending → In Progress → Completed.

Only completed tasks eligible for payment. Uses existing `vendor_tasks` with type `gps_install`.

---

## 17. GPS Pricing Model — Partial

Components: device cost (fixed) + monitoring fee (monthly × loan duration) + KopaFasta markup.

Bundled into post-approval fee rows when fee code matches `config/gps_pricing.fee_codes` or `fee_type = gps`.

---

## 18. Partner Settlement Logic — Partial

Partners: suppliers, GPS providers, insurance, valuers, affiliates.

Payment states: **Pending → Approved → Paid** on `vendor_payments`. Batched into `partner_settlements` weekly.

Settlement only after task completion (or commission accrual) + admin payment approval.

---

## Implementation phases

| Phase | Focus | Items |
|-------|-------|-------|
| **4A** (current) | Foundation | Fees, tiers, guarantor nav, guest links, referrals wallet, marketplace DB, asset requests |
| **4B** (current) | Growth | Referral checkout, wallet deduction, admin referrals settings, external guarantor onboarding |
| **4C** (current) | Assets | Supplier portal, deposit markup, asset reservations workflow, affiliate program, GPS pricing |
| **4D** (current) | Finance | Partner settlement automation, weekly supplier payouts, affiliate commission payouts |

---

## Key files

| Area | Path |
|------|------|
| Post-approval fees | `app/Services/PostApprovalFeeService.php` |
| Rate tiers | `app/Services/LoanRateTierService.php` |
| Referrals | `app/Services/ReferralService.php`, `config/referrals.php` |
| Partner settlements | `app/Services/PartnerSettlementService.php`, `app/Console/Commands/QueuePartnerSettlements.php` |
| Affiliates | `app/Services/AffiliateService.php`, `config/affiliates.php` |
| GPS pricing | `app/Services/GpsPricingService.php`, `config/gps_pricing.php` |
| Marketplace | `app/Models/MarketplaceAsset.php`, `AssetMarketplaceController` |
| Migration | `database/migrations/2026_05_31_100000_phase4_platform_foundation.php` |
| Guarantor links | `GuarantorInvitationService::invitationUrl()` |
| Loans (guarantor) | `resources/views/site/borrower/loans.blade.php` |

---

## Branding note

User-facing copy uses **KopaFasta**. System config defaults remain **Kopafasta** via `config/branding.php` until marketing rebrand is complete.
