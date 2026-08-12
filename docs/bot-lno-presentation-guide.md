# Kopafasta · BoT Letter of No Objection — presentation guide

**Live platform:** https://kopafasta.triptz.net  
**Source:** Bank of Tanzania · Guidance Note on Digital Lenders (Tier 2) · August 2024  
**Purpose:** Product readiness + **where to click** on presentation day. Not a legal opinion.

---

## Bottom line

Product-side LNO gaps are closed in code (ownership disclosure, complaints contacts, TZ/TZS lock, guarantor liability, pre-apply penalties, SMS Sender ID).

**Still needed from Board / compliance:** Tier 2 licence proof, PDPC certificate, Annexure 1–2, Board lending policy, hosting evidence, and confirmation that production SMS Sender ID matches the BoT-approved registered ID.

---

## BoT presentation walk (follow in order)

| Step | Breadcrumb (where to find it) | What to say / show |
|------|-------------------------------|--------------------|
| 1. Ownership on landing | https://kopafasta.triptz.net/ → hero disclosure card | Read aloud: “This Digital Lending Platform is owned or operated by [legal name].” |
| 2. Complaints contacts | Same hero card + page **Footer → Complaints & queries** | Show phone + email used for complaints (from Admin → Settings → Company). |
| 3. Kiswahili + English | **Header → language switcher** | Toggle SW ↔ EN; note TZ sessions default to Kiswahili. |
| 4. Tanzania / TZS only | https://kopafasta.triptz.net/register/borrower · header country control | Only Tanzania / +255; amounts show TZS. |
| 5. Pre-apply pricing + penalties | https://kopafasta.triptz.net/loans → pick a product → **Fees** tab | Show fees, late-penalty %, grace days, and penalty cap **before** Apply. |
| 6. Guarantor liability | Guarantor invite `/guarantor-request/{token}` or Borrower → Guarantor onboarding | Point to jointly-and-severally checkbox + written liability notice. |
| 7. SMS Sender ID | https://kopafasta.triptz.net/admin/settings/gateways | Show registered short Sender ID (must match BoT/carrier approval). |
| 8. Privacy / complaints pages | `/legal/privacy` · `/legal/complaints` | Legal pack pages for PDPA + complaints procedure. |
| 9. End-to-end lending | Apply → Admin underwrite → Offer OTP → Disburse → Repay | Prove the platform is testable for digital lending. |

---

## Admin / config breadcrumbs

| Setting | Where to find / change it |
|---------|---------------------------|
| Legal / company name (ownership sentence) | Admin → Settings → Company (or `BRAND_LEGAL_NAME` in `.env`) → landing/footer |
| Complaints phone & email | Admin → Settings → Company → support phone/email → landing + footer |
| SMS Sender ID | Admin → Settings → SMS / Email Gateways → Sender ID |
| TZ-only lock | Production `.env` → `DIGITAL_LENDING_TZ_ONLY=true` (default) |
| Late penalty defaults | Admin loan settings / product penalty fields → public product **Fees** tab |

---

## Criteria map + breadcrumbs (§3 + §5)

| Ref | Area | Status | Evidence | Breadcrumb |
|-----|------|--------|----------|------------|
| 3.1(a) | Tier 2 MFSP licence | Outside code | Must hold Tier 2 licence before LNO | Board file (physical/PDF) — not in the app |
| 3.1(b) | Testable digital lending platform | Pass | Live apply → underwrite → disburse → repay | Live site end-to-end flow |
| 3.1(b)(i–ii) | Limited device permissions | Partial | Camera for face/ID only; no contact/SMS scraping | Borrower → Profile / KYC upload |
| 3.1(b)(iii) | OTP / txn licensed Sender ID | Partial | Short Sender ID ≤11; confirm BoT-approved ID | Admin → Settings → Gateways → Sender ID |
| 3.1(c) | PDPC / Personal Data Protection | Partial | Privacy + consent; certificate is offline | `/legal/privacy` |
| 3.1(d) | Landing ownership disclosure | Pass | Ownership sentence on hero + footer | `/` hero disclosure · Footer |
| 3.1(f) | Pre-apply pricing disclosure | Pass | Fees + late penalties before apply | `/loans` → product → **Fees** |
| 3.1(g) | Kiswahili default + English | Pass | SW default; EN/SW switcher | Header language flag |
| 3.1(h) | Complaints phone + email on landing | Pass | Phones/emails on landing + footer | Hero card · Footer · `/legal/complaints` |
| 5.1(b) | No contact-list / message scraping | Pass | No phonebook harvesting | KYC screens — user-entered data only |
| 5.1(e) | No offshore personal-data access | Partial | Document hosting residency / DPA | Ops evidence pack |
| 5.1(f) | Prohibited debt-collection conduct | Partial | App has no shaming tools; SOPs needed | Admin recovery · partner contracts |
| 5.1(g) | e-receipts / instant messages | Partial | Templates exist; verify production SMS | Borrower payments · SMS logs |
| 5.1(l) | No deposit-taking | Partial | Membership framed as fee | Membership / marketplace wording |
| 5.1(n) | TZ residents only | Pass | Non-TZ markets inactive | Register + country switcher = TZ |
| 5.1(o) | Guarantor consent + liability | Pass | Jointly and severally (EN/SW) | Guarantor invite / onboarding consent |
| 5.1(p) | No upfront interest | Pass | Interest on schedule | Product rates · contract PDF |
| 5.1(s) | TZS only | Pass | Currency forced to TZS | Any amount display = TZS |
| CRB / scoring | Underwriting tools | Pass | CRB + affordability live | Admin → Loan application file |
| Agreements | Digital loan agreements | Pass | Offer/contract + OTP sign | Borrower → Offer / Contract |

---

## LNO document pack (§4)

| Required attachment | Status | Owner | Where / how |
|---------------------|--------|-------|-------------|
| Annexure 1 signed application | Outside code | Board / legal | Board signed PDF pack |
| Platform description, process flow, UI screenshots | Partial | Product + eng | Capture from demo walk steps 1–9 |
| T&Cs + Privacy Policy | Pass | Legal | `/legal/terms` · `/legal/privacy` |
| Hosting / app-store dashboard screenshots | Partial | Ops | Hosting provider console |
| Lending policy including digital products | Outside code | Credit / Board | Board-approved policy PDF |
| Pricing model + interest computation | Partial | Finance + credit | Finance memo + Fees/Rates screenshots |
| PDPC registration certificate | Outside code | Compliance | PDPC certificate PDF |
| Live link to platform + products | Pass | Ops | https://kopafasta.triptz.net/ · `/loans` |
| Annexure 2 questionnaire | Outside code | Board + compliance | Board-authorised questionnaire PDF |

---

## Do next

### Product / ops
1. Confirm production SMS Sender ID matches the BoT-approved registered ID.
2. Screenshot demo walk steps 1–9 for the LNO attachment pack.
3. Keep `DIGITAL_LENDING_TZ_ONLY=true` on production.
4. Verify Admin → Company support phone/email (landing complaints lines).

### Board / compliance
1. Confirm Tier 2 MFSP licence is in force.
2. Obtain / attach PDPC registration certificate.
3. Board-clear lending policy + pricing for digital products.
4. Fill Annexure 1 + Annexure 2 questionnaire truthfully.
5. Prepare process-flow screenshots and hosting evidence.
6. Contract recovery partners against §5.1(f) prohibited collection conduct.

---

*Mirror of the Cursor canvas `bot-digital-lending-lno` — keep this file for offline/print use on BoT presentation day.*
