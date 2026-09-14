# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs

| Env | SHA | Note |
| --- | --- | --- |
| **Accepted production** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | C1 accepted-only baseline (do not casually overwrite) |
| **Failed staging (do not promote)** | `15bf3c71efd09214cba3f9c766d2abe385f1e6ed` | AG/AB/DOC owner UAT FAIL |
| **Staging under UAT** | `08f358df3062068ff57fb54bd7556ef1d9c57540` | P0 mobile docs + AG Continue + Profile save (AB frozen) |
| **Prior staging (superseded)** | `83d8a820df78e8b8fd4fce41c32d1aa8a4c9848f` | Combined micropass — AB PASS; AG Continue / DOC FAIL |

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`.

## Frozen (do not touch in the current correction)

- **AB-1** — Asset-Backed step graph, collateral-card, Profile `return_to`, insurance-not-an-apply-stage
- PayIn / accounting / Marketplace / registration / product ordering / Sharia / guarantor·group **policy** / underwriting·recovery policy

## Open defects (P0)

1. **Mobile Upload/Camera** — bottom sheet opens; actions must open file picker / camera (shared `document-source-picker`)
2. **AG-2 Continue** — UI can show 2/2 required docs while footer Continue stays hidden → Mdhamini
3. **Profile document Save** — Replace/Camera needs Save/Use; no false ✓; no IMEFANIKIWA / Hifadhi mabadiliko? modal
4. **Desktop + menu clipping** — Upload/Camera popover must not be cut by card overflow

## UAT matrix (latest owner)

| Item | Status |
| --- | --- |
| AG-1 Overview persistence | PASS (partial — blocked by mobile upload) |
| AG-1 mobile document action | FAIL |
| AG-2 Agriculture Continue | FAIL |
| AB-1 | **PASS — FREEZE** |
| DOC-1 / Profile document persistence UX | FAIL |
| Staging performance | Needs attention (non-blocking vs P0) |

## Canonical component map (reuse; do not duplicate)

| Need | Canonical |
| --- | --- |
| + Upload / Camera | `x-site.document-source-picker` → window `document-source` |
| Multi-page → one PDF | `x-site.multi-page-document-upload` |
| Single image / photo | `x-site.single-image-document-upload` (+ Save/Use when source-driven) |
| Profile doc holder | `x-site.profile-document-field` |
| Apply doc holder | `site.apply._apply-document-holder` |
| Inline upload progress | `data-inline-document-progress` + saving-overlay skip |
| AB asset | `x-site.collateral-card` + Profile asset |
| Region/District | `x-site.address-fields` / profile-select |
| Wizard Continue gate | `apply-wizard.js` → `isCurrentStepReady()` + `wizard-footer` |
| Profile completion | `ProfileCompletionService` (one formula) |
| Mobile sheet / desktop popover | `x-site.bottom-sheet` / teleported menus |

## Low-cost development mode

Do not ask an agent to rediscover architecture from the whole repository for every micropass.

For borrower / Profile / application work, read only:

1. This file (`KOPAFASTA-CURRENT-STATE.md`)
2. Current micropass instruction
3. When available in the handoff package: `00-START-HERE-COMPREHENSIVE.md`, `01-BORROWER-DEVELOPER-BRIEF.md`, `kopafasta-borrower-shell-audit.md`, `kopafasta-profile-plus-deep-review.md`

Then inspect **only the code paths named by the defect**.

- One micropass = one primary workstream
- Prefer cheaper models for locate/grep/tests/mechanical edits
- Escalate reasoning only when canonical systems conflict, policy is ambiguous, or a defect remains unexplained after direct tracing

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
