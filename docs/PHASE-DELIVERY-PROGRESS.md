# Phase delivery progress — product review

Checkpoint for phased UAT / product-review work on **kopafasta**.  
**Architecture delivery arc complete** (Phases 3–44).

**Last updated:** 2026-06-18  
**Test status:** **226/226** phase tests passing  
**Do not commit** unless explicitly requested.

---

## Architecture gap closure (Phases 39–44)

| Phase | Summary |
|-------|---------|
| **39** | Lock deposit markup to Settings only; minimal public marketplace cards; asset-lending “Markup rules” copy |
| **40** | Managed-loan supplier repayment accrual; group min/max members; field-partner region validation; `PartnerSettlementService::accrue` tap fix |
| **41** | Option A GL: supplier payable journal on managed-loan principal repayments (`AssetLendingRepaymentGlService`, finance settings) |
| **42** | Group lending module foundation: `loan_groups` / `loan_group_members`, leader-first unlock after N repayments, per-member application fees |
| **43** | Group recovery stages: individual → group liability → external (`GroupLendingService`) |
| **44** | Unified partner supplier portal at `/partner/supplier`; nationwide partner coverage; location master tables + `LocationLookupService` |

---

## How to resume

1. Read this file and [TESTING-REVIEW-DEVELOPER-BRIEF.md](./TESTING-REVIEW-DEVELOPER-BRIEF.md).
2. Run the full phase test filter after changes (see below).
3. Run `php artisan migrate` on deploy when migrations exist.

---

## Completed phases (borrower portal arc 3–38 + architecture 39–44)

See git history / prior sections for Phases 3–38 (borrower portal, Swahili parity, wide layout).

| Phase | Summary |
|-------|---------|
| **39–40** | Markup lock, marketplace cards, supplier repayments, group limits, regions |
| **41** | Supplier payable GL on asset-lending repayments |
| **42** | Group entities, leader unlock, per-member fees |
| **43** | Group recovery escalation |
| **44** | Partner portal unification, nationwide coverage, location masters |

---

## Migrations to run on deploy

```bash
php artisan migrate
php artisan db:seed --class=LocationMasterSeeder
```

New migration:

- `2026_06_23_100000_phase41_44_platform_completion.php` (group lending, location masters, partner coverage)

---

## Full phase test command

```bash
php artisan test --filter="Phase44FeatureTest|Phase43FeatureTest|Phase42FeatureTest|Phase41FeatureTest|Phase40FeatureTest|Phase39FeatureTest|Phase38FeatureTest|Phase37FeatureTest|Phase36FeatureTest|Phase35FeatureTest|Phase34FeatureTest|Phase33FeatureTest|Phase32FeatureTest|Phase31FeatureTest|Phase30FeatureTest|Phase29FeatureTest|Phase28FeatureTest|Phase27FeatureTest|Phase26FeatureTest|Phase25FeatureTest|Phase24FeatureTest|Phase23FeatureTest|Phase22FeatureTest|Phase21FeatureTest|Phase20FeatureTest|Phase19FeatureTest|Phase18FeatureTest|Phase17FeatureTest|Phase16FeatureTest|Phase15FeatureTest|Phase14FeatureTest|Phase13FeatureTest|Phase12FeatureTest|Phase11FeatureTest|Phase10FeatureTest|Phase9FeatureTest|Phase8FeatureTest|Phase7FeatureTest|Phase6FeatureTest|Phase5FeatureTest|Phase4FeatureTest|Phase3FeatureTest|NidaVerificationLockoutTest|ValuationPartnerWorkflowTest|AssetDepositPaymentTest|CustomerDossierTest|PartnerSettlementServiceTest"
```

---

## Deferred (post–Phase 44)

| Area | Notes |
|------|-------|
| DNS / `partners.copperfasta.com` host | Config `PARTNER_PORTAL_HOST` ready; DNS + TLS is ops |
| Full group apply wizard UI | Data model + services ready; borrower wizard step TBD |
| Ward master admin UI | `location_wards` table exists; seeding districts only for now |
| `vendors` → `partners` DB rename | Explicitly deferred |
| iOS Safari face verification QA | Manual QA |

---

## Key files (Phases 41–44)

- `app/Services/AssetLendingRepaymentGlService.php`, `GroupLendingService.php`, `LocationLookupService.php`
- `app/Models/LoanGroup.php`, `LoanGroupMember.php`, `LocationCountry.php` …
- `database/migrations/2026_06_23_100000_phase41_44_platform_completion.php`
- `database/seeders/LocationMasterSeeder.php`
- `config/partners.php`, `config/group_lending.php`
- `tests/Feature/Phase41FeatureTest.php` … `Phase44FeatureTest.php`
