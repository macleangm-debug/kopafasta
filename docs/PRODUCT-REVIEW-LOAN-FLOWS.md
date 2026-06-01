# KopaFasta Product Review — Loan Flows, Asset Lending & Platform

Structured backlog from UAT / product review (May 2026). Focus: simplify the borrower journey and behave like a modern digital lender.

**Environment:** https://kopafasta.triptz.net  
**Related:** [UAT-FULL-SYSTEM.md](./UAT-FULL-SYSTEM.md) · [TESTING-REVIEW-DEVELOPER-BRIEF.md](./TESTING-REVIEW-DEVELOPER-BRIEF.md)

---

## Priority legend

| Priority | Meaning |
|----------|---------|
| **P0** | Blocks core flow / security / duplicate data |
| **P1** | Required for production-ready experience |
| **P2** | Important enhancement |
| **P3** | Strategic / larger initiative |

| Status | Meaning |
|--------|---------|
| **Done** | Shipped |
| **Partial** | Exists; gaps remain |
| **New** | Not started |
| **In progress** | Active sprint |

---

## 1. Loan application simplification — **P0**

**Issue:** Apply wizard still collects personal, residence, kin, activity, income already in Profile/KYC.

**Required:** Remove these steps from **all** loan flows. Block apply until profile is complete; redirect to Profile.

