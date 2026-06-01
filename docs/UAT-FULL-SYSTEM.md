# KopaFasta — Full System UAT Checklist

User Acceptance Testing guide for the entire platform: public site, borrower portal, guarantor flows, partner portals, investor portal, and admin console.

**Production URL:** https://kopafasta.triptz.net  
**Admin console:** `/admin/login`  
**Borrower portal:** `/login`

---

## UAT execution — start here (May 2026)

Development is paused for UAT. Use this order so blockers surface early.

### Before you test (15 min)

1. **Create dedicated UAT accounts** on production — do not use live member data.
2. **Admin → Settings → Company Profile** — confirm app base URL is `https://kopafasta.triptz.net`.
3. **Admin → Settings → KYC** — confirm **CRB sandbox / stub mode** is ON for NIDA testing without live bureau credentials.
4. **Admin → Settings → Identity Verification** — note max mismatch attempts (default 3) and lock hours (default 24).
5. Have one **admin**, one **credit officer**, and two **borrower** accounts ready (referrer + referred for Journey A).

### Priority 1 — P0 smoke (≈45 min)

Run these first. Log any **Fail** with URL, steps, screenshot, and severity.

| # | Area | URL / path | What to verify |
|---|------|------------|----------------|
| S1 | Admin loan product fees | `/admin/loan-products/{id}/edit` | **Add fee** and **Add tier** buttons add rows (scripts load) |
| S2 | Face verification camera | `/borrower/face-verification` | Camera preview starts on HTTPS (desktop + mobile); front camera on phone |
| S3 | NIDA verify (match) | Profile → Personal | Use sandbox NIDA `19810713-00001-23456-78` with matching DOB `1981-07-13` → verified |
| S4 | NIDA mismatch + lockout | Profile → Personal | Register as different name → verify same NIDA 3× → account locked; admin unlock on customer page |
| S5 | Apply wizard skip | `/borrower/apply` | With **complete profile**, wizard skips personal/residence/kin/activity; shows quote → review → sign |
| S6 | Language switch | Borrower nav | Switch EN ↔ SW on dashboard, apply flow, referral hub — labels follow locale |

### Priority 2 — Borrower portal (≈2 h)

Sections **§4–§9** below. Focus on rows marked **Partial** or recently changed.

### Priority 3 — Admin + E2E journeys (≈4 h)

Sections **§14–§20**. Run **Journey A** (individual loan) and **Journey B** (asset) minimum before sign-off.

### Sandbox NIDA numbers (stub mode only)

| Scenario | NIDA number | Notes |
|----------|-------------|-------|
| Verified match | `19810713-00001-23456-78` | Register with names **Gaspari Malim Shiliba** / **Shiliba**, DOB **1981-07-13**, gender **male** |
| Multihit | `19890304-00001-56789-01` | Pick correct candidate on profile |
| No hit | `20000101-99999-99999-99` | Expect failure message |
| Name mismatch | Use verified NIDA with **different** registered name | Triggers mismatch UI; repeat to test lockout |

### Payments during UAT

Many flows are **Manual** — use admin bank payment approval or in-app “confirm paid” buttons. Do **not** fail UAT because live M-Pesa/card is absent (see §22).

### Defect log template

```
ID: UAT-###
Portal: Borrower | Admin | Public
URL:
Role:
Steps:
Expected:
Actual:
Severity: Blocker | Major | Minor
Screenshot:
```

---

## How to use this document

| Column | Meaning |
|--------|---------|
| **Steps** | What the tester does |
| **Expected** | Pass criteria |
| **Result** | Pass / Fail / Blocked / N/A |
| **Notes** | Tester name, date, screenshot ref, bug ID |

**Legend**
- **Done** — implemented and ready for UAT
- **Partial** — core flow works; known gaps listed
- **Manual** — no live payment gateway; use manual confirm / bank approval flows

Record defects with: portal, URL, role, steps to reproduce, expected vs actual, severity.

---

## 1. Test environment setup

| # | Check | Expected | Result | Notes |
|---|-------|----------|--------|-------|
| 1.1 | Confirm **App base URL** in Admin → Settings → Company Profile | Matches production URL (used in guarantor/referral/affiliate links) | | |
| 1.2 | Confirm SMS/email gateways configured (or test mode documented) | OTP and notifications behave predictably | | |
| 1.3 | Seed or create test users for each role (see §2) | All roles can log in | | |
| 1.4 | Create at least one active **loan product** per category (individual, asset, group) | Products visible on apply wizard | | |
| 1.5 | Create test **vendor** (GPS), **supplier**, and **affiliate** partners | Partner portals accessible | | |

