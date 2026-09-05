# Recovery architecture (owner policy — recorded 2026-09-05)

Specification only until implementation is sequenced. **Does not block staging UAT or production DNS.** Supplier and Capital Partner are outside this document.

## Case ownership

**Kopafasta owns the Recovery Case.** Partners receive **stage-specific assignments**, not ownership of the whole chain.

Partner profile capabilities (eligibility only):

- Call Center
- Debt Collection
- Repossession
- Auctioneering

Having a capability does **not** guarantee the next stage. After each stage ends, control returns to the Kopafasta assignment engine (eligible weighted pool: capability, agreement, geography, capacity, SLA eligibility, no suspension). Same-partner consecutive assignment only if the engine selects them again.

Canonical flow:

**Call Center → Debt Collection → Repossession → Auction Preparation → Auction**

## Commercial rates (borrower sees partner + platform)

**Universal rule:** every recovery/support partner stage has a partner component **and** a Kopafasta markup/platform component. Zero markup is only a Settings value — never a hard-coded permanent exception (including Legal).

Borrower-facing recovery charge = partner component + Kopafasta platform component. Platform revenue never enters the partner wallet.

Applies to: **Call Center · Debt Collector · Repossession · Auctioneer · Legal · Towing · Yard/Storage** (and any later priced recovery stage).

Models may differ by stage (percentage, fixed, asset-type matrix, hybrid, daily storage) but each must expose configurable partner rate/fee, Kopafasta markup, calculation basis, borrower total, partner earning, and platform revenue.

| Stage | Partner (current defaults) | Platform (current defaults) | Borrower sees |
|---|---:|---:|---:|
| Call Center | 10% | 3% | **13%** of recovery base |
| Debt Collector | 15% | 3% | **18%** of recovery base |
| Auctioneer | 8% | 2% | **10%** (basis TBD — balance vs realization) |
| Repossession | Asset-class partner cost | Markup % by asset class | Partner + markup |
| Legal | TZS 100,000 fixed | **0% currently** (configurable) | Partner + markup |
| Towing | Unconfigured fixed | Markup configurable (default 10% when enabled) | Partner + markup |
| Yard / storage | Unconfigured **daily** partner rate | Daily markup % or fixed | Daily charge × days in custody |

### Yard / storage ledger fields (prospective accrual)

`partner_daily_rate` · `platform_markup` · `borrower_daily_charge` · `storage_start_at` · `storage_end_at` · `days_charged` · `total_partner_amount` · `total_platform_revenue` · `total_borrower_charge`

Invariant: `partner_amount + platform_amount = borrower_stage_charge`. Platform portion never enters partner wallet.

## Three amounts (must not collapse into one)

1. **Live borrower balance** — from ledger; moves with payments, penalties, valid charges.
2. **Stage calculation base** — snapshotted at assignment: eligible underlying debt + eligible accrued penalties; **excludes** prior recovery-stage charges/platform fees.
3. **Live collection target** — whole amount the assigned partner must collect (may include prior valid recovery charges + live penalties).

Partner portal must show all three with explanatory copy.

## Assignment vs earning

- Assignment: snapshot commercial terms; create borrower stage charge; partner allocation **Pending**.
- Success / partial recovery: partner earns from **amount actually recovered in that stage** (proportional); never commission twice on the same money.
- SLA failure: partner earns **0**; borrower charge already posted **remains**; failed partner portion → **Kopafasta revenue** (reclassify with audit); next stage gets a **fresh** calculation-base snapshot.

Do not calculate next stage % on previous recovery charges. Do not stack fee-on-fee into the **calculation base**.

## Promise-to-Pay / Pay Now

Call Center and Debt Collector: **Send Pay Now** → Promise-to-Pay record → borrower CTA → canonical **`payment.show`** for the agreed amount only.

- Timer expires the **request**, not the debt.
- One active PTP per case by default.
- Partial payment ≠ restructuring; allocation uses existing repayment waterfall.
- PTP amount is a snapshot; do not silently raise it if penalties move during the timer.

