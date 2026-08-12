# Kopafasta · BoT Letter of No Objection — presentation guide

**Live platform (go-live domain):** https://kopafasta.com  
**Source:** Bank of Tanzania · Guidance Note on Digital Lenders (Tier 2) · August 2024  
**Also reviewed:** Personal Data Protection Act, 2022; Personal Data Collection and Processing Regulations, 2023; Complaints Settlement Procedures Regulations, 2023  
**Purpose:** Product readiness + **where to click** on presentation day. Not a legal opinion.

---

## Bottom line

Product-side BoT LNO criteria that can be fixed in code are closed. Camera capture is Kopafasta-branded with front/back switching. Disbursements and repayments write **in-app** receipts (plus SMS when a phone is on file).

**Still yours (Board / compliance / ops):** Tier 2 licence, **PDPC registration certificate**, named DPO, Annexure 1–2, Board lending policy, hosting-region evidence, screenshots, and confirmation that production SMS Sender ID matches the BoT/carrier-registered ID.

---

## BoT presentation walk (follow in order)

| Step | Breadcrumb (where to find it) | What to say / show |
|------|-------------------------------|--------------------|
| 1. Ownership on landing | https://kopafasta.com/ → hero disclosure card | “This Digital Lending Platform is owned or operated by [legal name].” |
| 2. Complaints contacts | Same hero card + **Footer → Complaints & queries** | Phone + email (Admin → Company). |
| 3. Branded camera | Borrower → **Add picture** / **Capture image** | Kopafasta brand header; **Front camera** / **Back camera**; keep Add picture + Capture. |
| 4. Privacy / PDPA | https://kopafasta.com/legal/privacy | PDPA basis, biometric consent, residency, PDPC complaint rights. |
| 5. TZ / TZS only | https://kopafasta.com/register/borrower | Only Tanzania / +255; amounts in TZS. |
| 6. Pricing + penalties | https://kopafasta.com/loans → product → **Fees** | Fees + late penalty **before** Apply. |
| 7. No deposit-taking | `/legal/terms` · marketplace down payment | Membership fee ≠ deposit; asset down payment ≠ savings. |
| 8. Guarantor liability | Guarantor invite / onboarding | Jointly and severally checkbox + notice. |
| 9. Partner fair collection | Partner apply / activate · recovery case | Conduct acceptance; no shaming tools. |
| 10. In-app + SMS e-receipts | Disburse / repay → borrower notifications | In-app receipt always; SMS when phone on file + licensed name. |

---

## Admin / config breadcrumbs

| Setting | Where to find / change it |
|---------|---------------------------|
| Legal / company name | Admin → Settings → Company / `BRAND_LEGAL_NAME` |
| Complaints phone & email | Admin → Settings → Company |
| SMS Sender ID | Admin → Settings → SMS / Email Gateways |
| Critical e-receipt events | Admin → Settings → Messaging (disbursed / payment received locked on) |
| TZ-only lock | Production `.env` → `DIGITAL_LENDING_TZ_ONLY=true` |

---

## PDPA fit check (from your uploaded Acts/Regs)

| Ref | Area | Status | What we see |
|-----|------|--------|-------------|
| Act §14–21 / Collection Regs §4 | PDPC registration as data controller | Outside code | Must register with PDPC — Board/compliance |
| Act §5 / Regs §25–31 | Data protection principles in product | Pass | Purpose, security, retention, disclosures in-product |
| Act §22–29 | Collection, use, disclosure, retention | Pass | Privacy Policy + KYC consents |
| Act §30 | Sensitive personal data (biometrics) | Pass | Face capture treated as sensitive; consent before camera |
| Act §27(3) / Regs §32 | Data Protection Officer | Partial | Privacy uses support email as DPO placeholder — appoint named DPO |
| Act §31–32 / Regs §20–22 | Transborder data flow | Partial | Residency + transfer caution in Privacy; need hosting map / PDPC permission if required |
| Act §33–38 | Data subject rights | Pass | Access/correction/erasure/objection/marketing/automated-decision rights disclosed |
| Complaints Regs 2023 | PDPC complaint pathway | Pass | Privacy + complaints pages point to PDPC procedures |

**Verdict:** Platform **product practices largely abide** for disclosures, consent, rights language, and device scope. You are **not fully PDPA-compliant as an organisation** until PDPC registration, a named DPO, and any required transborder permissions are completed.

---

## LNO document pack (§4)

| Required attachment | Status | Owner | Where / how |
|---------------------|--------|-------|-------------|
| Annexure 1 signed application | Outside code | Board / legal | Board signed PDF |
| Platform screenshots / process flow | Partial | Product + eng | Capture demo walk 1–10 |
| T&Cs + Privacy Policy | Pass | Legal | `/legal/terms` · `/legal/privacy` |
| Hosting / app-store screenshots | Partial | Ops | Hosting console |
| Lending policy | Outside code | Credit / Board | Board PDF |
| Pricing methodology | Partial | Finance | Memo + Fees screenshots |
| PDPC registration certificate | Outside code | Compliance | PDPC certificate |
| Live link | Pass | Ops | https://kopafasta.com/ · `/loans` |
| Annexure 2 questionnaire | Outside code | Board + compliance | Board PDF |

---

*Mirror of the Cursor canvas `bot-digital-lending-lno` — use this file for offline/print on BoT presentation day.*
