# Copperfasta Enterprise Roadmap

Strategic specification for maturing Copperfasta from a feature-rich microfinance platform into an enterprise-grade digital lending system. Business rules remain configurable through **Settings** rather than hardcoded logic.

**Last updated:** June 2026  
**Status:** Phases 1–6 complete

---

## Current Platform Baseline

| Area | Built | Remaining gaps |
|------|-------|----------------|
| Group loans | Wizard, invitations, member progress, admin per-member review, contract signatures, **status engine**, **group scoring** | — |
| Recovery | Partner types, SLA, auto-escalation chain, partner case portal, **SLA matrix**, **KPI dashboard**, **commission wallet** | Deeper portfolio-level recovery analytics (optional) |
| Portals | `/admin`, `/staff`, `/partner`, `/affiliate`, `/investor`, `/borrower` | — |
| Auth | Phone+PIN, email+password (borrowers); TOTP API + **web 2FA** (admin/staff/partner) | — |
| Affiliates | Codes, tracking, discount≠commission, accrual/settlement, **dedicated portal**, **UTM attribution**, **tiered/hybrid commission**, **wallet**, **lifecycle**, **fraud controls**, **marketing reports** | — |
| Capital partners | Allocations, splits, investor portal, **affiliate → loan → capital attribution report** | — |

---

## Phase 1 — Group Loan Workflow ✅

*Implemented June 2026 — `GroupApplicationStatusService`, `GroupScoringService`, wizard + admin wiring, feature tests.*

### 1.1 Group Application Status Engine

Leader- and admin-facing status that sits **above** the individual `LoanApplication` underwriting pipeline.

| Status | When |
|--------|------|
| `draft` | Group setup incomplete (name, purpose, or member count missing) |
| `inviting_members` | Setup complete; members still being added or invitations outstanding |
| `member_completion` | All slots filled; members completing profile/KYC |
| `ready_for_submission` | All members KYC-complete; leader can submit |
| `under_review` | Submitted; in underwriting (`submitted` → `pre_approval`) |
| `approved` | Final approval granted; not yet disbursed |
| `rejected` | Application rejected |
| `disbursed` | Loan disbursed |
| `cancelled` | Withdrawn or cancelled |

**Implementation:** `GroupApplicationStatusService` — computed for wizard drafts; persisted on `loan_groups.application_status` after submission; synced on workflow transitions.

### 1.2 Group Scoring (Pre-Submission)

Computed before submission to support underwriting:

| Metric | Description |
|--------|-------------|
| Member completion % | Weighted profile + KYC progress across members |
| Average credit score | Mean CRB score of members with data |
| Average income | Mean monthly income (or income-range midpoint) |
| Group risk score | 0–100 composite (higher = lower risk) |

**Implementation:** `GroupScoringService` — exposed in apply wizard, admin group review, and stored in `loan_groups.scoring_snapshot` at submission.

### 1.3 Existing (Phase 1 scope — no rebuild)

- Member invitation mapping (`GroupMemberInvitationService`)
- Per-member progress (`GroupMemberProgressService`)
- Admin per-member underwriting review (`GroupLoanMemberReviewService`)

---

## Phase 2 — Recovery Engine & Partner Portal ✅

*Implemented June 2026 — SLA matrix settings, `RecoveryPartnerKpiService`, `RecoveryCommissionWalletService`, partner portal UI, feature tests.*

### 2.1 Recovery Partner SLA Settings

**Settings → Recovery Management** — per-partner row:

| Field | Example |
|-------|---------|
| Partner type | Call Center |
| Priority | 1 |
| Loan types | All / specific products |
| Collateral types | All / secured only |
| SLA days | 7 |
| Commission % | 10 |
| Auto escalation | Yes |

*Partially exists in `config/recovery.php` and admin recovery settings; extend UI for full matrix.*

### 2.2 Recovery Workflow Engine

```
Current DPD
    ↓
Check grace period
    ↓
Assign partner (priority 1)
    ↓
Track SLA (sla_due_at)
    ↓
Escalate on breach (hourly: recovery:escalate-expired-slas)
    ↓
Next partner in chain
```

*Core engine exists (`RecoveryEscalationService`, `RecoveryAutoAssignmentService`). Phase 2 adds settings granularity and reporting.*

### 2.3 Recovery Partner Portal Enhancements

| Feature | States |
|---------|--------|
| KPI dashboard | Assigned cases, recovered cases, recovery rate, commission earned, avg resolution time |
| Commission wallet | Pending → Approved → Paid → Disputed |

*Cases and SLA tracking exist at `/partner/recovery-cases`; economics UI is new.*

---

## Phase 3 — Affiliate Portal & Commission ✅

*Implemented June 2026 — dedicated `/affiliate` portal, UTM attribution, tiered/hybrid commission engine, commission wallet with disputes.*

### 3.1 Dedicated Affiliate Experience

Split affiliate UI from unified `/partner` portal. Routes: `/affiliate` and `/partner/affiliate` (legacy partner home redirects affiliates here).

