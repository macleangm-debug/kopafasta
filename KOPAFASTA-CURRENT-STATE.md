# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs

| Env | SHA | Note |
| --- | --- | --- |
| **Accepted production** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | C1 accepted-only baseline (do not casually overwrite) |
| **Failed staging (do not promote)** | `15bf3c71efd09214cba3f9c766d2abe385f1d9c57540` | AG/AB/DOC owner UAT FAIL |
| **Prior staging (do not promote)** | `95c36dadf39b3611c1d492e91b0ac0c4a034d60b` | Region/District HTML break + AG Continue FAIL |
| **Staging under UAT** | _(pending deploy)_ | FINAL P0: restore Region/District, Quote-style Continue, clean camera |

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`.

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, registration, product ordering, Sharia
- Document holder status cleanup, KYC 405, Upload autosave, multi-page PDF, Replace persistence, Remove confirmation

## FINAL P0 root causes

1. **Region/District missing:** `95c36dad` embedded `@js($regionName)` inside a double-quoted Alpine `x-data` attribute → truncated HTML → address controls broke.
2. **AG Continue:** same location break left `farming_region`/`farming_district` empty → footer gate hid Endelea. Overview readiness now uses Quote-style `KopaFastaForm.isComplete` on `data-agro-overview` + required docs.
3. **Ghost camera preview:** replace/delete reopened capture without resetting pages/preview; holders now pass `fresh: true` / `clear-capture`.

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
