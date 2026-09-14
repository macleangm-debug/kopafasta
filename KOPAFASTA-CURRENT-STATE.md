# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs

| Env | SHA | Note |
| --- | --- | --- |
| **Accepted production** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | C1 accepted-only baseline (do not casually overwrite) |
| **Failed staging (do not promote)** | `15bf3c71efd09214cba3f9c766d2abe385f1d9c57540` | AG/AB/DOC owner UAT FAIL |
| **Prior staging (do not promote)** | `51f205454122242cfc1d48abad584330e803ef05` | Docs PASS; AG-2 FAIL (region/district not reaching footer gate) |
| **Staging under UAT** | `(pending deploy)` | Final closure: AG-2 location harvest + member badges removed |
| **Prior staging (superseded)** | `83d8a820df78e8b8fd4fce41c32d1aa8a4c9848f` | Combined micropass — AB PASS; AG Continue / DOC FAIL |

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`.

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, registration, product ordering, Sharia

## AG-2 root cause (staging draft #137, product KB)

Overview fields + farm/land docs were persisted, but `farming_region` / `farming_district` were **null** in draft inputs. Footer gate requires region+district → Continue stayed hidden (Ghairi only).

Fix: harvest Alpine address state into draft; mobile sheet picks emit change; readiness uses `form.elements` + Alpine address scan.

## Member document holders

No member-facing status badges (Required / Pending / Optional). Backend verification status unchanged.

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
