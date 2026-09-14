# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs

| Env | SHA | Note |
| --- | --- | --- |
| **Accepted production** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | C1 accepted-only baseline (do not casually overwrite) |
| **Prior staging (do not promote)** | `d748536efc6a18062ec0fd67a818bb855dd04299` | FINAL economical: AG Region/District, Quote Continue, canonical camera, Replace |
| **Staging under UAT** | `ff7bb132add5e792d825d3880064c0e9b09b6fed` | P0: face focus loop + ID image completion |

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`.

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, registration, product ordering, Sharia
- Document holder status cleanup, KYC 405, native Upload chooser, Remove confirmation

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
9. **Profile autosave:** Saving… → ✓ Saved; no ordinary success modals. Signature holder = presentation only.

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