| Task | Status |
|------|--------|
| Remove profile/income steps from `borrowerStepPlan` | Done |
| Wizard uses profile data via hidden fields only | Done |
| Readiness blocks apply → profile URLs | Done |
| Asset lending separate flow (no quote/purpose) | Partial (#7) |

---

## 2. Identity verification protection — **P0**

| Task | Status |
|------|--------|
| Attempt 1/2/3 warnings + 24h lock | Done |
| Settings → Identity Verification | Done |
| Admin unlock | Done |
| **Do not display NIDA bureau data until verified match** | Done |

---

## 3. Face verification — **P1**

| Task | Status |
|------|--------|
| Steps: front, left, right, hold NIDA | Done |
| Preview / Capture / Retake per step | Done |
| Camera auto-start (HTTPS) | Done |
| i18n step labels | Partial |

---

## 4. Referral program — **P1**

| Task | Status |
|------|--------|
| Referral hub `/borrower/referrals` | Done |
| Share + wallet rules | Done |
| **Dashboard CTA (Invite Friends & Earn)** | Done |

---

## 5. Asset marketplace cards — **P1**

Show: asset value, deposit, max tenure, weekly installment. Vendor optional/hidden.

| Task | Status |
|------|--------|
| Card fields | Done |
| Hide vendor on card | Done |
| i18n labels | Done |

---

## 6. Asset request form — **P1**

Collapsed by default: “Can’t find what you need?” → expand for description, budget, photo.

| Task | Status |
|------|--------|
| Collapsible request form | Done |
| Mobile gallery upload | Partial — verify on device |

---

## 7. Asset lending workflow — **P0 / Epic**

**Not** standard quote flow. Flow:

Marketplace → Select asset → Viewing (optional) → App fee → Deposit → Post-approval fees → Guarantor → Review → Signature → Approval.

No loan purpose or quote slider (quote from asset).

| Task | Status |
|------|--------|
| Skip quote/purpose in apply wizard for AL | Done |
| Reservation → application (prefilled asset terms) | Done |
| Full marketplace pre-approval flow (fees, viewing) | Partial |
| Dedicated asset-lending apply wizard | Partial |

---

## 8. Asset lending post-approval tracking — **P1**

Borrower portal status: GPS, insurance, registration, asset release (pending/completed).

| Task | Status |
|------|--------|
| Post-approval milestone UI | New |

---

## 9. Asset-backed loan workflow — **P1 / Epic**

Separate from asset lending (product AB): upload asset photos, insurance, valuation fee, valuer, LTV, offer.

| Task | Status |
|------|--------|
| Full AB workflow | New |

---

## 10. Asset lending entry point — **P0**

Start from **Marketplace**, not loan products list.

| Task | Status |
|------|--------|
| AL/AST redirect to marketplace | Done |
| Hide from standard apply browse | Done |

---

## 11. Public marketplace — **P2**

Browse assets on public site (SEO, acquisition).

| Task | Status |
|------|--------|
| Public read-only marketplace | New |

---

## 12. Loan product bilingual names — **P2**

Admin: English name + Swahili name per product.

| Task | Status |
|------|--------|
| `name_sw` field + display by locale | New |

---

## 13. Asset requests admin — **P1**

Borrower request → Admin Asset Requests → assign supplier → add to marketplace.

| Task | Status |
|------|--------|
| Admin queue | Done |
| End-to-end polish | Partial |

---

## 14. Notifications — **P1**

Opening dropdown marks displayed notifications read.

| Task | Status |
|------|--------|
| Auto mark read on bell open | Done |

---

## 15. Branding — **P2**

Needs assets from business: logo SVG, primary/secondary/accent, fonts.

| Task | Status |
|------|--------|
| Brand kit from client | Blocked |
| Apply across portals | Partial (`config/branding.php`) |

---

## 16. Partners module — **P1**

Rename “Add New Vendor” → “Add New Partner”. Fix 500 errors.

| Task | Status |
|------|--------|
| Unified partners admin | Partial |
| Label + error fixes | New |

---

## 17. Affiliate applications — **P2**

Public “Become an Affiliate” form → admin review.

| Task | Status |
|------|--------|
| Public apply page | New |

---

## 18–19. Asset pricing & deposit markup — **P1**

Only configure: asset value, supplier deposit, max tenure. Auto-calc weekly installment and customer deposit.

Settings → Asset Lending: markup basis (asset value vs supplier deposit).

| Task | Status |
|------|--------|
| Auto pricing in `MarketplaceAssetService` | Partial |
| Admin markup settings | New |

---

## 20. Capital partners allocation — **P3**

Round robin, proportional, priority, manual allocation models.

| Task | Status |
|------|--------|
| Allocation engine | New |

---

## 21. Default tier structures — **P2**

Seed default rate tiers for all products except advance salary.

| Task | Status |
|------|--------|
| Seeder / migration defaults | New |

---

## 22–23. CRB freshness & billing — **P1**

Reuse CRB if &lt; 90 days old. Settings: cost per request, monthly usage, reconciliation.

| Task | Status |
|------|--------|
| CRB freshness service | New |
| CRB billing settings UI | New |

---

## 24. Settings reorganization — **P2**

Group: Lending, Compliance, Partners, Branding, Finance.

| Task | Status |
|------|--------|
| Settings nav restructure | New |

---

## Recommended sprint order

1. **Now:** #1, #2, #4, #6, #10, #14 (borrower journey)
2. **Next:** #7, #8, #9 (asset flows)
3. **Then:** #12, #16, #18–19, #21–24 (admin/platform)
4. **Blocked:** #15 (brand assets from client)

---

## Round 2 — Borrower portal, asset lending & admin (May 2026)

| # | Item | Status |
|---|------|--------|
| 1 | Applications page shows all active products (sync with dashboard) | Done |
| 2 | Asset marketplace → apply creates lending application (tenure only) | Done |
| 3 | Remove viewing date from asset details; optional after app fee | Partial — viewing removed from details; post-fee scheduling in reserve flow still TODO |
| 4 | Guarantor structured fields (name, phone +255, relationship, region/district) | Done |
| 5 | WhatsApp share with approval link | Partial — post-submit WhatsApp CTA on success screen |
| 6 | Artisan loan: craft dropdown + workshop region/district/street | Partial — craft dropdown; workshop uses text fields pending tzAddress in product questions |
| 7 | Continue always advances; block submit only if KYC incomplete | Done |
| 8 | Application fee before final submit | New |
| 9 | Standardize sidebar / content margins | New |
| 10 | Internal guarantor KPF-TZ- prefix auto | Done |
| 11 | Public homepage asset marketplace | New |
| 12 | Dynamic loan product count on homepage | Done |
| 13 | Promotions scope (fees only, not interest/penalties) | New |
| 14 | Default interest tier templates | New |
| 15 | NIDA: no name override on mismatch | Done |
| 16 | KYC progress navigation (NIDA → Face → Activity → Residence → Kin) | Done |
| 17 | Residence verification camera (desktop/mobile) | New |
| 18 | Next of kin structured fields + relationship dropdown | Partial — guarantor done; kin profile form TODO |
| 19 | Verification documents page clarity | New |
| 20 | Profile page member design (photo, read-only sections) | New |
| 21 | Admin partners rename + fix 500 errors | New |
| 22 | Asset pricing auto-calculation | New |
| 23 | Capital partner allocation models | New |
| 24 | CRB freshness + billing settings | New |

---

*Update status columns as items ship.*
