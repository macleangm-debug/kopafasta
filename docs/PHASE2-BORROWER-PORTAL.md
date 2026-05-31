# Borrower Portal, Membership & Loan Application — Phase 2

Production-ready microfinance workflow aligned with BOT compliance, NIDA verification, CRB checks, guarantor management, and mobile-first UX.

**Status baseline:** Phase 1 delivered borrower shell, membership renew (bank + mobile money), admin bank payment approval, 4-step apply wizard with product cards, and OTP loan agreement signing. This document defines Phase 2 gaps and implementation order.

---

## Current state summary

| # | Area | Status | Key files |
|---|------|--------|-----------|
| 1 | Auth (phone + PIN) | **Missing** | `Site/AuthController.php`, `login.blade.php` — email/phone + password only |
| 2 | Membership card copy | **Partial** | `components/site/member-card.blade.php` — display only |
| 3 | Profile subsections | **Partial** | `borrower/membership.blade.php` (Who/What/Where/KYC summary) |
| 4 | NIDA + CRB | **Missing** | NIDA text field only; admin flags in `settings/kyc.blade.php` |
| 5 | Face verification | **Missing** | File upload selfie only in `borrower/kyc.blade.php` |
| 6 | Product cards | **Done** | `site/apply/wizard.blade.php`, `ApplyController.php` |
| 7 | Wizard (extended) | **Partial** | 4 steps: Product → Personal → Income → Review |
| 8 | DOB 18+, kin, address | **Missing** | `min_age` in admin settings, not enforced |
| 9 | Dynamic activity / income | **Missing** | Static `employment_type` + numeric income |
| 10 | Guarantor workflow | **Partial** | `borrower/guarantors.blade.php` — no internal/external flows |
| 11 | Digital signatures | **Partial** | OTP loan signing in `LoanAgreementController.php` |
| 12 | Application review | **Partial** | Basic wizard step 4 + `borrower/application.blade.php` |
| 13 | Applications card/table | **Partial** | Cards (borrower), table (admin), no toggle |
| 14 | Ad-hoc doc requests | **Missing** | Product requirements only |
| 15 | KYC freshness | **Missing** | No expiry / reconfirm logic |

---

## Implementation phases

### Phase 2A — UX & compliance quick wins (1–2 sprints)

No external API dependencies. Ship first.

#### 2A.1 Membership card improvements
**Requirements**
- Prominent membership number (bank-card style typography)
- Copy icon → toast “Membership Number Copied”
- Target format example: `KPF-TZ-0001-4567-8923` (consider formatting helper for display)

**Tasks**
- [ ] Update `resources/views/components/site/member-card.blade.php`
- [ ] Add Alpine/JS copy + toast (reuse existing toast pattern if any)
- [ ] Optional: `MemberNumberFormatter` helper for grouped display

**Acceptance**
- Member number readable at a glance on mobile
- One tap copies full number; user sees confirmation

---

#### 2A.2 Profile structure redesign
**Requirements**

Replace Who / What / Where / KYC summary with **Profile** containing:

| Subsection | Fields |
|------------|--------|
| Personal Information | Name, DOB, Gender, NIDA Number, Face Verification status |
| Activity Information | What do you do?, Income range, Business details (dynamic) |
| Residence Information | Region, District, Street, Supporting documents |
| KYC Information | Verification status, NIDA status, Face match status |

**Tasks**
- [ ] New route group: `/borrower/profile/{section}` or tabbed single page
- [ ] Refactor `borrower/profile.blade.php` into subsection partials
- [ ] Update sidebar in `components/site/borrower-layout.blade.php`
- [ ] Migrate membership page (`borrower/membership.blade.php`) to link into Profile subsections instead of duplicating data

**Acceptance**
- All four subsections navigable from Profile
- Membership page shows card + status; detailed edits live under Profile

---

#### 2A.3 DOB validation (18+ BOT compliance)
**Requirements**
- Only applicants 18+ may register or apply
- Use admin setting `min_age` (default 18) from KYC settings

**Tasks**
- [ ] Enforce in `Site/AuthController` (register), `ApplyController`, `BorrowerController::updateProfile`
- [ ] Shared rule: `App\Rules\MinimumAge` reading from `Setting::group('kyc')`
- [ ] Client-side hint on DOB fields

**Acceptance**
- Under-18 submission blocked with clear message
- Setting change in admin affects all entry points

---

#### 2A.4 Address hierarchy (Tanzania)
**Requirements**

```
Region → District → Ward (optional) → Street (manual)
```

