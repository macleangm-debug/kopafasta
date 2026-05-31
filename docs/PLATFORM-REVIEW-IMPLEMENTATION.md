# Platform Review Implementation

Structured tracking for the 24-item CopperFasta/KopaFasta platform review (May 2026).

| # | Area | Status | Notes |
|---|------|--------|-------|
| 1 | Dashboard application requirements UI | **Done** | Checklist %, progress bar, ⏳ pending icons |
| 2 | Notification system | **Done** | Bell dropdown, mark read, clear, categories field ready |
| 3 | NIDA verification UX | **Done** | Verify Identity, loading state, status cards, no CRB copy |
| 4 | NIDA name matching | **Done** | Parse first/middle/last, mismatch table, accept NIDA names |
| 5 | Verified fields locking | **Done** | identity_locked after verify |
| 6 | Face verification redesign | **Done** | 4-step flow + preview/retake before upload |
| 7 | Loan product review | **Done** | FC renamed Artisans & Craftsmen Loan; 10 products seeded |
| 8 | Loan product architecture | **Partial** | Shared wizard + product questions (EM/EL/FC) |
| 9 | Phone login prefix | **Done** | Country prefix + auto-append on login |
| 10 | Face verification approval (admin) | **Done** | Reject modal + SMS notify borrower |
| 11 | KYC module simplification | **Partial** | Nav merged label; duplicate doc reqs reduced |
| 12 | Guarantor simplification | **Partial** | Admin guarantors nav removed; loans guaranteed on borrower loans |
| 13 | Admin notifications | **Done** | Admin alert bell with pending counts |
| 14 | Loan products in Settings | **Done** | Settings → Loan Products redirect |
| 15 | Universal loan requirements | **Partial** | Guarantor all products except GL; income doc in baseline |
| 16 | Asset-backed requirements | **Done** | Vehicle photos, insurance, valuation in seeder |
| 17 | Partners module | **Partial** | Nav renamed Partners; model still Vendor |
| 18 | Partner markup config | **Partial** | DB columns partner_cost, markup_percent on vendors |
| 19 | Capital partners | **Partial** | Nav renamed; revenue share % not yet on lender form |
| 20 | Finance module redesign | **Pending** | Sidebar structure unchanged |
| 21 | Bank account configuration | **Partial** | Multiple accounts exist; fee-purpose labels in admin |
| 22 | Company signature | **Done** | Settings → Company Profile signatory + signature upload |
| 23 | Digital loan contracts | **Partial** | Borrower sign on apply; guarantor/company PDF blocks pending |
| 24 | Loan visibility | **Done** | Loans borrowed + loans guaranteed sections |

## Key files

- `app/Services/IdentityNameService.php` — NIDA name parse/compare
- `app/Services/AdminAlertService.php` — admin notification bell
- `config/loan_product_questions.php` — EM / EL / FC apply questions
- `database/seeders/PublicLoanProductsSeeder.php` — product catalogue
- `database/seeders/LoanProductRequirementSeeder.php` — universal + AB docs

## Remaining (Phase E follow-up)

- Finance sidebar restructure (Transactions, Revenue, Reports grouping)
- Lender revenue share % and interest-only split
- Partner markup UI on vendor forms
- Guarantor signature on approval + company block on PDF
- Full Vendors → Partners model/route rename
