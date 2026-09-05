# Production pricing comparison (staging snapshot 2026-09-05)

Do **not** treat this table as an approved commercial tariff. Staging currently holds the migrated triptz **test** world. Seeder/config defaults are historical/engineering values, not proven production prices.

Proposed production: **pending owner approval** unless a cell says otherwise.

| Charge | Current staging/test value | Historical/config value | Proposed actual production value | Evidence/source |
|---|---|---|---|---|
| Individual application (IL) | TZS 10,000 | Catalog `APP_FEE` TZS 5,000; `config/site.php` default 10,000; seeder does not set IL `application_fee_amount` | Pending owner approval | Staging `loan_products.IL.application_fee_amount` after triptz import |
| Group application (GL, per member) | TZS 10,000 | `PublicLoanProductsSeeder` GL 10,000 | Pending owner approval | Staging `loan_products.GL.application_fee_amount` |
| Asset-backed application (AB) | TZS 25,000 | Seeder does not set AB fee; catalog `APP_FEE` 5,000 | Pending owner approval | Staging `loan_products.AB.application_fee_amount` |
| Other public products (AL, BP, EL, EM, FC, KB, SAL-12, SL, WL) | TZS 5,000 each | Catalog `APP_FEE` 5,000; seeder omits per-product amount so RateTier fills from catalog | Pending owner approval — **unproven as commercial** | Staging `loan_products.application_fee_amount`; catalog `charges_fees.APP_FEE` |
| Catalog application fee `APP_FEE` | TZS 5,000 | `ChargesFeeSeeder` 5,000 | Pending owner approval — **catalog ≠ IL/GL/AB live product fees** | `charges_fees.APP_FEE` |
| Membership registration (borrower) | TZS 2,000 | Common engineering default 2,500 in tests; live Settings 2,000 | Pending owner approval — **unproven** | Settings `membership.registration_fee` |
| Membership renewal (borrower) | TZS 2,000 | Settings/tests often 2,000 | Pending owner approval — **unproven** | Settings `membership.renewal_fee` |
| Valuation (borrower total) | TZS 1,100 | Valuer base 1,000 + 10% markup (`ValuationPricingDefaultsSeeder`, `config/partner_defaults.php`) | Pending owner approval | Settings `partner_defaults.valuer.*`; `charges_fees.VAL_FEE` / `VAL_POST_FEE` |
| GPS installation (Settings) | TZS 100,000 + 10% markup | Config default install 50,000, markup off | Pending owner approval — **conflict with catalog** | Settings `partner_defaults.gps_installer.base_cost` + markup |
| GPS monthly monitoring | TZS 10,000 | Config default 20,000 | Pending owner approval — **unproven** | Settings `partner_defaults.gps_installer.monitoring_monthly` |
| GPS catalog `GPS_FEE` | TZS 50,000 | `ChargesFeeSeeder` 50,000 | Pending owner approval — **does not match Settings 100,000** | `charges_fees.GPS_FEE` vs Settings |
| Legal fee `LEGAL_FEE` | TZS 75,000 | `ChargesFeeSeeder` 75,000 | Pending owner approval — **unproven** | `charges_fees.LEGAL_FEE` |
| Origination `ORIG_FEE` | 2% (min 5,000 / max 500,000 in seeder) | `ChargesFeeSeeder` 2% | Pending owner approval — **unproven** | `charges_fees.ORIG_FEE` |
| Insurance premium `INS_FEE` | 1% of loan | `ChargesFeeSeeder` 1% | Pending owner approval — **unproven** | `charges_fees.INS_FEE` |
| Insurance partner cover rate | 3.5% of insured value, markup off | `config/partner_defaults.php` 3.5% | Pending owner approval — **unproven** | Settings `partner_defaults.insurance.rate_percent` |
| Disbursement processing `DISB_FEE` | TZS 10,000 | `ChargesFeeSeeder` 10,000 | Pending owner approval — **unproven** | `charges_fees.DISB_FEE` |
| Asset registration / transfer `REG_POST_FEE` | TZS 35,000 | `ChargesFeeSeeder` 35,000 | Pending owner approval — **unproven** | `charges_fees.REG_POST_FEE` |
| Late penalty `LATE_FEE` | 1% per day | `ChargesFeeSeeder` 1%/day; BoT cap 30% | Pending owner approval — **policy, not a commercial list price** | `charges_fees.LATE_FEE` |
| Early settlement `EARLY_FEE` | 1% | `ChargesFeeSeeder` 1% | Pending owner approval — **unproven** | `charges_fees.EARLY_FEE` |
| Restructure `RESTR_FEE` | TZS 10,000 | `ChargesFeeSeeder` 10,000 | Pending owner approval — **unproven** | `charges_fees.RESTR_FEE` |
| Kopafasta Plus | TZS 35,000 | `config/kopafasta_plus.php` 35,000; no Settings override on staging | Pending owner approval | Config file; Settings `kopafasta_plus.config` is empty |
| Affiliate membership (individual) | TZS 1,500 | Config default 25,000 | Pending owner approval — **staging is a reduced test value** | Settings `affiliates.membership.fee_amount_individual` |
| Affiliate membership (company) | TZS 2,000 | Config default 50,000 | Pending owner approval — **staging is a reduced test value** | Settings `affiliates.membership.fee_amount_company` |
| Valuer membership (individual) | TZS 1,500 | `config/partners.php` 1,500 | Pending owner approval — **config default, not a proven commercial tariff** | `partners.membership` Settings empty → config `category_fees.valuer.individual` |
| Valuer membership (company) | TZS 2,000 | `config/partners.php` 2,000 | Pending owner approval — **config default, not a proven commercial tariff** | config `category_fees.valuer.company` |
| Other partner types (GPS, insurance, recovery, yard, supplier, capital) | No activation fee in Settings (`default_fee_amount` 0; only valuer requires payment) | Partner membership page: non-valuer categories are renew-on-expiry unless ticked | Pending owner approval | `PartnerMembershipService::config()` |
| Affiliate minimum withdrawal / payout | TZS 50,000 minimum balance | `config/affiliates.php` 50,000 | Pending owner approval — **this is a payout floor, not a charge** | Settings `affiliates.minimum_payout_amount` |
| Partner / affiliate wallet withdrawals | No extra payment-gateway charge found in Settings | No seeder charge | No production charge identified | Code review of membership/wallet payout; flag if ops later add a PSP fee |
| Staging price overlay (TZS 500 / 1,000) | **Off** (`use_price_overrides=false`, simulator still on) | Config `staging_payments.use_price_overrides` false | Must stay off until a new test-pricing profile is approved | `StagingPaymentsService` + Settings |

## Preserve on staging

Do not reset IL 10,000 / GL 10,000 / AB 25,000 / valuation 1,100 / reduced affiliate & valuer membership / Plus 35,000. Do not turn on the TZS 500/1,000 overlay.

## Production

Do not copy this database. Production prices must be written from an **owner-approved** column, not from this staging snapshot.
