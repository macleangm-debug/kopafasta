# KopaFasta Testing Review — Developer Brief

Structured review from UAT / product testing (Borrower Portal, Marketplace, Admin & Configuration).

**Environment:** https://kopafasta.triptz.net  
**Related:** [UAT-FULL-SYSTEM.md](./UAT-FULL-SYSTEM.md) · [PHASE2-BORROWER-PORTAL.md](./PHASE2-BORROWER-PORTAL.md) · [PHASE4-KOPAFASTA-PLATFORM.md](./PHASE4-KOPAFASTA-PLATFORM.md)

---

## Priority legend

| Priority | Meaning |
|----------|---------|
| **P0** | Broken in production / security / blocks core flow |
| **P1** | Required for production-ready borrower experience |
| **P2** | Important enhancement; can ship in next sprint |
| **P3** | Strategic / larger initiative |

| Status | Meaning |
|--------|---------|
| **Done** | Largely implemented |
| **Partial** | Exists but incomplete vs spec |
| **Bug** | Intended feature broken |
| **New** | Not implemented |

---

## Executive summary

The borrower dashboard and marketplace are materially improved. Remaining work clusters into:

1. **Fix now (P0):** Face verification camera, admin loan product fee/tier add buttons, apply wizard duplicating profile data.
2. **Profile & KYC (P1):** Next of kin in completion banner, KYC freshness (90-day) with selective re-verification, NIDA attempt lockout, profile page redesign.
3. **Growth & visibility (P1):** Dedicated referral hub with share actions and wallet rules.
4. **Marketplace & asset lending (P1–P2):** Asset detail fields, filters, flow rename (Apply for Asset), tenure from product.
5. **Admin & platform (P2–P3):** Unified partners module, nav moves, BOT rate display, i18n, campaigns, finance clarity, branch simplification.

---

## 1. Borrower dashboard & profile completion

**Reviewer feedback:** Dashboard looks significantly better. Profile completion works but needs Next of Kin and KYC freshness rules.

| # | Requirement | Status | Priority | Acceptance criteria |
|---|-------------|--------|----------|---------------------|
| 1.1 | **Next of Kin** in profile completion: Full Name, Relationship, Phone | Partial | P1 | Fields required until complete; hidden from banner when done |
| 1.2 | Next of kin reappears only on **KYC freshness expiry** | New | P1 | Freshness service flags kin only if policy requires (default: not on 90-day refresh unless configured) |
| 1.3 | **KYC freshness: 90 days** | Partial | P1 | Admin setting; after expiry request: proof of income, residence letter, activity info |
| 1.4 | **Identity verification not repeated** on freshness refresh unless admin flag | Partial | P1 | NIDA + face remain valid across refresh cycles |

**Current code**
- `ProfileCompletionService` already scores kin via `nok_name`, `nok_phone`, `nok_relationship` but **does not show kin in `displaySections()`** (dashboard banner).
- `KycFreshnessService` exists; wire expiry → selective section re-open.

**Suggested tasks**
- [ ] Add kin to dashboard completion banner + borrower profile kin section (editable).
- [ ] Extend `KycFreshnessService` with 90-day default and per-section refresh map.
- [ ] Admin: Settings → KYC → freshness days + which sections refresh.

---

## 2. Referral program visibility

**Reviewer feedback:** Need a dedicated, highly visible referral area.

| # | Requirement | Status | Priority | Acceptance criteria |
|---|-------------|--------|----------|---------------------|
| 2.1 | Show **referral code** (e.g. `KPF-MAGORI001`) | Partial | P1 | Prominent on membership/referrals page |
| 2.2 | Show **referral link** with share: WhatsApp, SMS, Facebook, Copy | New | P1 | One-tap share + copy toast |
| 2.3 | Show **referral wallet balance** (e.g. TZS 15,000) | Partial | P1 | Large balance display |
| 2.4 | Explain wallet **can** pay: application fees, post-approval fees | Partial | P1 | Clear allowed uses |
| 2.5 | Explain wallet **cannot** pay: repayments, interest, penalties | Partial | P1 | Clear blocked uses |

**Current code**
- Referral link/code on `site/borrower/membership.blade.php` (text only, not a dedicated hub).
- Wallet rules in `ReferralService` + config; UI not prominent.

**Suggested tasks**
- [ ] New route: `/borrower/referrals` (or membership tab) with card layout.
- [ ] Share component using `navigator.share` + fallback links (`wa.me`, `sms:`, Facebook sharer).
- [ ] Wallet rules panel (static copy from settings).