**Tasks**
- [ ] Migration: add `region`, `district`, `ward`, `street` to `customers` (keep legacy `address` for backfill)
- [ ] Seed or JSON config: `config/tanzania_locations.php` (regions + districts)
- [ ] Cascading selects component (Alpine/Livewire)
- [ ] Apply in profile + wizard Step 2 (Personal)

**Acceptance**
- Region/district required; ward optional; street required
- Existing flat addresses migrated or shown as street fallback

---

#### 2A.5 Next of kin (mandatory for loan apply)
**Requirements**

| Field | Required |
|-------|----------|
| Full Name | Yes |
| Relationship | Yes |
| Phone Number | Yes |
| Region | Yes |
| District | Yes |

**Tasks**
- [ ] Migration: `next_of_kin` table or JSON column on `customers` / `loan_applications`
- [ ] Wizard step or subsection in Personal step
- [ ] Validation before submit in `ApplyController`

**Acceptance**
- Cannot submit application without complete next-of-kin block

---

#### 2A.6 Activity information & income ranges
**Requirements**

- Rename “Employment Type” → **What Do You Do?**
- Options: Business Owner, Farmer, Artisan, Trader, Employed, Student, Casual Worker, Transport Operator, Freelancer, Unemployed
- Dynamic follow-up questions (config-driven)
- Income as **ranges**, not free numeric field

**Income range options (default)**
- Below 100,000
- 100,000 – 300,000
- 300,000 – 500,000
- 500,000 – 1,000,000
- Above 1,000,000

**Tasks**
- [ ] Config: `config/activity_profiles.php` (question sets per activity type)
- [ ] Migration: `activity_type`, `activity_details` (JSON), `income_range` on customers
- [ ] Dynamic form partial in wizard + profile
- [ ] Admin settings page to edit activity types and income bands (optional v1: config file only)

**Acceptance**
- Selecting “Student” shows School Name; “Farmer” shows Farm Type; etc.
- Income stored as range key, display label shown in UI

---

#### 2A.7 Loan wizard refinements
**Requirements**

| Step | Content |
|------|---------|
| 1 | Choose Product — card selection (exists) |
| 2 | Loan Amount & Tenure — min/max from product settings; purpose dropdown |
| 3 | Personal + Kin + Address |
| 4 | Activity (dynamic) |
| 5 | Review + confirmation checkbox |
| 6 | Signature (see 2C) |

**Purpose dropdown (config-driven)**
- Business Expansion, Agriculture, School Fees, Medical Emergency, Asset Purchase, Working Capital, Home Improvement, Other

**Tasks**
- [ ] Extend `ApplyController` + `wizard.blade.php` to 5–6 steps
- [ ] Bind amount/tenure to `loan_products` min/max fields
- [ ] Add `purpose` column on `loan_applications`
- [ ] Product cards: show loan range, rate range, max tenure, eligibility (from product config)

**Acceptance**
- All product metadata visible on expandable cards
- Amount/tenure validated against selected product limits

---

#### 2A.8 Applications module — card/table toggle
**Requirements**
- Applications visible immediately after submission
- Card view and table view
- User preference persisted (session or `users.preferences` JSON)

**Tasks**
- [ ] Update `borrower/applications.blade.php` with toggle
- [ ] Store preference in session or DB
- [ ] Table columns: ref, product, amount, status, submitted, actions

**Acceptance**
- Toggle persists across visits for logged-in user

---

### Phase 2B — Authentication & security (1 sprint)

#### 2B.1 Phone + PIN login (primary)
**Requirements**

| Method | Fields |
|--------|--------|
| Primary | Phone + 4-digit PIN |
| Secondary | Email + Password |

- PIN: 4 digits, hashed (`pin_hash`), confirmation on setup
- PIN reset via OTP

**Tasks**
- [ ] Migration: `pin_hash`, `pin_set_at` on `users`
- [ ] `PinService` — hash, verify (bcrypt/argon)
- [ ] Register/setup PIN flow after first login or during registration
- [ ] Update `login.blade.php` — default tab Phone+PIN
- [ ] OTP reset flow (reuse SMS gateway from settings)

**Security**
- Max 5 failed attempts → lock 15 minutes (mirror API `AnomalyGuard` on web)
- [ ] Wire `AnomalyGuard` or shared `LoginThrottleService` into `Site\AuthController`

**Acceptance**
- Borrower can log in with phone + PIN
- Email/password remains available as secondary
- Lockout after 5 failures

---

#### 2B.2 Trusted devices (web)
**Requirements**
- Extend existing API trusted-device model to web borrower portal
- “Remember this device” on login