## Repossession / Auction

- Repossession: own stage, asset-type matrix, GPS coordination internally, evidence/SLA; do not expose internal ops playbook to borrower; keep required legal notices.
- Auction: not automatic on repossession complete; grace/notice/advertising/authorization/proceeds.

## Anti-gaming

Stage progression is policy/SLA/evidence-driven. Partners cannot self-escalate for commercial gain. Manual early escalation needs Kopafasta approval + reason. Track escalation rates per capability for Admin review.

## Current code audit (2026-09-05) — before changing recovery

| Question | Finding |
|---|---|
| Calculation basis | `recovery.fee_base`: `principal` (default) or `outstanding` at assignment (`RecoveryAssignmentService`). Comment: not compounded from commission itself. |
| What does 3% mean for **percentage** fee type? | **`base × markup%`** — same base as partner commission. Call Center 10%+3% → borrower **13% of base**. Matches owner model. |
| What does 3% mean for **fixed/hybrid**? | Markup is **`partner_amount × markup%`** (different). Legal fixed 100k uses this path with 0% markup. |
| When borrower charge posts | **On assignment** — `LoanFee` `RECOVERY_{partnerType}` via `accrueRecoveryFee`. |
| When partner earning posts | **On complete only** — `PartnerSettlementService::accrue(..., recovery_commission)`. Escalate cancels task; no wallet credit. |
| SLA failure finances | Escalate sets status escalated; **does not reverse** borrower `RECOVERY_*` fee; partner gets 0. Next stage creates a **new** `RECOVERY_{next}` fee (charges can stack on borrower). |
| Does next stage % include prior recovery fees in base? | If `fee_base=principal` → uses approved/principal amount → **prior recovery fees excluded from % base**. If `fee_base=outstanding` → depends whether outstanding includes recovery fees (risk of compounding). Default is principal. |
| Partial recovery commission | Not modeled as proportional-to-recovered today; commission snapshot stored as full `commission_earned` at assign, paid in full on complete. |
| Stage assignment model | Escalation chain exists; not yet independent capability-based random assignment across firms. |
| GL | Recovery charge posts via LoanFee path; partner payable via settlements — confirm exact GL accounts before production. |

### Penalty engine (consume existing — do not fork)

| Question | Finding |
|---|---|
| Rate | Default **1% per day** (`LoanPenaltyPolicy` / Settings `loan.default_penalty_rate`) |
| Basis of daily amount | First overdue installment remaining (`total_due − amount_paid`) via `perDayPenaltyAmount` |
| Day start | After grace days from first overdue due date (`grace + 1`) |
| Cap | Cumulative **30%** of overdue schedule balance (`penalty_cap_percent`, hard max 30) |
| Continues during recovery? | Yes — `loans:accrue-late-fees` is independent of recovery assignments |
| Regulatory label | Treat as **owner policy provisional** until exact BoT/legal authority is cited |

## Owner clarifications locked (do not block staging pricing)

- Failed-stage borrower charge **remains**; unsuccessful partner portion → Kopafasta revenue (reclassify with audit).
- Next stage % uses a **fresh eligible base** excluding prior recovery charges.
- Partner earning from **amount actually recovered** in that stage (proportional); never commission twice on the same money.
- **Kopafasta owns the Recovery Case**; partners receive independent stage assignments from an eligible weighted pool.
- Auctioneer commercial basis (balance vs realization) remains **unlocked** pending financial mechanics review.
- PTP / Pay Now for Call Center & Debt Collector: backlog after commercial pricing apply.
- SLA engine and commercial engine stay separate.

## Implementation order

1. Do not rewrite historical recovery rows when changing Settings.
2. Align percentage-stage semantics and snapshots to this doc (percentage path already matches 10+3=13).
3. PTP / capability assignment / anti-gaming as backlog after staging commercial pricing.
4. Auctioneer basis (outstanding vs realization) — owner decision after mechanics review.