### Suggested test accounts (local/staging seed)

| Role | Login | Password (seed default) |
|------|-------|-------------------------|
| Admin | `admin@kopafasta.local` | `Password@123` |
| Credit officer | `officer@kopafasta.local` | `Password@123` |
| Borrower | Register new or use seeded customer | Set PIN on first login |

> Production may use different credentials. Create dedicated UAT accounts rather than testing on live members.

---

## 2. Public website

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 2.1 | Home | Open `/` | Branding, navigation, CTAs load | Done | |
| 2.2 | Products | Browse `/loans` and a product detail page | Product cards and terms display | Done | |
| 2.3 | Static pages | Visit About, FAQ, How it works | Content renders on mobile + desktop | Done | |
| 2.4 | Capital partners landing | Visit `/capital-partners` | Info page loads; register link works | Done | |
| 2.5 | Affiliate redirect | Open `/aff/{code}` for valid affiliate | Redirects to registration with `?aff=` preserved | Done | |
| 2.6 | Referral registration | Open `/register/borrower?ref={code}` | Referrer attached after registration | Done | |
| 2.7 | Waitlist | Submit waitlist form | Success message / record created | Done | |

---

## 3. Authentication & security

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 3.1 | Borrower login | Login with phone + PIN | Dashboard loads | Done | |
| 3.2 | Forgot PIN | Request OTP → reset PIN | New PIN works; old PIN rejected | Done | |
| 3.3 | First-time PIN setup | New borrower completes setup PIN | Cannot access portal until PIN set | Done | |
| 3.4 | Admin login | Login at `/admin/login` | Console dashboard loads | Done | |
| 3.5 | Role permissions | Log in as officer vs admin | Officer blocked from admin-only actions | Done | |
| 3.6 | Branch scoping | Officer from Branch A opens Branch B application | Access denied or filtered | Done | |
| 3.7 | Logout | Log out from borrower and admin | Session cleared | Done | |
| 3.8 | Trusted devices | Revoke device from Profile → Security | Device requires re-auth | Done | |

---

## 4. Borrower — membership & referrals

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 4.1 | Membership card | Open `/borrower/membership` | Member number, status, expiry visible | Partial | |
| 4.2 | Copy member number | Tap copy on membership card | Toast confirms copy | Partial | |
| 4.3 | First registration fee | Pay via mobile money (simulated) | Membership active; history recorded | Manual | |
| 4.4 | Referral discount | Register/pay with valid `?ref=` | Discount shown at checkout | Done | |
| 4.5 | Referral wallet | Apply wallet to registration fee (≤50%) | Wallet debited; cash due reduced | Done | |
| 4.6 | Affiliate discount | Register with `?aff=` (no referrer) | Affiliate discount at registration | Done | |
| 4.7 | Bank transfer renewal | Submit bank payment reference | Status pending until admin approves | Manual | |
| 4.8 | Referral link display | View referral code + link on membership page | Link uses app base URL | Done | |
| 4.9 | Referral hub | Open `/borrower/referrals` | Code, link, share (WhatsApp/SMS/copy), wallet rules | Done | |

---

## 5. Borrower — profile & KYC

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 5.1 | Profile sections | Navigate Personal, Activity, Residence, KYC, Security | Each section loads and saves | Partial | |
| 5.2 | DOB 18+ rule | Enter DOB under minimum age | Validation blocks save | Partial | |
| 5.3 | NIDA verify | Run Verify Identity on NIDA number | Loading state → verified / mismatch UI | Done | Stub samples on profile when sandbox ON |
| 5.4 | NIDA name mismatch | Trigger mismatch; accept NIDA names | Names updated; fields locked | Done | |
| 5.4b | NIDA lockout | Repeat mismatch until max attempts | Login blocked; lock banner; admin unlock restores access | Done | Settings → Identity Verification |
| 5.5 | Face verification | Complete 4-angle capture flow | Camera auto-starts; upload succeeds; status pending review | Done | Retest iOS Safari + Android Chrome |
| 5.6 | Admin face approve | Admin approves face verification | Borrower status updates | Done | |
| 5.7 | KYC documents | Upload required docs on `/borrower/kyc` | Files stored; checklist updates | Done | |
| 5.8 | KYC reconfirm | Trigger stale KYC (if configured) | Borrower redirected to reconfirm | Partial | |
| 5.9 | CRB check | Apply when CRB enabled | CRB result stored on application | Partial | |

