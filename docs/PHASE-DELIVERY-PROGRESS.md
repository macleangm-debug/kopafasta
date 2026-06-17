# Phase delivery progress — product review

Checkpoint for phased UAT / product-review work on **kopafasta**.  
**Delivery arc complete** (Phases 3–38). Resume here only for deferred items or new scope.

**Last updated:** 2026-06-18  
**Test status:** **205/205** phase tests passing  
**Do not commit** unless explicitly requested.

---

## How to resume

1. Read this file and [TESTING-REVIEW-DEVELOPER-BRIEF.md](./TESTING-REVIEW-DEVELOPER-BRIEF.md).
2. Say **"next phase"** in Cursor to start a new bundle (post–Phase 38).
3. Run the full phase test filter after each phase (see below).
4. Run `php artisan migrate` on deploy when migrations exist.

---

## Completed phases (this delivery arc)

| Phase | Summary |
|-------|---------|
| **17** | Capital allocation strategies (round-robin, manual); public `/become-affiliate`; admin partner-applications queue; legacy vendor URL redirects; asset requests admin polish |
| **18** | Admin Settings nav restructure; affiliate footer + Swahili; content-width on activity/residence/KYC/documents; verification documents i18n |
| **19** | Kin structured fields; WhatsApp combined share on apply success; admin affiliate applications alert; loan profile + support narrow width |
| **20** | "Apply for asset" copy; asset lending default monthly rate admin setting; department seeder (Credit, Compliance, IT); referral share i18n |
| **21** | Partner codes `PTR-` in admin; legacy `/admin/vendors` redirects; Swahili marketplace/referral strings |
| **22** | Priority capital allocation tests; `PTR-` in Site/Api VendorController + VendorSeeder + partner login placeholder |
| **23** | Application fee requirements panel; face camera auto-start when permission granted; dashboard/loans wide + apply wizard narrow; Swahili application/valuation fee strings; proportional allocation test; partner-performance report route alias |
| **24** | NIDA 3-strike lockout (user login sync); wizard step plan excludes profile sections; Swahili payment/notification/NIDA strings; marketplace/notifications/payment wide layout |
| **25** | BOT rate disclosure in apply wizard; Swahili disbursement/loan servicing/policy/rate strings; referrals/membership/profile wide layout; marketplace + asset-backed wizard step regression |
| **26** | Face verification auto-starts camera on page load; Swahili offer/membership/support/documents strings; support/documents/loan-profile wide layout; admin tier add-script regression |
| **27** | Swahili post-approval fees, agreement, contract; support page full i18n (appeal, FAQ, chat bot); face verification wide layout; membership renew i18n; admin post-approval fee catalog sync + count on init |
| **28** | Swahili handover milestones, payments page, guarantor notification actions, public marketplace CTAs; apply success + payments wide layout; guarantor notifications i18n polish; SQLite `post_approval` charge_when parity |
| **29** | Payments create/show/refund i18n + wide layout; schedule + loan show wide; guarantor request detail wide; Swahili loan profile sections, payments sub-pages, schedule, guarantor detail strings |
| **30** | Guarantor expired/responded public pages i18n; loan show recent-payment label; profile security + kyc-reconfirm + guarantor onboarding wide layout; Swahili guarantor expired + loan servicing strings |
| **31** | Application detail i18n + wide layout; loan restructure/top-up wide + outstanding label; profile assets tab wide; security PIN form grid polish; kyc-reconfirm full width; Swahili application detail, loan actions, profile shell/assets strings; internal member guarantor login regression |
| **32** | Agreement page full i18n + wide; offer/contract/post-approval/disbursement wide layout; refunds page + refund status i18n; guaranteed loans tab + applications list polish; Swahili agreement summary, refunds, guaranteed, loan status strings |
| **33** | Asset conversion + guaranteed loan detail wide layout; guarantor request detail width polish; offer letter PDF i18n; payments ledger refund labels i18n + merge fix; guaranteed installment/modification status Swahili |
| **34** | Loan contract PDF i18n; public marketplace show wide + back link i18n; guarantor notifications fallback title; apply wizard error/placeholder i18n sweep; loan profile application shell wide regression; Swahili contract PDF + marketplace + previous guarantor strings |
| **35** | Full Swahili merge into `lang/sw/borrower.php` (0 missing keys vs EN); membership renew + marketplace reserve wide layout; notifications category/fallback title i18n; guarantor-requests redirect confirmation |
| **36** | Guarantors page full i18n + wide layout; membership page section/history/referral i18n |
| **37** | Standalone KYC page i18n + wide layout; face verification rejected/approved banners i18n; dashboard empty-state i18n; marketplace reservation payment form promo/wallet i18n; nested `marketplace.fees.payment_note` key fix |
| **38** | Swahili parity regression test (EN ↔ SW key count); delivery-arc spot checks; membership referral + guarantor confirm dialog + dashboard wide regressions |

