# KopaFasta — QA Review & Required Fixes

Structured developer brief from QA review (June 2026).

| # | Area | Priority | Status |
|---|------|----------|--------|
| 1 | Loan product monthly rate (BOT + fees, no “Display Rate”) | High | Done |
| 2 | Guarantor relationship options (non-family) | High | Done |
| 3 | Guarantor step Continue validation | High | Done |
| 4 | Application auto-save / resume | Critical | Done |
| 5 | Language switch preserves wizard step | High | Done |
| 6 | Asset marketplace display fields | Medium | Done |
| 7 | Asset tenure max from asset config | High | Done |
| 8 | Asset lending workflow order | Medium | Done |
| 9 | Deposit screen + configured fees | Medium | Done |
| 10 | Internal guarantor membership + phone validation | High | Done |
| 11 | External guarantor → share link (WA/SMS/copy) | High | Done |
| 12 | Complete Profile button redirect | High | Done |
| 13 | Default interest tier seeder | Medium | Done |
| 14 | Pre-submit profile checklist gate | High | Done |

## 1. Loan product rate display

- **Remove** label “Display Rate” everywhere in borrower UI.
- **Show** “Monthly Rate” only = BOT rate + internal charges (processing + service + administration).
- Use `DisplayedRateService::breakdown()` — do not show raw `interest_rate` alone.

## 2–5. Guarantors, auto-save, language

Implemented in apply wizard, `LoanApplicationDraftService`, and locale form hook.

## 6–9. Asset lending

- Marketplace cards: asset value, deposit, **loan amount**, max tenure, weekly installment.
- Tenure cap: `effective_marketplace_asset_max_tenure()`.
- Workflow: Apply → reserve flow (viewing → interest → application fee → deposit → loan apply).
- Fees: `AssetMarketplaceFeeService` (APP_FEE from settings + post-approval charges).

## 10–14. Profile, tiers, pre-submit

- Guarantor lookup API; external share channels on success page.
- `LoanProductRateTierSeeder` for IL, EM, BP.
- Apply wizard + marketplace reserve show requirement checklist with profile links.