---

## 6. Borrower — loan application

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 6.1 | Product readiness | Open apply wizard; select product | Readiness checklist shows blockers (localized EN/SW) | Done | |
| 6.2 | Wizard steps | Complete apply with full profile | Skips completed profile sections; quote → guarantor/questions → review → sign | Done | Regression: complete profile first |
| 6.3 | Tiered rate preview | Change amount band on individual product | EMI/rate reflects tier | Done | |
| 6.4 | Product questions | Answer EM/EL/FC-specific questions | Answers saved on application | Partial | |
| 6.5 | Guarantor (internal) | Add existing member as guarantor | Invitation / link sent | Partial | |
| 6.6 | Guarantor (external) | Invite external guarantor | SMS/email link `{base}/guarantor/{token}` | Partial | |
| 6.7 | Submit application | Submit complete application | Success page; status Submitted | Done | |
| 6.8 | Application detail | Open application from list | Stage, docs, next steps visible | Done | |
| 6.9 | Upload requested docs | Fulfill admin document request | Request marked satisfied | Done | |
| 6.10 | Affiliate tracking | Apply as affiliate-attached customer | `affiliate_events` application recorded | Done | |

---

## 7. Borrower — agreement & post-approval

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 7.1 | Offer letter | Admin generates offer; borrower opens agreement | PDF/view loads | Done | |
| 7.2 | OTP sign offer | Request OTP → sign offer letter | Status signed; contract generated | Done | |
| 7.3 | Download agreement | Download signed PDF | File opens; data matches application | Done | |
| 7.4 | Post-approval fees list | After approval, open post-approval fees page | Fee rows match product config | Done | |
| 7.5 | GPS bundled fee | Asset product with GPS fee code | Amount = device + monitoring × tenure + markup | Done | |
| 7.6 | Pay post-approval fees | Pay with referral wallet / affiliate discount | Fees marked paid; reservation syncs | Manual | |
| 7.7 | Asset ownership in PDF | Asset loan (product code AL) signed contract | Ownership clause present | Done | |
| 7.8 | Guarantor sign on contract | External guarantor signs loan contract | **Known gap:** external guarantor signature pending | Partial | |

---

## 8. Borrower — loans, repayments & notifications

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 8.1 | Loans borrowed | View `/borrower/loans` | Active loans listed | Done | |
| 8.2 | Loans guaranteed | Same page — guaranteed section | Guaranteed loans visible | Done | |
| 8.3 | Guarantor requests | Respond approve/decline on pending request | Status updates; legacy URL redirects here | Done | |
| 8.4 | Repayment schedule | Open schedule for a loan | Installments and due dates correct | Done | |
| 8.5 | Submit repayment | Record payment (manual channel) | Payment logged; balance updates | Manual | |
| 8.6 | Notifications bell | Open notifications; mark read / clear | Count updates | Done | |
| 8.7 | Application list | View `/borrower/applications` | Cards/table show status | Partial | |

---

## 9. Borrower — asset marketplace

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 9.1 | Browse marketplace | Open `/borrower/marketplace` | Active assets listed | Done | |
| 9.2 | Reserve asset | Schedule viewing slot | Reservation created | Done | |
| 9.3 | Pay reservation fee | Advance workflow — pay reservation fee | Status → reservation fee paid | Manual | |
| 9.4 | Complete viewing | Mark viewing complete | Status advances | Done | |
| 9.5 | Pay deposit | Mark deposit paid | Supplier payout accrues (admin queue) | Manual | |
| 9.6 | Link to loan application | Submit asset loan application | Reservation linked | Done | |
| 9.7 | Post-approval sync | Approve loan + pay post-approval fees | Reservation status advances | Done | |
| 9.8 | Asset request | Submit custom asset request form | Admin sees request in queue | Done | |

---