**Tasks**
- [ ] Reuse `TrustedDevice` model + migrations
- [ ] Web middleware to skip PIN re-entry on trusted device (optional step-up for sensitive actions)
- [ ] Device management in borrower profile/settings

**Existing files**
- `app/Models/TrustedDevice.php`
- `tests/Feature/TrustedDevicesTest.php`

**Acceptance**
- Trusted device cookie/token issued on opt-in
- User can revoke devices from profile

---

#### 2B.3 Biometric (future-ready)
**Requirements**
- No full implementation in Phase 2B; prepare extension points only

**Tasks**
- [ ] Document WebAuthn/passkey hook in auth service interface
- [ ] Placeholder UI “Biometric login coming soon” behind feature flag

---

### Phase 2C — Identity, KYC & guarantors (2–3 sprints)

#### 2C.1 NIDA verification
**Requirements**

- User enters NIDA: `XXXXXXXX-XXXXX-XXXXX-XX`
- Format validation client + server
- System request: NIDA → CRB (or NIDA bureau API)
- Returns: Full Name, DOB, Gender, NIDA Picture
- Auto-populate profile; **lock verified fields** from manual edit

**Tasks**
- [ ] `App\Services\NidaVerificationService` (interface + stub + env-driven provider)
- [ ] `App\Services\CrbService` for bureau call (interface; implement when credentials available)
- [ ] Store verification payload on `customer_kycs` (encrypted JSON)
- [ ] Fields flagged `verified_at` / `verified_source` on customer
- [ ] UI: NIDA entry in Profile → Personal; show match status

**Config**
- Admin: API keys, sandbox mode in `settings/kyc`
- Env: `NIDA_API_URL`, `NIDA_API_KEY`, `CRB_API_*`

**Acceptance**
- Valid format accepted; invalid rejected
- Successful verification populates and locks name, DOB, gender
- Failed verification shows retry + manual review queue for admin

---

#### 2C.2 Face verification
**Requirements**

Before loan applications: capture
- Front face
- Left face
- Right face
- Selfie holding NIDA ID

Status: `pending` | `verified` | `rejected`

**Tasks**
- [ ] Migration: `face_verifications` or extend `customer_documents` with `angle` + `verification_status`
- [ ] Camera capture component (mobile-first; `getUserMedia` with file fallback)
- [ ] Block `ApplyController` until face verification `verified` (or `pending` allowed with banner — product decision)
- [ ] Admin review queue in `CustomerKycController`

**Acceptance**
- Four photos stored under KYC profile
- Admin can approve/reject with notes
- Borrower sees status in Profile → KYC

---

#### 2C.3 KYC freshness
**Requirements**

- Admin setting: **KYC Freshness** (e.g. 90 days)
- If exceeded: user must reconfirm Residence + Activity
- Profile updates automatically; may block new applications until complete

**Tasks**
- [ ] Setting in `admin/settings/kyc.blade.php` + `SettingsController`
- [ ] `KycFreshnessService` — compute stale date from last KYC approval or reconfirm
- [ ] Middleware or apply gate: prompt reconfirm flow
- [ ] Reconfirm form (residence + activity only)

**Acceptance**
- Setting editable in admin
- Stale users see prompt on dashboard and cannot apply until reconfirmed

---

#### 2C.4 Guarantor workflow

**Internal guarantor**
- Borrower enters membership ID (`KPF-TZ-XXXXX`)
- System sends approval request to guarantor (in-app + SMS/email)

**External guarantor**
- Invite via WhatsApp, SMS, Email
- Link: `{base_url}/guarantor/{token}` from Settings → Base URL

**External flow**
```
Accept → Registration → Membership Fee → KYC → Approval
Reject → Invite to become borrower
```

**Tasks**
- [ ] Migration: `guarantor_invitations` (token, channel, status, loan_application_id)
- [ ] `GuarantorInvitationService` — create link, notify
- [ ] Public routes: `/guarantor/{token}` accept/reject/register
- [ ] Internal: lookup member by `member_no`, create pending approval on guarantor account
- [ ] Wizard step: mandatory guarantor before submit
- [ ] Link guarantor records to `loan_application_id`

**Existing base**
- `guarantors`, `customer_guarantors` tables
- `borrower/guarantors.blade.php`, `BorrowerController`

**Acceptance**
- Application cannot submit without at least one approved guarantor (per loan rules)
- External invite completes full onboarding path

---

#### 2C.5 Digital signatures (application + agreement)
**Requirements**

Before underwriting submission:
- Borrower signs (name, signature image/data, timestamp)
- Guarantor signs separately
- Signatures populate loan agreement PDF

