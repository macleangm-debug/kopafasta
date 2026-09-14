# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs

| Env | SHA | Note |
| --- | --- | --- |
| **Accepted production** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | C1 accepted-only baseline (do not casually overwrite) |
| **Prior staging (do not promote)** | `237a4bfc430fecd278773c625e9af0251015cfe1` | address-fields @js() comment hotfix |
| **Staging under UAT** | _(pending deploy)_ | FINAL economical: AG Region/District, Quote Continue, canonical camera, Replace |

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`.

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, registration, product ordering, Sharia
- Document holder status cleanup, KYC 405, native Upload chooser, Remove confirmation

## Canonical rules (this pass)

1. **Region → District:** `x-site.address-fields` only. AG Overview hides ward/street (`show-ward`/`show-street` false) so Region/District stay visible.
2. **Continue:** Loan Quote pattern — required Overview complete (`KopaFastaForm.isComplete` on `data-agro-overview`) + farm/land docs → footer Endelea.
3. **Ordinary document camera:** shared `x-site.multi-page-document-upload` via holders (`profile-document-field` default `mode=multi`, apply holder default multi-page). Directive photo flows stay `mode=single` / `capture=images`.
4. **Replace:** never asks document type — `startReplace()` → Upload/Camera with type already on the form. Only **Ongeza hati** opens the type picker.
5. **Clean capture:** `fresh: true` / `clear-capture` / `resetCapture()` at shared camera.
6. **Camera UI:** icon shutter/facing/add/rotate + portrait/landscape dotted frame guide (no crop).

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