## 10. External guarantor flow

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 10.1 | Guest link | Open `/guarantor/{token}` without login | Accept / reject page loads | Done | |
| 10.2 | Accept invitation | Accept → register or login | Session tracks invitation | Done | |
| 10.3 | Membership + KYC | Complete membership fee and verification | Redirected through onboarding | Partial | |
| 10.4 | Onboarding confirm | Complete `/borrower/guarantor/onboarding` | `CustomerGuarantor` approved | Done | |
| 10.5 | Reject invitation | Reject from guest page | Borrower notified; status rejected | Done | |

---

## 11. Vendor portal (GPS / insurance / valuers)

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 11.1 | Dashboard | Login as vendor user | Tasks summary loads | Done | |
| 11.2 | Accept task | Accept assigned task | Status → accepted | Done | |
| 11.3 | Start task | Mark in progress | Timestamps recorded | Done | |
| 11.4 | Upload proof | Attach photo/PDF proof | File visible on task | Done | |
| 11.5 | Complete task | Complete with GPS serial (if GPS) | Invoice/payment row created (pending) | Done | |
| 11.6 | Payments list | View vendor payments | Shows pending → approved → paid | Done | |
| 11.7 | Calendar & profile | Update profile; view calendar | Saves without error | Done | |

---

## 12. Supplier portal

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 12.1 | Dashboard | Login as supplier vendor | Portal loads | Done | |
| 12.2 | Manage assets | Create/edit marketplace asset | Asset visible in borrower marketplace | Done | |
| 12.3 | Deposit markup | Set supplier deposit + markup % | Customer deposit calculates correctly | Done | |
| 12.4 | View reservations | Open reservations list | Linked customers/applications shown | Done | |
| 12.5 | Asset requests | View assigned requests from admin | Request details correct | Done | |
| 12.6 | Settlements | View settlement status | Shows invoice, status, batch ref | Done | |

---

## 13. Investor / capital partner portal

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 13.1 | Dashboard | Login as investor | Overview loads | Partial | |
| 13.2 | Funding pools | Browse and open a pool | Pool details and invest form | Partial | |
| 13.3 | Invest | Submit investment | Record created | Manual | |
| 13.4 | Wallet deposit/withdraw | Use wallet actions | Balances update | Manual | |
| 13.5 | Returns & analytics | View returns/transactions | Data consistent with investments | Partial | |

---

## 14. Admin — applications workflow

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 14.1 | Application queues | Open New, Under review, Pre-approval, Final approval lists | Filters match stage | Done | |
| 14.2 | Acknowledge | Submitted → Screening | Stage history + audit log | Done | |
| 14.3 | Screening → Credit review | Complete screening action | Affordability evaluated | Done | |
| 14.4 | Pre-approve | Move to pre-approval within limit | `pre_approved_at` set | Done | |
| 14.5 | Approval limit | Officer exceeds limit | Action blocked with message | Done | |
| 14.6 | Final approve | Approve application | Post-approval fees generated; reservation syncs | Done | |
| 14.7 | Affordability override | Admin overrides fail verdict | Transition allowed with audit | Done | |
| 14.8 | Reject | Reject with reason | Borrower sees rejection | Done | |
| 14.9 | Document requests | Request ad-hoc document | Borrower can upload; admin satisfy/reject | Done | |
| 14.10 | Generate offer letter | Generate agreement from admin | PDF stored; borrower can sign | Done | |
| 14.11 | Disbursement stage | Mark ready for disbursement | Loan origination triggered | Done | |

---

## 15. Admin — customers & compliance

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 15.1 | Customer CRUD | Create/view/edit customer | Profile sections editable | Done | |
| 15.2 | Verify documents | Approve/reject uploaded docs | Status updates | Done | |
| 15.3 | Face verification queue | Approve/reject with reason | SMS/email to borrower | Done | |
| 15.4 | Membership bank payments | Approve pending bank membership payment | Membership activated; referral/affiliate settled | Manual | |
| 15.5 | KYC settings | Change min age / income proof flags | Affects new applications | Partial | |
| 15.5b | Identity verification settings | Admin → Settings → Identity Verification | Max mismatch attempts + lock hours saved | Done | |
| 15.6 | AML / PEP / blacklist | Review compliance lists | Records manageable | Done | |
| 15.7 | Audit logs | Open audit log after an action | Entry shows user, before/after | Done | |

---

