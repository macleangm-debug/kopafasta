# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs

| Env | SHA | Note |
| --- | --- | --- |
| **Accepted production** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | C1 accepted-only baseline (do not casually overwrite) |
| **Failed staging (do not promote)** | `15bf3c71efd09214cba3f9c766d2abe385f1d9c57540` | AG/AB/DOC owner UAT FAIL |
| **Prior staging (do not promote)** | `bdedd6f10059fb5e4945517809dd27596d604326` | Mixed P0 — AG Continue / KYC still open |
| **Staging under UAT** | `51f205454122242cfc1d48abad584330e803ef05` | Economical P0: AG Continue + KYC 405 + canonical multi-page camera |
| **Prior staging (superseded)** | `83d8a820df78e8b8fd4fce41c32d1aa8a4c9848f` | Combined micropass — AB PASS; AG Continue / DOC FAIL |

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`.

## Frozen (do not touch in the current correction)

- **AG-1** — Agriculture overview persistence (accepted)
- **AB-1** — Asset-Backed step graph, collateral-card, Profile `return_to`, insurance-not-an-apply-stage
- PayIn / accounting / Marketplace / registration / product ordering / Sharia / guarantor·group **policy** / underwriting·recovery policy

## Open defects (P0) — awaiting owner UAT after this deploy

1. **AG-2 Continue** — Overview + farm + land ready → footer Ghairi → Endelea → Mdhamini (no refresh)
2. **Profile/KYC 405** — Camera/Upload must POST/PUT to profile update without Method Not Allowed
3. **Canonical multi-page camera** — default for normal documents; exceptions: facial, NIDA directive, collateral/farm photos
4. **Document badges** — Required disappears once supplied; Pending review only

## Canonical camera rule

- **Normal document** → `x-site.multi-page-document-upload` (pages → one PDF)
- **Directive photograph** → specialized single/image capture (NIDA sides, face, collateral, farm activity photos)

## Canonical component map (reuse; do not duplicate)

| Need | Canonical |
| --- | --- |
| + Upload / Camera | `x-site.document-source-picker` → window `document-source` |
| Multi-page → one PDF | `x-site.multi-page-document-upload` |
| Single image / photo | `x-site.single-image-document-upload` |
| Profile doc holder | `x-site.profile-document-field` |
| Apply doc holder | `site.apply._apply-document-holder` |
| Wizard Continue gate | `apply-wizard.js` → `isCurrentStepReady()` + `wizard-footer` |
| Profile update | `PUT|POST /borrower/profile/{section}` → `updateProfile` |

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