### 3.2 Referral Code Framework

Extend `AffiliateEvent` / attribution with:

- Affiliate ID, referral code, campaign
- Landing page, device fingerprint, IP
- UTM source, UTM campaign

*Captured via `AffiliateAttributionService` on `/aff/{code}` and registration.*

### 3.3 Commission Engine — Tiered & Hybrid

Support **fixed**, **percentage**, **tiered**, and **hybrid** structures:

```
1–10 registrations   → 1,000 TZS each
11–50 registrations  → 1,500 TZS each
50+                  → 2,000 TZS each
```

*Configured in Admin → Settings → Affiliates; calculated by `AffiliateCommissionCalculatorService`.*

### 3.4 Borrower Discount Framework

Discount and commission remain **independent**:

```
Registration fee:     10,000 TZS
Borrower discount:     2,000 TZS  → Borrower pays 8,000 TZS
Affiliate commission:  1,000 TZS  → Platform retains 7,000 TZS
```

*Already implemented via `AffiliateService::quoteFee`.*

### 3.5 Commission Wallet (Partner-Facing)

Pending → Approved → Paid → Disputed — surfaced at `/affiliate/wallet` via `AffiliateCommissionWalletService`.

---

## Phase 4 — Affiliate Automation ✅

*Implemented June 2026 — lifecycle states, monthly evaluation command, automated watchlist/suspend, leaderboard.*

### 4.1 Affiliate Lifecycle

| State | Trigger |
|-------|---------|
| Pending KYC | Application submitted |
| Active | KYC approved |
| Watchlist | Manual or automated flag |
| Suspended | Policy breach |
| Terminated | Permanent removal |

*Managed via `AffiliateLifecycleService`; admin can override on partner profile.*

### 4.2 Monthly Evaluation Job

```bash
php artisan affiliate:evaluate
```

Calculates KPI score, risk score, fraud score; creates admin recommendations. Scheduled monthly (1st at 06:00). Options: `--dry-run`, `--no-apply`, `--month=YYYY-MM`.

### 4.3 Suspension Rules & Ranking Engine

Automated watchlist/suspend based on evaluation thresholds (Admin → Settings → Affiliates). Affiliate leaderboard ranked by KPI score on admin affiliates page and affiliate dashboard.

---

## Phase 5 — Advanced Analytics & Fraud ✅

*Implemented June 2026 — UTM marketing reports, fraud detection with device fingerprints, capital attribution chain.*

### 5.1 Marketing Attribution

Reports from UTM + referral source data at **Admin → Marketing attribution** (`/admin/reports/affiliate-marketing-attribution`).

### 5.2 Affiliate Fraud Controls

- Device fingerprinting on affiliate events (`AffiliateDeviceFingerprintService`)
- Self-referral, multi-account, shared device/phone/NIDA detection (`AffiliateFraudDetectionService`)
- Risk flags: Low / Medium / High / Blocked — auto-suspend on blocked
- Weekly scan: `php artisan affiliate:scan-fraud`

### 5.3 Capital Partner Attribution Report

Chain: **Affiliate → Borrower → Loan → Capital Partner** at **Admin → Capital attribution** (`/admin/reports/affiliate-capital-attribution`).

---

## Portal & Authentication Architecture

| Portal | Path | Audience | Status |
|--------|------|----------|--------|
| Admin | `/admin` | Back-office staff | ✅ Built |
| Staff | `/staff` | Limited-permission staff | ✅ Built |
| Partners | `/partner` | Recovery, origination partners | ✅ Built |
| Affiliate | `/affiliate` | Referral partners | ✅ Built |
| Investor | `/investor` | Capital partners | ✅ Built |
| Borrower | `/borrower` | Loan customers | ✅ Built |

### Authentication

| Method | Borrower | Staff/Partner |
|--------|----------|---------------|
| Phone + PIN | ✅ | — |
| Email + password | ✅ | ✅ (admin) |
| 2FA (TOTP) | — | ✅ Web enforcement for admin, staff, and partner login |

---

## Development Priority Summary

| Phase | Focus | Status |
|-------|-------|--------|
| **1** | Group status engine + group scoring | ✅ Complete |
| **2** | Recovery SLA matrix UI + partner KPI/wallet | ✅ Complete |
| **3** | Affiliate portal split + referral attribution + tiered commission | ✅ Complete |
| **4** | `affiliate:evaluate` job + lifecycle automation | ✅ Complete |
| **5** | Fraud controls + marketing analytics + capital attribution reports | ✅ Complete |
| **6** | Staff portal + web 2FA enforcement | ✅ Complete |

### Suggested next (outside Phases 1–6)

| Item | Notes |
|------|-------|
| Recovery analytics | Portfolio-level trends beyond partner KPIs |

---

## Configuration Principle

All business rules (SLA days, commission tiers, scoring weights, suspension thresholds) live in:

- `config/*.php` defaults
- **Settings** admin UI (`settings` table groups)
- Never hardcoded in controllers

See `config/group_application.php` for Phase 1 group status and scoring weights.