## 16. Admin — loans & finance

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 16.1 | Loan products | Edit product: tiers, post-approval fees, requirements | Add tier/fee rows work; saved config drives borrower behavior | Done | Fixed admin `@stack('scripts')` |
| 16.2 | Disburse loan | Disburse from admin loan record | Fees applied; net disbursed calculated | Done | |
| 16.3 | Application fee at disbursement | Disburse with referral/affiliate customer | Discount + commission/wallet settled | Done | |
| 16.4 | Record repayment | Post repayment against schedule | Outstanding balance reduces | Done | |
| 16.5 | Arrears / overdue jobs | Verify overdue marking (cron) | Overdue installments flagged | Done | |
| 16.6 | Write-off | Write off eligible loan | Loan closed; audit recorded | Done | |
| 16.7 | Expenses & journals | Create/post expense | Journal entry created | Partial | |
| 16.8 | Payment rail settlements | CRUD on finance Settlements (M-Pesa/NMB) | Separate from partner settlements | Done | |

---

## 17. Admin — partners & marketplace

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 17.1 | Partner CRUD | Create GPS, supplier, affiliate vendors | Categories filter correctly | Done | |
| 17.2 | Affiliate code | Save affiliate; copy registration link | Link tracks clicks/registrations | Done | |
| 17.3 | Vendor tasks | Assign/view tasks | Vendor receives task in portal | Done | |
| 17.4 | Marketplace assets (admin) | CRUD admin marketplace assets | Borrower marketplace updates | Done | |
| 17.5 | Asset requests | Assign supplier; update status | Supplier notified | Done | |
| 17.6 | Referrals settings | Admin → Settings → Referrals | Discount/commission/wallet cap saved | Done | |

---

## 18. Admin — partner settlements (Phase 4D)

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 18.1 | Pending payments queue | Finance → Partner payments | Lists vendor/supplier/affiliate accruals | Done | |
| 18.2 | Approve payment | Approve a pending vendor payment | Status → approved | Done | |
| 18.3 | Cancel payment | Cancel erroneous payment | Status → cancelled | Done | |
| 18.4 | Weekly batch job | Run `partners:queue-weekly-settlements` (or wait for Friday cron) | Batch created per vendor | Done | |
| 18.5 | Approve batch | Finance → Partner settlements → Approve | Batch status → approved | Done | |
| 18.6 | Mark batch paid | Enter channel + reference → Mark paid | All linked payments → paid | Done | |
| 18.7 | Supplier deposit accrual | After borrower deposit paid | Pending payment for supplier deposit amount | Done | |
| 18.8 | Affiliate commission accrual | After fee settlement (no referrer) | Pending payment on affiliate vendor | Done | |

---

## 19. Admin — settings & reports

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 19.1 | Company profile | Update legal name, app URL, signatory | PDFs and links use new values | Done | |
| 19.2 | Branches & users | Create branch user with approval limit | Workflow respects limit | Done | |
| 19.3 | Roles & permissions | Adjust role permissions | UI access matches ACL | Done | |
| 19.4 | Notification templates | Edit SMS/email template | Message uses placeholders | Partial | |
| 19.5 | Reports | Portfolio, disbursements, PAR, trial balance | Figures match sample data | Partial | |
| 19.6 | Admin alert bell | Pending counts for applications/KYC | Bell updates after actions | Done | |

---

## 19b. Borrower i18n (English + Swahili)

| # | Area | Steps | Expected | Status | Result |
|---|------|-------|----------|--------|--------|
| 19b.1 | Locale switcher | Toggle language in borrower header | UI persists for session | Done | |
| 19b.2 | Dashboard + nav | Switch to Kiswahili | Nav, cards, completion banner in SW | Done | |
| 19b.3 | Profile sections | Visit personal, residence, kin, activity, KYC | Field labels and buttons translated | Done | |
| 19b.4 | Apply wizard | Full apply flow in SW | Browse, readiness, steps, alerts in SW | Done | |
| 19b.5 | Referrals hub | `/borrower/referrals` in SW | Share labels and wallet rules translated | Done | |
| 19b.6 | Untranslated (OK) | Region/district dropdowns | Geographic names remain English | Partial | Backlog — not a UAT fail |

---

## 20. End-to-end journeys (smoke tests)

Run these as full cross-portal scenarios. Each should complete without manual DB edits.

### Journey A — Individual loan (referral member)