---

## Migrations to run on deploy

```bash
php artisan migrate
```

Relevant migrations (if not yet applied in target env):

- `2026_06_22_100000_add_name_sw_to_loan_products.php` (Phase 16)
- `2026_06_22_200000_phase17_platform_features.php` (Phase 17 — `lenders.allocation_priority`, `partner_applications`)

---

## Full phase test command

```bash
php artisan test --filter="Phase38FeatureTest|Phase37FeatureTest|Phase36FeatureTest|Phase35FeatureTest|Phase34FeatureTest|Phase33FeatureTest|Phase32FeatureTest|Phase31FeatureTest|Phase30FeatureTest|Phase29FeatureTest|Phase28FeatureTest|Phase27FeatureTest|Phase26FeatureTest|Phase25FeatureTest|Phase24FeatureTest|Phase23FeatureTest|Phase22FeatureTest|Phase21FeatureTest|Phase20FeatureTest|Phase19FeatureTest|Phase18FeatureTest|Phase17FeatureTest|Phase16FeatureTest|Phase15FeatureTest|Phase14FeatureTest|Phase13FeatureTest|Phase12FeatureTest|Phase11FeatureTest|Phase10FeatureTest|Phase9FeatureTest|Phase8FeatureTest|Phase7FeatureTest|Phase6FeatureTest|Phase5FeatureTest|Phase4FeatureTest|Phase3FeatureTest|NidaVerificationLockoutTest|ValuationPartnerWorkflowTest|AssetDepositPaymentTest|CustomerDossierTest"
```

---

## Deferred / post–Phase 38

| Area | Brief ref | Notes |
|------|-----------|-------|
| Face verification | §4 | Auto-start on page load done; iOS Safari QA still open |
| Unified partners module | §12 | Single role per `vendors.category`; pivot/JSON roles later |
| `vendors` → `partners` DB rename | — | Explicitly deferred; model still `Vendor` |
| Application fee UX | §10 | Requirements panel done; gateway integration TBD |
| Residence / document camera | §4 / §5 | `tz_address` on FC workshop done (Phase 15) |
| Legacy `guarantor-requests.blade.php` | — | Route redirects to loans tab; blade unused |

---

## Key files touched recently

- `lang/en/borrower.php`, `lang/sw/borrower.php` (full Swahili parity)
- `resources/views/site/borrower/guarantors.blade.php`, `membership.blade.php`, `kyc.blade.php`
- `resources/views/site/borrower/membership-renew.blade.php`, `marketplace/reserve.blade.php`, `marketplace/_reservation-payment-form.blade.php`
- `resources/views/site/borrower/notifications.blade.php`, `dashboard.blade.php`, `face-verification.blade.php`
- `tests/Feature/Phase35FeatureTest.php` … `Phase38FeatureTest.php`

---

## Workflow reminders

- User pattern: ask **"next phase"** to continue past Phase 38.
- **Do not commit** unless the user explicitly asks.
- Prefer minimal diffs; match existing conventions.
- Run phase test suite after each phase and report pass count.