**Tasks**
- [ ] Migration: `application_signatures` (signer_type, name, signature_data, signed_at)
- [ ] Canvas signature pad component (or typed name + draw)
- [ ] Wizard final step before submit
- [ ] Extend `LoanAgreementService` / `pdf/offer-letter.blade.php` to embed stored signatures
- [ ] Guarantor signing route (linked from invitation)

**Existing**
- OTP signing in `LoanAgreementController.php` — keep for offer acceptance; add drawn signature layer

**Acceptance**
- Review screen shows “I confirm all information is correct” + sign
- PDF includes borrower + guarantor signature blocks

---

#### 2C.6 Application review screen (pre-submit)
**Requirements**

Summary sections:
- Product, Loan Amount, Tenure, Guarantor, Activity, Residence, Next of Kin

User confirms checkbox, then signs.

**Tasks**
- [ ] Dedicated review step in wizard (merge with signature step or separate)
- [ ] Read-only summary partials pulling from session/DB draft
- [ ] Checkbox + validation

**Acceptance**
- All sections visible; edit links back to relevant step
- Submit disabled until confirmed + signed

---

### Phase 2D — Underwriting & documents (1 sprint)

#### 2D.1 Additional document requests
**Requirements**

Underwriting can request:
- Additional documents
- Clarifications

Borrower sees **Requested Documents** with upload options:
- Camera, Gallery, PDF, Multi-page scan

**Tasks**
- [ ] Migration: `loan_application_document_requests` (application_id, label, instructions, status, due_at)
- [ ] Admin action on application show: “Request document”
- [ ] Borrower UI on `borrower/application.blade.php`
- [ ] Notification (SMS/email template)

**Acceptance**
- Admin creates ad-hoc request; borrower uploads; admin marks satisfied

---

## Configuration reference

| Setting group | Keys | Location |
|---------------|------|----------|
| `membership` | duration, fees, grace | `admin/settings/membership` |
| `kyc` | min_age, require_nida, require_selfie, freshness_days | `admin/settings/kyc` |
| `loan_rules` | min_guarantors, guarantor_required_above | `admin/settings/loan-rules` |
| `company` | base_url (for guarantor links) | `admin/settings/company` |
| Activity types | dynamic questions | `config/activity_profiles.php` (new) |
| Income ranges | bands | `config/income_ranges.php` (new) |
| Loan purposes | dropdown options | `config/loan_purposes.php` (new) |
| TZ locations | regions/districts/wards | `config/tanzania_locations.php` (new) |

---

## API / integration checklist

| Integration | Phase | Env vars | Notes |
|-------------|-------|----------|-------|
| NIDA bureau | 2C | `NIDA_API_*` | Interface first; stub until credentials |
| CRB | 2C | `CRB_API_*` | Triggered on NIDA verify + loan apply |
| SMS OTP | 2B | Existing gateway settings | PIN reset, guarantor invites |
| WhatsApp | 2C | Optional | Deep link share for external guarantor |

---

## Testing checklist

- [ ] Feature: PIN login, lockout, PIN reset OTP
- [ ] Feature: Apply wizard full path with kin, address, activity, guarantor, signature
- [ ] Feature: Admin bank payment approval (Phase 1 — done)
- [ ] Feature: NIDA verify mocks success/failure
- [ ] Feature: KYC freshness blocks apply when stale
- [ ] Feature: Ad-hoc document request upload cycle
- [ ] Unit: `MinimumAge` rule, `MemberNumberFormatter`, location cascade validation

---

## Suggested sprint order

```
Sprint 1 → 2A.1, 2A.2, 2A.3, 2A.8        (card copy, profile, age, applications toggle)
Sprint 2 → 2A.4, 2A.5, 2A.6, 2A.7        (address, kin, activity, wizard)
Sprint 3 → 2B.1, 2B.2                      (phone+PIN, trusted devices)
Sprint 4 → 2C.1, 2C.2                      (NIDA stub + face capture)
Sprint 5 → 2C.4, 2C.5, 2C.6                (guarantors + signatures + review)
Sprint 6 → 2C.3, 2D.1                      (KYC freshness + doc requests)
```

---

## Out of scope (Phase 3+)

- Native mobile app (Expo) — separate track; reuse API from Phase 2B
- Real M-Pesa / payment gateway (Phase 1 uses simulated mobile money)
- Full biometric WebAuthn production rollout
- Live CRB scoring display to borrower

---

*Last updated: 2026-05-27. Phase 1 commit baseline: admin membership payment approval (`16000f7` pending push/deploy).*
