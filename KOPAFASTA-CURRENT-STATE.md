# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs (four distinct states — do not conflate)

| State | SHA | Note |
| --- | --- | --- |
| **Live production (frozen)** | `03beb59bbc6625f2d866bb2d3e0b405ca04dc5c1` | Payment.show closed. MU8Q restored/closed. **Do not promote Affiliate staging tip here yet.** |
| **Accepted production baseline (Release 2 freeze)** | `0b292c681f5c110d33bcc8fe324c9c618ea5b0ac` | Release 2 production-closed ancestry root. |
| **Last locally observed production snapshot** | `91b9942a…` | Historical only (`parity/production-91b9942a`). Not a reason to roll staging backward. |
| **Affiliate staging baseline (Owner UAT)** | `ee5148c5ecd34c4ec12c6b44dcc68f240776982b` | Prior Performance freeze tip. **Superseded for contracts re-acceptance — see tip below.** |
| **Affiliate staging tip (contracts re-accept)** | `50665e33a2351c272976f8132dd13a9a02cf9c67` | Owner EN/SW pack v2; existing Affiliates on older acceptance must re-accept. Historical signed v1 retained. |
| **Affiliate commercial closure (staging tip)** | `6ddc3077` | Commission remaining-amount basis + Accounting earn/payout + public apply fee ON/OFF via payment.show body. Agreement content_revision 3. Staging only. |
| **Affiliate application UAT blockers (staging tip)** | `8af71a3b` | Public apply mobile carousel, Other same-surface, DOB/gender, single NIDA camera, declarations + completeness gate, contract content_revision 4, Verify Card Affiliate safety. Accounting/commission frozen. Staging only — Owner restarts Affiliate apply UAT. |
| **Clean production candidate** | `6cb7798c67cc5ccc4581c00c7339155f07a203ea` | Branch `release/production-candidate-accepted`: `4db04d90` + Activity dropdown teleport only. **Excludes Support V1 and latest Family/Kin work. STOP for owner approval before production.** |
| **GitHub `origin/main`** | `bf292772…` (this machine) | Behind local staging tip. Do not force-catch-up with mixed WIP. |

**Typo note:** `73fbbfbe` does not exist — use `73fbbbfe`.

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`. Never push mixed staging tip to `origin/main` merely to sync GitHub. **Do not promote staging HEAD wholesale.**

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, product ordering, Sharia
- Registration password removed this pass (phone + PIN + incomplete draft resume)
- Document holder status cleanup, KYC 405, native Upload chooser, Remove confirmation
- Face focus/reload loop fix (preserve no-snap)
- Partner Experience Consistency (CLOSED) except this owner-authorized Premium Affiliate commercial pass
- **Release 2 lending** (First Gate, Parked Screening, auto-rejection, Screening wizard, System Sorted, receipts, Borrower Loans IA, application restoration). Do not start Steward. **APP-EM-MU8Q restored/closed — do not re-restore.**
- **Payment.show** — closed on production `03beb59b`
- **Affiliate Performance** — PASS/freeze on staging `ee5148c5` (do not polish further unless a visual walkthrough finds a real defect)
- Recovery, Supplier, Valuer, Capital Partner, payment posting/provider logic

## Premium Affiliate (current isolated pass)

Settings Hub → Affiliates → Rates is the **one** default for Standard and Premium. Only Premium may store an individual negotiated override. Affiliate 360 is the commercial-arrangement card. The existing contract renderer snapshots the effective terms. No second portal, commission engine, or Premium rate configuration.

**Owner UAT focus (staging `ee5148c5`):** Home → Performance → Share & Earn → Reports → Profile/360 → Agreement/contract → Wallet/withdrawal → notifications. Watch Premium classification, negotiated-vs-Settings rates, minimum withdrawal, EN/SW, mobile, dark mode. Contracts implemented; finish walkthrough before any production promote. Accumulate genuine defects only — no further Performance polish.

## Canonical rules (this pass)

1. **Region → District:** `x-site.address-fields` only (Profile-identical: mobile bottom sheet + desktop select). AG Overview hides ward/street.
2. **Continue:** Quote pattern — required Overview (`Region`, `District`, activity fields) + farm/land docs → footer Endelea. Optional docs never block.
3. **Three camera families only:**
   - **Shared general shell** `x-site.multi-page-document-upload` — Profile docs, AG docs, farm/activity photos, residence/income/supporting/guarantor/group/partner ordinary Camera CTAs.
   - **Identity directive** — facial/selfie + NIDA front/back (`mode=single` / `capture=selfie|nida`).
   - **Collateral directive** — asset photographs.
4. **Same shell, output by field:** `outputMode=pdf` → one PDF; `capture=images` / `outputMode=images` → image collection (no forced PDF).
5. **Replace:** never asks document type — `startReplace()` → Upload/Camera. Only **Ongeza hati** opens the type picker.
6. **Clean capture:** `fresh: true` / `clear-capture` / `resetCapture()`.
7. **Camera UI:** bottom control area (shutter / facing / add / orientation) + portrait/landscape dotted guide; Finish primary.
8. **NIDA number:** confirm before lock; images remain replaceable. Face: replaceable + Remove uses confirmForm.
9. **Platform Profile UX (frozen pattern):** Collapsed → View all saved fields → Hariri/Edit → `kfAutosave`. One top-centre green tab only (`Inahifadhi…` / ✓ `Imehifadhiwa` / `Haijahifadhiwa · Jaribu tena`). No inline Saved labels. Autosave every valid change (partial OK); Complete ≠ Saved. Signature owns its own focus.
10. **National ID:** one member-facing holder; Front → Back directed; landscape id-card guide; Replace picks side then source.
11. **Public company contact:** one primary email + one primary phone from Settings Hub Company Profile → `support_emails()` / `support_phones()` → footer.
12. **Support V1:** `SupportTicket` + guest fields + round-robin `agent` assignment + `support_ticket_events`. No SupportUser. Staging only until UAT.

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