---

## 3. Identity verification security (NIDA)

**Reviewer feedback:** Name mismatch detection is good; need escalation for stolen NIDA use.

| # | Requirement | Status | Priority | Acceptance criteria |
|---|-------------|--------|----------|---------------------|
| 3.1 | Attempt 1: name mismatch warning | Done | — | Already implemented |
| 3.2 | Attempt 2: stronger warning | New | P0 | Distinct copy + audit log |
| 3.3 | Attempt 3: **24h account lock** | New | P0 | Login blocked; admin can unlock |
| 3.4 | Configurable in **Settings → Identity Verification** | New | P1 | Max attempts, lock duration |

**Suggested tasks**
- [ ] Track `nida_verification_attempts` on customer.
- [ ] `NidaVerificationService` increment + lock logic.
- [ ] Admin settings group `identity_verification`.
- [ ] Borrower messaging for each attempt tier.

---

## 4. Face verification

**Reviewer feedback:** Page opens but camera does not activate.

| # | Requirement | Status | Priority | Acceptance criteria |
|---|-------------|--------|----------|---------------------|
| 4.1 | Desktop: open webcam automatically | Bug | P0 | Camera preview visible without extra dead-end |
| 4.2 | Mobile: open front camera automatically | Bug | P0 | `facingMode: 'user'` on mobile |
| 4.3 | Steps: front, left, right, holding NIDA | Done | — | Wizard exists |
| 4.4 | Preview, Capture, Retake before submit | Partial | P1 | User confirms each photo |

**Current code**
- `face-verification-wizard.blade.php` requires **“Start verification”** click; `ready` gate may block on HTTPS/permissions.
- Possible issues: browser permission prompt not surfaced; `ready` never true; intro phase never auto-advances.

**Suggested tasks**
- [ ] Auto-call `startScan()` on mount when permissions already granted.
- [ ] Clear error UI when `getUserMedia` fails (permission denied, insecure context).
- [ ] Explicit preview/retake step per angle (partially present — verify on iOS Safari + Android Chrome).
- [ ] Test on production HTTPS (camera blocked on HTTP).

---

## 5. Profile page redesign

**Reviewer feedback:** Structure feels fragmented.

| # | Section | Mode | Status | Priority |
|---|---------|------|--------|----------|
| 5.1 | Personal information | Read-only (after NIDA lock) | Partial | P1 |
| 5.2 | Next of kin | Editable | Partial | P1 |
| 5.3 | Activity information | Editable | Partial | P1 |
| 5.4 | Residence information | Editable | Partial | P1 |
| 5.5 | Documents (income proof, residence letter) | Upload | Partial | P1 |
| 5.6 | Persistent **completion banner** at top | Always visible until 100% | Partial | P1 |

**Suggested tasks**
- [ ] Consolidate `borrower/profile/*.blade.php` into single tabbed layout.
- [ ] Move document uploads from scattered KYC page into Documents subsection.
- [ ] Sticky completion banner component shared with dashboard.

---

## 6. Loan application wizard

**Reviewer feedback:** Re-asks KYC questions already in profile.

| # | Requirement | Status | Priority | Acceptance criteria |
|---|-------------|--------|----------|---------------------|
| 6.1 | Skip personal, residence, kin, activity, income if profile complete | Partial | P0 | Wizard steps driven by `SmartLoanApplicationWizardService` / readiness |
| 6.2 | Individual loan flow: Quote → Guarantor → Review → Signature → Submit | Partial | P1 | No duplicate profile steps |

**Current code**
- `SmartLoanApplicationWizardService` and `apply/wizard.blade.php` have profile-skip logic but **still shows kin/personal steps in some paths**.
- `LoanProductReadinessService` links incomplete kin to application step.

**Suggested tasks**
- [ ] Audit wizard step builder: if `ProfileCompletionService` section complete → exclude step.
- [ ] Individual products: product-specific questions + guarantor + review + sign only.
- [ ] Regression test: complete profile → apply → max 4 steps.

---

## 7–10. Asset marketplace & lending flow

