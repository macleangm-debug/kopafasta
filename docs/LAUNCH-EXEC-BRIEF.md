# KopaFasta — Launch Executive Brief

**Date:** 4 July 2026 · **Production:** https://kopafasta.triptz.net · **Deploy:** commit `9302870`

---

## Verdict: Conditional Go

| Launch type | Ready? |
|-------------|--------|
| Controlled pilot (invite-only, manual payments) | **Yes — now** |
| Public soft launch (marketing + self-serve) | **After 1–2 weeks UAT + ops sign-off** |
| Full scale (live M-Pesa volume, SW parity guaranteed) | **Not yet** |

---

## What ships today

Digital lending platform: public site, borrower portal, admin console, marketplace/asset lending, membership, referrals, affiliate & investor portals, group loans, recovery & partner ops.

**Recent UX (Phases 1–6):** mobile bottom-sheet filters, numeric keypads, product-first apply flow, illustrations, landing A/B, profile+membership merge, dashboard quick actions, investor/affiliate polish.

**Enterprise (Phases 1–6 roadmap):** group status/scoring, recovery SLA, affiliate automation, fraud controls, staff portal, web 2FA.

---

## Quality snapshot

| Metric | Value |
|--------|-------|
| Automated tests | **478 / 503 pass** (95%) |
| Production status | **Live** (HTTP 200, assets built, caches warm) |
| Formal UAT sign-off | **Not completed** (checklist exists) |
| Live M-Pesa / card | **Partial** — manual/bank approval flows |

---

## Top risks (manage, don’t ignore)

1. **Payments** — many flows need admin approval or bank confirm; define ops SOP before users pay fees.
2. **UAT gap** — no signed product/tech approval; run P0 smoke + Journey A/B on production first.
3. **Swahili** — 2 test failures on lang parity; fine for EN-first pilot, not for SW-first launch.
4. **External guarantor** — contract PDF signature still partial.
5. **CRB/NIDA** — confirm production uses intended mode (sandbox vs live bureau).

---

## Pilot launch criteria (minimum)

- [ ] P0 smoke passed on production (see `LAUNCH-GO-NO-GO-CHECKLIST.md`)
- [ ] Company Profile base URL = `https://kopafasta.triptz.net`
- [ ] Dedicated support channel + ops runbook for payments
- [ ] Admin + credit officer accounts active
- [ ] `APP_DEBUG=false`, queue worker + cron running

---

## Recommended timeline

| Week | Action |
|------|--------|
| **Now** | Pilot with 10–50 invited borrowers; manual payment handling |
| **Week 1** | Full UAT (borrower + admin + asset journey); triage 9 test failures |
| **Week 2** | Product owner + technical sign-off; decide M-Pesa go-live |
| **Week 3+** | Soft public launch if UAT clean and payments SOP proven |

---

## Approvals

| Role | Name | Date | Decision |
|------|------|------|----------|
| Product owner | | | ☐ Pilot ☐ Delay |
| Technical lead | | | ☐ Pilot ☐ Delay |
| Operations | | | ☐ Manual payments OK ☐ Not ready |

---

*Supporting docs: `LAUNCH-GO-NO-GO-CHECKLIST.md` · `LAUNCH-UAT-EXECUTION.csv` · `UAT-FULL-SYSTEM.md`*
