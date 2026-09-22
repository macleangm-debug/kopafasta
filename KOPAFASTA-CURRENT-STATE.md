# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs (four distinct states — do not conflate)

| State | SHA | Note |
| --- | --- | --- |
| **Accepted production baseline** | `8bb9bf5e0a2e117e79db27459ce7a77f321af9d6` | Operational production baseline until live production is re-verified. Do not casually overwrite. |
| **Last locally observed production snapshot** | `91b9942a…` | Historical only (`parity/production-91b9942a`). Not a reason to roll staging backward. |
| **Current staging / development baseline** | `91c8ec0e198b114474f826a1ff5de08fea3032c7` | Steward released to Screening after KYC flags were aligned with production. John and Abdulatif still awaiting a guarantor. Document cosmetics unfinished. **Do not promote.** |
| **Clean production candidate** | **none** | Accepted Member 360/Legal IA is mixed with unfinished signature transparency and PDF cosmetics in `75eff6eb`, `eeb553e5`, and `ceb37ac8`. Do not promote those SHAs. `6cb7798c` is an older diverged candidate and is **not** an ancestor of the accepted Member 360 work. |
| **GitHub `origin/main`** | behind local tip | Do not force-catch-up with mixed WIP. |

**Production cut (2026-09-20):** SAFE TO PROMOTE **NO**. Current production baseline remains `8bb9bf5e`. Creating a file-level extract onto that baseline breaks signatory upload (methods added in the same mixed commits) and would omit or drag 94 commits of Profile, Support, Screening, Plus, and affordability that this cut did not approve.

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, product ordering, Sharia
- Registration password removed this pass (phone + PIN + incomplete draft resume)
- Document holder status cleanup, KYC 405, native Upload chooser, Remove confirmation
- Face focus/reload loop fix (preserve no-snap)
- Partner Experience Consistency (CLOSED)

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