1. Member A shares referral link → Member B registers and pays registration fee (referral discount + wallet if used).
2. Member B completes KYC, applies for individual loan, adds guarantor.
3. Officer moves application through workflow to **approval**; generates offer letter.
4. Borrower signs offer via OTP; pays post-approval fees.
5. Admin disburses loan; verify schedule and referral commission/wallet entries.

| Step | Pass | Notes |
|------|------|-------|
| A complete | | |

### Journey B — Asset loan + marketplace + GPS

1. Supplier lists asset; borrower reserves, pays reservation fee + deposit (manual steps).
2. Borrower applies for asset product (AL); admin approves.
3. Verify GPS post-approval fee uses bundled pricing; ownership clause in contract PDF.
4. GPS vendor completes task → payment pending → admin approves → weekly batch → paid.
5. Supplier settlement shows deposit payout in queue.

| Step | Pass | Notes |
|------|------|-------|
| B complete | | |

### Journey C — External guarantor

1. Borrower invites external guarantor → guest opens link → accepts.
2. Guarantor registers, pays membership, completes KYC + onboarding.
3. Loan proceeds to approval; verify guarantor status approved on application.

| Step | Pass | Notes |
|------|------|-------|
| C complete | | |

### Journey D — Affiliate (no referrer)

1. Open `/aff/{code}` → register new borrower.
2. Pay registration with affiliate discount; verify affiliate commission in Partner payments (pending).
3. Complete loan through disbursement; verify application fee commission accrual.

| Step | Pass | Notes |
|------|------|-------|
| D complete | | |

---

## 21. Non-functional checks

| # | Area | Steps | Expected | Result |
|---|------|-------|----------|--------|
| 21.1 | Mobile UX | Repeat Journey A on phone viewport | Usable without horizontal scroll | |
| 21.2 | Performance | Load admin application list with 100+ rows | Acceptable pagination/render time | |
| 21.3 | Security | Access admin URL as borrower | Redirect/denied | |
| 21.4 | IDOR | Borrower opens another customer's application URL | 404/403 | |
| 21.5 | File upload limits | Upload oversize doc | Validation error | |
| 21.6 | Scheduled jobs | Confirm cron: overdue, reminders, partner settlements | Jobs run on schedule | |

---

## 22. Known gaps (do not fail UAT — log as backlog)

| Item | Notes |
|------|-------|
| Live M-Pesa / card gateway | Many payments are manual confirm or bank approval |
| External guarantor signs loan contract PDF | Partial — onboarding done; contract signature pending |
| Finance sidebar restructure | Planned — old grouping may still appear |
| PHPUnit on SQLite | Local test DB migration order issue; production OK |
| Dynamic activity/income profiles | Partial — static fields in places |
| CRB live API | May be stubbed depending on environment — use sandbox for UAT |
| Investor revenue share % | Partial on capital partner config |
| KYC 90-day freshness selective refresh | Partial — service exists; full expiry UX not complete |
| Tanzania region/district names in Swahili | Geographic data still English |
| Loan product document names in readiness panel | From DB; may show English in SW locale |

---

## 23. UAT sign-off

| Portal / area | Tester | Date | Pass | Fail | Blocked | Signed |
|---------------|--------|------|------|------|---------|--------|
| Public site | | | | | | |
| Borrower portal | | | | | | |
| Guarantor flow | | | | | | |
| Vendor portal | | | | | | |
| Supplier portal | | | | | | |
| Investor portal | | | | | | |
| Admin — applications | | | | | | |
| Admin — finance | | | | | | |
| Admin — partners | | | | | | |
| End-to-end journeys | | | | | | |

**Product owner approval:** ___________________ **Date:** __________  

**Technical lead approval:** ___________________ **Date:** __________  

---

## Quick URL reference

| Area | Path |
|------|------|
| Borrower dashboard | `/borrower` |
| Apply | `/borrower/apply` |
| Referrals hub | `/borrower/referrals` |
| Marketplace | `/borrower/marketplace` |
| Membership | `/borrower/membership` |
| Vendor portal | `/vendor` |
| Supplier portal | `/supplier` |
| Investor portal | `/investor` |
| Admin dashboard | `/admin` |
| Partner payments | `/admin/vendor-payments` |
| Partner settlements | `/admin/partner-settlements` |
| Referrals settings | `/admin/settings/referrals` |
| Asset requests | `/admin/asset-requests` |
