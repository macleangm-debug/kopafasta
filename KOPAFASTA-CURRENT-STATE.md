# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs (four distinct states — do not conflate)

| State | SHA | Note |
| --- | --- | --- |
| **Accepted production baseline** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | Operational production baseline until live production is re-verified. Do not casually overwrite. |
| **Last locally observed production snapshot** | `91b9942a…` | Historical only (`parity/production-91b9942a`). Not a reason to roll staging backward. |
| **Current staging / development baseline** | `545af64b008ebc7b1483c564fba79bc011c00188` | Platform autosave consistency: signature isolation, remaining Profile sections on `kfAutosave`, one canonical loader, no ordinary success modals, collateral disclosure EN/SW. **Staging UAT. Do not promote.** |
| **Current clean production candidate** | _(none yet)_ | After Identity UAT passes: cut from `8bb9bf5e…` with only owner-accepted changes → staging smoke → owner approve → promote that exact SHA. |
| **GitHub `origin/main`** | `bf292772…` (this machine) | Behind local staging tip. Do not force-catch-up with mixed WIP. |

**Typo note:** `73fbbfbe` does not exist — use `73fbbbfe`.

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`. Never push mixed staging tip to `origin/main` merely to sync GitHub.

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, product ordering, Sharia
- Registration password removed this pass (phone + PIN + incomplete draft resume)
- Document holder status cleanup, KYC 405, native Upload chooser, Remove confirmation
- Face focus/reload loop fix (preserve no-snap)

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
9. **Platform autosave (`kfAutosave`):** Idle → Saving… → ✓ Saved (server success only); fail → Retry. One shared primitive for Borrower + Partner shells — not per-role engines. Signature owns its own focus; never required on other Profile sections. No ordinary success modals; Profile Complete celebration once only.
10. **National ID:** one member-facing holder; Front → Back directed; landscape id-card guide; Replace picks side then source.

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
