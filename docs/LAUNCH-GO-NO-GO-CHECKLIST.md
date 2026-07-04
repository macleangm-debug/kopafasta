# KopaFasta — Production Go / No-Go Checklist

**URL:** https://kopafasta.triptz.net  
**Tester:** _______________ **Date:** _______________

Mark each item **Pass / Fail / N/A**. **Go** requires all **Blocker** items Pass and no more than 2 **Major** fails with documented workarounds.

**Legend:** 🔴 Blocker · 🟠 Major · 🟡 Minor

---

## A. Infrastructure (15 min)

| # | Sev | Check | Pass | Fail | Notes |
|---|-----|-------|:----:|:----:|-------|
| A1 | 🔴 | Site loads over HTTPS (no cert warnings) | ☐ | ☐ | |
| A2 | 🔴 | `APP_DEBUG=false` on server | ☐ | ☐ | |
| A3 | 🔴 | Admin → Settings → Company Profile → base URL correct | ☐ | ☐ | |
| A4 | 🔴 | Queue worker running (Supervisor) | ☐ | ☐ | |
| A5 | 🟠 | Cron `schedule:run` every minute | ☐ | ☐ | |
| A6 | 🟠 | `public/storage` symlink exists | ☐ | ☐ | |
| A7 | 🟡 | Nginx `client_max_body_size` ≥ 25M (uploads) | ☐ | ☐ | |

---

## B. P0 smoke — borrower (45 min)

| # | Sev | Check | Pass | Fail | Notes |
|---|-----|-------|:----:|:----:|-------|
| B1 | 🔴 | Register new borrower (phone + PIN) | ☐ | ☐ | |
| B2 | 🔴 | Login with PIN | ☐ | ☐ | |
| B3 | 🔴 | Complete profile (personal, residence, KYC docs) | ☐ | ☐ | |
| B4 | 🔴 | NIDA verify (sandbox number if stub mode) | ☐ | ☐ | |
| B5 | 🟠 | Face verification camera on mobile HTTPS | ☐ | ☐ | |
| B6 | 🔴 | Browse `/loans` → open product → start apply | ☐ | ☐ | |
| B7 | 🔴 | Submit application (wizard completes) | ☐ | ☐ | |
| B8 | 🟠 | EN ↔ SW switch on dashboard | ☐ | ☐ | |
| B9 | 🟡 | Help FAB opens (chat / feedback) | ☐ | ☐ | |
| B10 | 🟡 | Marketplace filters open as bottom sheet (mobile) | ☐ | ☐ | |

**Sandbox NIDA (stub):** `19810713-00001-23456-78` · DOB `1981-07-13` · names **Gaspari Malim Shiliba**

---

## C. P0 smoke — admin (30 min)

| # | Sev | Check | Pass | Fail | Notes |
|---|-----|-------|:----:|:----:|-------|
| C1 | 🔴 | Admin login | ☐ | ☐ | |
| C2 | 🔴 | Find submitted application in pipeline | ☐ | ☐ | |
| C3 | 🔴 | Move application through review → approval | ☐ | ☐ | |
| C4 | 🔴 | Disburse loan (or mark ready + disburse) | ☐ | ☐ | |
| C5 | 🟠 | Approve membership / bank payment (if used) | ☐ | ☐ | |
| C6 | 🟠 | Loan product fees/tiers editable | ☐ | ☐ | |

---

## D. Secondary portals (pilot scope)

| # | Sev | Portal | Check | Pass | Fail | Notes |
|---|-----|--------|-------|:----:|:----:|-------|
| D1 | 🟠 | Public | Home, products, marketplace browse | ☐ | ☐ | |
| D2 | 🟠 | Asset | Marketplace → reserve → apply | ☐ | ☐ | |
| D3 | 🟡 | Referral | Share link + copy code | ☐ | ☐ | |
| D4 | 🟡 | Affiliate | Dashboard + wallet payout request | ☐ | ☐ | |
| D5 | 🟡 | Investor | Pools browse + wallet deposit form | ☐ | ☐ | |
| D6 | 🟡 | Footer | Partner Portal link works | ☐ | ☐ | |

---

## E. Known accepted gaps (do not block pilot if documented)

| Item | Accepted for pilot? | Workaround |
|------|:-------------------:|------------|
| Live M-Pesa / card auto-debit | ☐ Yes ☐ No | Manual bank approval / confirm in admin |
| External guarantor contract PDF sign | ☐ Yes ☐ No | Internal guarantors only for pilot |
| CRB live bureau | ☐ Yes ☐ No | Sandbox / stub mode |
| Full Swahili parity | ☐ Yes ☐ No | EN-primary for pilot |
| Group loan (full cohort) | ☐ Yes ☐ No | Individual loans only for pilot |

---

## F. Decision

| Outcome | Criteria |
|---------|----------|
| **GO — Pilot** | All 🔴 Pass; ops SOP for payments signed |
| **GO — Soft launch** | Pilot criteria + Journey A & B UAT Pass + product sign-off |
| **NO-GO** | Any 🔴 Fail on register/apply/disburse OR no payment SOP |

**Blocker failures (list):**

1. _______________________________________________
2. _______________________________________________

| | Name | Signature | Date |
|---|------|-----------|------|
| **Tester** | | | |
| **Product owner** | | | |
| **Technical lead** | | | |
| **Operations** | | | |

**Final decision:** ☐ GO (Pilot) ☐ GO (Soft launch) ☐ NO-GO