| # | Requirement | Status | Priority | Notes |
|---|-------------|--------|----------|-------|
| 7.1 | Asset card: value, deposit, weekly repayment, max tenure | Partial | P1 | Extend marketplace cards |
| 7.2 | Detail page: photos, description, deposit, remaining loan, weekly installment | Partial | P1 | `AssetMarketplaceController@show` |
| 8.1 | Filters: brand, model, price, tenure | New | P2 | Vehicle marketplace |
| 8.2 | **Find What You Need** at top (photo, budget, request) | Partial | P1 | Request form exists; promote to hero |
| 9.1 | Rename **Reserve Asset** → **Apply For Asset** | New | P1 | Copy + step labels |
| 9.2 | Flow: View → Viewing → Confirm interest → App fee → Deposit → Processing → Post-approval → Release | Partial | P1 | Align `AssetReservationService` steps |
| 10.1 | Show requirements before payment | Partial | P1 | Summary panel before fee/deposit actions |
| 10.2 | Max tenure from **loan product** (default 6 months) | Partial | P1 | `max_tenure_months` on asset + product link |

**Current code**
- Marketplace + reservation workflow implemented (Phase 4C).
- Payment steps are manual confirm buttons (no gateway).

---

## 11. Admin asset management

| # | Requirement | Status | Priority | Acceptance criteria |
|---|-------------|--------|----------|---------------------|
| 11.1 | **Admin → Asset Marketplace** (clear nav) | Partial | P1 | Single entry: `/admin/marketplace-assets` |
| 11.2 | Add / edit / remove asset | Done | — | CRUD exists |
| 11.3 | Assign supplier | Partial | P1 | `vendor_id` on asset; admin can set on behalf of supplier |

**Suggested tasks**
- [ ] Rename nav label to **Asset Marketplace** (under Settings or Partners).
- [ ] Supplier picker on admin asset form.

---

## 12. Partner management (unified module)

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 12.1 | Single **Partners** module | Partial | P2 |
| 12.2 | Types: supplier, GPS, insurance, valuer, debt collector, auctioneer, affiliate, capital | Partial | P2 |
| 12.3 | One partner, multiple roles | New | P2 |

**Current code:** `vendors.category` single value; separate admin views per category.

**Suggested tasks**
- [ ] Pivot table `vendor_roles` or JSON roles array.
- [ ] Unified partner list with role filters.

---

## 13. Loan products navigation

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 13.1 | Move **Loan Products** entirely to **Settings → Loan Products** | Partial | P2 |
| 13.2 | Remove from main admin menu | Partial | P2 |

