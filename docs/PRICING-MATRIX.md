# Production vs Staging pricing policy (owner decisions 2026-09-05)

Settings Hub remains the **runtime** source of truth. This document records **owner-approved commercial policy**. Do not treat old seeder defaults as commercial tariffs.

**Do not initialize production** until Post-Approval sequence changes (if any) are approved and Settings are aligned.

## Asset products (confirmed distinct)

| Code | Name | Category | Staging app fee now | Owner production |
|---|---|---|---:|---:|
| **AB** | Asset-Backed Loan | asset (cash against collateral) | 25,000 | **25,000** |
| **AL** | Asset Lending | asset (own-the-asset / installment) | 5,000 | **50,000** |

These are **two different products**. Do not overwrite one with the other.

## Owner commercial matrix

| Charge | Staging / UAT | Production | Notes |
|---|---:|---:|---|
| Individual (IL) application | 1,000 | **10,000** | |
| Group (GL) application | 1,000 / member | **10,000 / member** | |
| Asset-backed (AB) application | 1,000 | **25,000** | code `AB` |
| Asset Lending (AL) application | 1,000 | **50,000** | code `AL` |
| Other loan products application | 1,000 | **10,000** | |
| Valuation | **1,000** | **50,000** | Settings-controlled; whole TZS |
| Kopafasta Plus | 1,000 | **36,000 / year** | May market as ~3,000/month; payable is annual unless monthly billing is deliberately added |
| Standard Affiliate **application** fee | 1,000 | **10,000** | **NEW** — gate before submit; separate from membership |
| Standard Affiliate membership — Individual | 1,000 | **30,000 / year** | Not Premium |
| Standard Affiliate membership — Company | 1,000 | **50,000 / year** | Not Premium |
| Operational Partner joining fee | None | **None** | |
| Disbursement fee | 1,000 | **10,000** | Timing: see Post-Approval audit before moving |
| Asset registration / transfer | 1,000 | **40,000** | |
| Origination | **1%** | **2%** | |
| Loan insurance (`INS_FEE`) | **1%** of principal | **1%** of principal | Post-approval catalog charge. **Not** comprehensive asset cover. |
| Comprehensive asset insurance | separate condition | **3.5%** of insured asset value | **AFTER approval**, outside Post-Approval fee bundle. AL basis = marketplace asset price. AB may verify existing cover (expiry ≥ maturity + Settings buffer, default **1 month**). Payment ≠ condition complete. |
| Restructuring | disabled | **Disabled**; future 10,000 | |
| Top-up | test-configurable | **10,000** when Top-up activated | |
| Early repayment | **0%** | **0%**, configurable | Collect principal + amounts legitimately due; no automatic early-settlement penalty |
| Late charge | configurable | **1%/day, max 30 days / 30% cumulative** | Cite exact regulatory basis before production label |

### Borrower membership

- Capability remains in the platform.
- **Country → Lending → Borrower Membership** master switch.
- **Tanzania: OFF** — no requirement, no payment, no renewal, no eligibility dependency.
- Historical membership records untouched.
- Configured amount alone must **not** enable membership; country permission is the switch.

### GPS (one calculator)

Production: **partner/base + 10% Kopafasta markup**.

| Component | Base | Markup | Borrower |
|---|---:|---:|---:|
| Installation | 50,000 | 10% | **55,000** |
| Monthly monitoring | 20,000 | 10% | **22,000 / month** |

Staging: reduced bases, **same 10% markup math**. Retire competing standalone `GPS_FEE` formulas; one Settings-controlled GPS pricing service.

### Affiliate Standard journey (target)

Application details → Application Fee → `payment.show` → verified → Submit → Review → Terms → Annual Membership → Activate.

Fee verified **before** submission for review. Payment ≠ approval. Reuse canonical `payment.show`. Premium Affiliate stays separate.

### Post-Approval presentation (target)

Calculate components separately for accounting; present **one** `payment.show` obligation with a transparent breakdown. Comprehensive collateral insurance stays **outside** that bundle.

**Sequence preserved:** Committee → Offer → Customer accepts → Post-Approval bundle → Contract → remaining operational conditions → Disbursement → Released → Active.

Do not move the disbursement fee after contract — it remains a line in the pre-contract Post-Approval payment.

### Recovery commercial model (owner clarification)

Percentage stages: **partner rate + platform rate of the same Recovery Base** (borrower sees the sum). Not markup-on-partner-fee.

| Stage | Partner | Platform | Borrower |
|---|---:|---:|---:|
| Call Center | 10% | 3% | **13%** |
| Debt Collector | 15% | 3% | **18%** |
| Auctioneer | 8% | 2% | **10%** (basis TBD) |

See `docs/RECOVERY-ARCHITECTURE.md` for live balance vs stage snapshot, SLA failure revenue, PTP, and stage-assignment ownership. Recovery code changes are backlog relative to staging pricing apply.

### Environment indicator

Admin Pricing / Settings: show **STAGING · TEST PRICING** vs production commercial tariff. Same engines; different Settings values.

### Snapshot rule

Every financial obligation snapshots its price/rate when created. Later Settings changes do not rewrite historical obligations.