**Current code:** Settings redirect exists (`PLATFORM-REVIEW-IMPLEMENTATION.md` #14); main nav may still show Loan Products.

---

## 14. Post-approval fees (admin) — **BUG**

**Reviewer feedback:** Add fee button not functioning.

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 14.1 | Add fee row dynamically | **Bug** | P0 |
| 14.2 | Fixed + percentage types | Done | — |
| 14.3 | Fee examples: documentation, insurance, GPS, registration, disbursement | Done | — |

**Root cause (confirmed):** `_post-approval-fees-fields.blade.php` uses `@push('scripts')` but **`admin/layout.blade.php` has no `@stack('scripts')`** — JavaScript never loads.

**Fix**
```blade
{{-- resources/views/components/admin/layout.blade.php --}}
@stack('scripts')
```
before `</body>`.

**Files:** `resources/views/admin/loan-products/_post-approval-fees-fields.blade.php`, `LoanProductController.php`

---

## 15. Tiered pricing (admin) — **BUG**

**Reviewer feedback:** Add tier not working.

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 15.1 | Add tier row dynamically | **Bug** | P0 |
| 15.2 | Amount range → monthly rate | Done | — |

**Root cause:** Same as §14 — `@stack('scripts')` missing in admin layout.

**Files:** `resources/views/admin/loan-products/_rate-tiers-fields.blade.php`, `LoanRateTierService.php`

---

## 16. BOT compliance — displayed rate

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 16.1 | Split **BOT regulated interest** (max 3.5%) vs **internal fees** | New | P2 |
| 16.2 | System calculates **displayed monthly rate** | New | P2 |

**Suggested tasks**
- [ ] Product-level fee components: processing, service, administration.
- [ ] `DisplayedRateService` for offer letter / wizard EMI label.
- [ ] Admin disclosure copy on product form.

---

## 17. Affiliate management

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 17.1 | **Admin → Affiliates** | Partial | P1 | `/admin/vendors/affiliates` exists |
| 17.2 | Create affiliate, code, link, commission/registration/application tracking | Partial | P1 | `AffiliateService` + events |
| 17.3 | Affiliates create own codes | New | P2 | Self-service in partner portal |

---

## 18. Finance configuration

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 18.1 | Clarity on account setup, posting rules, chart of accounts | Partial | P2 |
| 18.2 | Document how loan disbursement/repayment posts to GL | New | P2 |

**Suggested tasks**
- [ ] Internal doc: `docs/FINANCE-POSTING-RULES.md`.
- [ ] Admin tooltips on chart of accounts + charges/fees linkage.

---

## 19. Branches

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 19.1 | Digital-first: default **Head Office only** | New | P3 |
| 19.2 | Remove branch complexity from borrower UX | Partial | P3 |

**Note:** Branch scoping still used in admin workflow ACL — simplify UI before removing data model.

---

## 20. Departments

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 20.1 | Use departments for staff management | Partial | P2 |
| 20.2 | Seed defaults: Operations, Credit, Collections, Compliance, Finance, Customer Support, IT | New | P2 |

---

## 21. Notifications & promotions

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 21.1 | Birthday notifications | New | P3 |
| 21.2 | Promotional campaigns (discounted fees, interest campaigns, referral promos) | New | P3 |
| 21.3 | Single admin campaign screen | New | P3 |

---

## 22. Document templates

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 22.1 | Settings → Document Templates | Partial | P2 |
| 22.2 | Templates: offer letter, loan contract, guarantor agreement, asset lending agreement | Partial | P2 |
| 22.3 | Per-product template assignment | New | P2 |

**Current code:** PDFs hardcoded in `resources/views/pdf/`; admin document templates CRUD exists but linkage to products may be incomplete.

---

## 23. Multi-language (i18n)

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 23.1 | English + Swahili | New | P2 |
| 23.2 | Priority: all borrower-facing strings | New | P2 |

**Suggested approach:** Laravel `lang/en` + `lang/sw`, locale switcher in borrower layout, `@lang()` / `__()` migration in phases.

---

## 24. Public website

| # | Requirement | Status | Priority |
|---|-------------|--------|----------|
| 24.1 | Focus: borrowers, products, marketplace, registration | Partial | P2 |
| 24.2 | Partner info only inside authenticated portals | Partial | P2 |

**Suggested tasks**
- [ ] Public marketplace browse (read-only) optional.
- [ ] Trim partner marketing from public nav; keep in login/register flows.

---

## Recommended sprint plan

### Sprint 1 — Unblock production (P0)
| Item | Effort |
|------|--------|
| Fix admin `@stack('scripts')` (§14, §15) | S |
| Face verification camera auto-start + errors (§4) | M |
| NIDA 3-strike lockout (§3) | M |
| Wizard skip completed profile (§6) | M |

### Sprint 2 — Borrower experience (P1)
| Item | Effort |
|------|--------|
| Referral hub + share (§2) | M |
| Profile redesign + kin in banner (§1, §5) | L |
| KYC freshness 90-day selective refresh (§1) | M |
| Marketplace asset detail + Apply for Asset flow (§7–10) | L |

### Sprint 3 — Admin & compliance (P2)
| Item | Effort |
|------|--------|
| Admin asset marketplace nav clarity (§11) | S |
| Affiliate admin polish (§17) | S |
| BOT displayed rate (§16) | M |
| Loan products nav move (§13) | S |
| Document templates per product (§22) | M |

### Sprint 4 — Platform (P3)
| Item | Effort |
|------|--------|
| Unified partners module (§12) | L |
| i18n borrower portal (§23) | XL |
| Campaigns + birthday (§21) | L |
| Branch simplification (§19) | M |
| Finance posting documentation (§18) | M |

---

## Quick wins (< 1 day)

1. Add `@stack('scripts')` to admin layout → fixes fee + tier buttons.
2. Add kin to `ProfileCompletionService::displaySections()`.
3. Rename “Reserve Asset” → “Apply For Asset” in marketplace views.
4. Add share + copy buttons to membership referral block.
5. Admin nav label: **Asset Marketplace** → existing CRUD route.

---

## Sign-off tracking

| Area | Dev owner | Target sprint | Done |
|------|-----------|---------------|------|
| P0 bugs | | Sprint 1 | ☑ |
| Profile & KYC | | Sprint 2 | ☑ |
| Referrals UI | | Sprint 2 | ☑ |
| Marketplace flow | | Sprint 2 | ☑ |
| Admin config | | Sprint 3 | ☑ |
| Platform / i18n | | Sprint 4 | ☑ |

---

*Generated from product testing review. Update status columns as items ship.*
