<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\LoanApplication;
use App\Models\LoanProductPostApprovalFee;
use App\Models\Setting;

/**
 * Snapshot extras for the bilingual loan agreement: company identity,
 * penalty/recovery formulas, complaints contacts, and document version.
 */
class LoanAgreementDisclosureService
{
    public const DOCUMENT_VERSION = '2026-08-18-v6';

    public function __construct(
        private readonly RecoveryPolicyService $recovery,
    ) {}

    /** @return array<string, mixed> */
    public function companyIdentity(): array
    {
        $company = Setting::group('company') ?? [];
        $legal = Setting::group('legal') ?? [];

        $legalName = trim((string) ($company['legal_name'] ?? '')) ?: brand('legal_name');
        $address = trim((string) ($company['address'] ?? ''));
        $licence = trim((string) ($company['bot_licence'] ?? ''));
        $registration = trim((string) ($company['registration_no'] ?? ''));
        $tin = trim((string) ($company['tin'] ?? ''));

        $complaintsPhone = trim((string) ($company['complaints_phone'] ?? ''))
            ?: trim((string) ($company['phone'] ?? ''))
            ?: (string) support_contact('phone');
        $complaintsEmail = trim((string) ($company['complaints_email'] ?? ''))
            ?: trim((string) ($company['support_email'] ?? $company['email'] ?? ''))
            ?: (string) support_contact('email');
        $complaintsAddress = trim((string) ($company['complaints_address'] ?? '')) ?: $address;

        return [
            'company_legal_name' => $legalName,
            'company_address' => $address !== '' ? $address : '—',
            'licence_number' => $licence !== '' ? $licence : '—',
            'registration_no' => $registration !== '' ? $registration : '—',
            'company_tin' => $tin !== '' ? $tin : '—',
            'jurisdiction' => (string) ($legal['jurisdiction'] ?? app(LegalSettingsService::class)->jurisdiction()),
            'complaints_phone' => $complaintsPhone !== '' ? $complaintsPhone : '—',
            'complaints_email' => $complaintsEmail !== '' ? $complaintsEmail : '—',
            'complaints_address' => $complaintsAddress !== '' ? $complaintsAddress : '—',
            'document_version' => self::DOCUMENT_VERSION,
        ];
    }

    /**
     * Penalty disclosure using product/global rules (same math as LateFeeAccrualService).
     *
     * @return array<string, mixed>
     */
    public function penaltyDisclosure(LoanApplication $application): array
    {
        $application->loadMissing('product');
        $product = $application->product;
        $defaults = $product
            ? LoanPenaltyPolicy::defaultsForProduct($product)
            : [
                'default_grace_days' => (int) Setting::get('loan.default_grace_days', 7),
                'penalty_rate_percent' => (float) Setting::get('loan.default_penalty_rate', 1),
                'penalty_basis' => (string) Setting::get('loan.penalty_basis', 'per_day'),
            ];

        $cap = min(
            LoanPenaltyPolicy::BOT_MAX_PENALTY_CAP_PERCENT,
            max(0, (float) Setting::get('loan.penalty_cap_percent', LoanPenaltyPolicy::BOT_MAX_PENALTY_CAP_PERCENT))
        );
        $rate = (float) $defaults['penalty_rate_percent'];
        $basis = (string) $defaults['penalty_basis'];
        $grace = (int) $defaults['default_grace_days'];
        $basisLabel = match ($basis) {
            'per_month' => 'per month',
            'one_time' => 'one-time',
            default => 'per day',
        };
        $basisLabelSw = match ($basis) {
            'per_month' => 'kwa mwezi',
            'one_time' => 'mara moja',
            default => 'kwa siku',
        };

        return [
            'penalty_rate' => $rate,
            'penalty_basis' => $basis,
            'penalty_basis_label' => $basisLabel,
            'penalty_basis_label_sw' => $basisLabelSw,
            'grace_days' => $grace,
            'penalty_cap_percent' => $cap,
            'penalty_base_en' => 'the unpaid remainder of the first overdue instalment (instalment amount due minus any amount already paid on that instalment)',
            'penalty_base_sw' => 'salio lisilolipwa la awamu ya kwanza iliyochelewa (kiasi cha awamu kinachodaiwa kutoa kiasi chochote kilicholipwa kwenye awamu hiyo)',
            'penalty_cap_base_en' => 'the sum of all overdue instalment remainders at the time of accrual',
            'penalty_cap_base_sw' => 'jumla ya salio la awamu zote zilizochelewa wakati wa kuhesabu adhabu',
            'penalty_formula_en' => sprintf(
                'Penalty accrues at %s%% %s on the unpaid remainder of the first overdue instalment, beginning on the calendar day after the Grace Period of %d day(s) expires. Cumulative penalty shall not exceed %s%% of the sum of all overdue instalment remainders.',
                format_number($rate, 2),
                $basisLabel,
                $grace,
                format_number($cap, 0)
            ),
            'penalty_formula_sw' => sprintf(
                'Adhabu inaingia kwa %s%% %s kwenye salio lisilolipwa la awamu ya kwanza iliyochelewa, kuanzia siku ya kalenda baada ya Muda wa Msamaha wa siku %d kumalizika. Adhabu jumla haitazidi %s%% ya jumla ya salio la awamu zote zilizochelewa.',
                format_number($rate, 2),
                $basisLabelSw,
                $grace,
                format_number($cap, 0)
            ),
            'call_center_assignment_day' => max(1, $grace - $this->recovery->callCenterLeadDays() + 1),
            'call_center_lead_days' => $this->recovery->callCenterLeadDays(),
        ];
    }

    /**
     * Recovery charges as they are calculated at assignment.
     *
     * Percentage: partner = base × commission%, company = base × markup%, total = sum.
     * Fixed: partner = fixed TZS, company = fixed × markup%, total = sum.
     * Base is principal or outstanding at the moment of assignment — not a future hypothetical fee.
     *
     * @return array<string, mixed>
     */
    public function recoverySchedule(LoanApplication $application): array
    {
        $feeBase = $this->recovery->feeBase();
        $baseLabelEn = $feeBase === 'outstanding'
            ? 'outstanding amount owed at the time of assignment'
            : 'principal amount';
        $baseLabelSw = $feeBase === 'outstanding'
            ? 'kiasi kinachodaiwa wakati wa kupelekwa hatua husika'
            : 'msingi wa mkopo';

        $grace = (int) ($this->penaltyDisclosure($application)['grace_days'] ?? 7);
        $lead = $this->recovery->callCenterLeadDays();
        $ccDay = max(1, $grace - $lead + 1);
        $auctionHold = $this->recovery->auctionHoldDays();

        $triggers = [
            'call_center' => [
                'en' => "Assigned when days past due reach day {$ccDay} (grace {$grace} minus lead {$lead}, plus 1). Charge posted immediately on assignment.",
                'sw' => "Inapelekwa siku ya {$ccDay} baada ya tarehe ya malipo (msamaha siku {$grace} kutoa siku {$lead} za maandalizi, pamoja na 1). Gharama inarekodiwa mara moja baada ya kupelekwa.",
            ],
            'debt_collector' => [
                'en' => 'Assigned on escalation after the call-centre stage, subject to SLA and recovery policy. Charge posted on assignment.',
                'sw' => 'Inapelekwa baada ya hatua ya kituo cha huduma, kwa mujibu wa SLA na sera ya urejeshaji. Gharama inarekodiwa baada ya kupelekwa.',
            ],
            'repossession' => [
                'en' => 'Where the loan is secured and lawful repossession conditions are met. Any repossession charge is posted when that action is initiated.',
                'sw' => 'Pale mkopo unapokuwa na dhamana na masharti halali ya kurejesha mali yametimizwa. Gharama inarekodiwa hatua inapoanzishwa.',
            ],
            'auctioneer' => [
                'en' => "Assigned after the redemption / auction-hold period of {$auctionHold} day(s) following repossession, where applicable. Charge posted on assignment.",
                'sw' => "Inapelekwa baada ya kipindi cha siku {$auctionHold} cha kununua tena / kusubiri mnada baada ya kurejesha mali, pale inapohusika. Gharama inarekodiwa baada ya kupelekwa.",
            ],
            'legal_partner' => [
                'en' => 'Assigned on further escalation to an authorised legal recovery partner. Charge posted on assignment.',
                'sw' => 'Inapelekwa pale mkopo unapopandishwa kwa mshirika wa kisheria aliyeidhinishwa. Gharama inarekodiwa baada ya kupelekwa.',
            ],
            'gps_partner' => [
                'en' => 'Assigned where GPS recovery action applies to a secured asset. Charge posted on assignment.',
                'sw' => 'Inapelekwa pale hatua ya GPS inapohusika na mali yenye dhamana. Gharama inarekodiwa baada ya kupelekwa.',
            ],
        ];

        $stages = [];
        foreach (array_keys($this->recovery->partnerTypes()) as $type) {
            if (! $this->recovery->chargesBorrowerForType($type)) {
                continue;
            }
            if (! $this->recovery->partnerTypeAppliesToApplication($type, $application)) {
                continue;
            }

            $feeType = $this->recovery->feeTypeForPartnerType($type);
            $commission = $this->recovery->defaultCommissionPercent($type);
            $markup = $this->recovery->hasMarkupForType($type)
                ? $this->recovery->defaultMarkupPercent($type)
                : 0.0;
            $fixed = $this->recovery->fixedAmountForType($type);
            $labels = $this->partnerLabels($type);

            if ($feeType === 'fixed') {
                $amount = (float) ($fixed ?? 0);
                if ($amount > 0) {
                    $displayEn = 'Fixed fee of TZS '.format_number($amount, 0).' posted when this stage is assigned.';
                    $displaySw = 'Ada ya TZS '.format_number($amount, 0).' inarekodiwa pale hatua hii inapopelekwa.';
                } else {
                    $displayEn = 'A fixed TZS fee (not a percentage of the loan) is posted when this stage is assigned, as configured in Settings at signing.';
                    $displaySw = 'Ada ya kiasi kilichowekwa (TZS), si asilimia ya mkopo, inarekodiwa pale hatua hii inapopelekwa, kama ilivyowekwa kwenye Mipangilio wakati wa kusaini.';
                }
                if ($markup > 0) {
                    $displayEn .= ' Company charge of '.$this->pct($markup).'% of that fee is added.';
                    $displaySw .= ' Gharama ya kampuni ya '.$this->pct($markup).'% ya ada hiyo inaongezwa.';
                }
            } elseif ($feeType === 'hybrid') {
                $amount = (float) ($fixed ?? 0);
                $displayEn = 'TZS '.format_number($amount, 0).' plus '.$this->pct($commission).'% of the '.$baseLabelEn.' at assignment.';
                $displaySw = 'TZS '.format_number($amount, 0).' pamoja na '.$this->pct($commission).'% ya '.$baseLabelSw.' wakati wa kupelekwa.';
                if ($markup > 0) {
                    $displayEn .= ' Company charge of '.$this->pct($markup).'% of that total is added.';
                    $displaySw .= ' Gharama ya kampuni ya '.$this->pct($markup).'% ya jumla hiyo inaongezwa.';
                }
            } else {
                $total = $commission + $markup;
                $displayEn = $this->pct($total).'% of the '.$baseLabelEn
                    .' at assignment, comprising '.$this->pct($commission).'% recovery-partner fee'
                    .($markup > 0 ? ' and '.$this->pct($markup).'% Kopafasta platform fee' : '')
                    .' (both on the same recovery base; not markup on the partner fee). Posted only when this stage is actually assigned.';
                $displaySw = $this->pct($total).'% ya '.$baseLabelSw
                    .' wakati wa kupelekwa, ikijumuisha '.$this->pct($commission).'% ada ya mshirika wa urejeshaji'
                    .($markup > 0 ? ' na '.$this->pct($markup).'% ada ya jukwaa la Kopafasta' : '')
                    .' (zote kwenye msingi mmoja; si ongezeko juu ya ada ya mshirika). Inarekodiwa pale tu hatua inapopelekwa.';
            }

            $stages[] = [
                'type' => $type,
                'label' => $labels['en'],
                'label_en' => $labels['en'],
                'label_sw' => $labels['sw'],
                'trigger_en' => $triggers[$type]['en'] ?? 'Assigned in accordance with the recovery policy. Charge posted on assignment.',
                'trigger_sw' => $triggers[$type]['sw'] ?? 'Inapelekwa kwa mujibu wa sera ya urejeshaji. Gharama inarekodiwa baada ya kupelekwa.',
                'fee_type' => $feeType,
                'commission_percent' => $commission,
                'markup_percent' => $markup,
                'fixed_amount' => $fixed,
                'display_en' => $displayEn,
                'display_sw' => $displaySw,
            ];
        }

        $early = ChargesFee::query()->where('code', 'EARLY_FEE')->where('is_active', true)->first();

        return [
            'fee_base' => $feeBase,
            'fee_base_label_en' => $baseLabelEn,
            'fee_base_label_sw' => $baseLabelSw,
            'stages' => $stages,
            'early_settlement_en' => $early
                ? sprintf(
                    'Where early settlement is permitted, the catalog charge is %s%% of outstanding balance (code EARLY_FEE), or as stated in the Charges Schedule at signing.',
                    format_number((float) $early->amount, 2)
                )
                : 'Where early settlement is permitted, any early-settlement charge is as stated in the Charges Schedule at signing.',
            'early_settlement_sw' => $early
                ? sprintf(
                    'Pale malipo ya mapema yanaporuhusiwa, ada ya katalogi ni %s%% ya salio, au kama ilivyoainishwa kwenye Jedwali la Ada wakati wa kusaini.',
                    format_number((float) $early->amount, 2)
                )
                : 'Pale malipo ya mapema yanaporuhusiwa, ada yoyote ya malipo ya mapema ni kama ilivyoainishwa kwenye Jedwali la Ada wakati wa kusaini.',
            'payment_allocation_en' => 'Unless applicable law requires otherwise: (1) accrued penalties; (2) accrued interest; (3) outstanding principal; (4) other amounts lawfully due. Oldest unpaid instalment first.',
            'payment_allocation_sw' => 'Isipokuwa sheria inayotumika iagize vinginevyo: (1) adhabu zilizokwishaingia; (2) riba iliyokwishaingia; (3) msingi wa mkopo ambao haujalipwa; (4) kiasi kingine kinachodaiwa kihalali. Deni la zamani zaidi kwanza.',
            'group_liability_en' => 'Group recovery is staged: first the defaulting member (individual), then group liability as configured, then an external recovery partner. Every member must sign this Agreement. The Group Leader is not automatically personally liable for every member merely by being leader, unless the Group Terms say so.',
            'group_liability_sw' => 'Urejeshaji wa kikundi una hatua: kwanza mwanachama aliyechelewa (binafsi), kisha uwajibikaji wa kikundi kama ulivyoainishwa, kisha mshirika wa nje. Kila mwanachama lazima asaini Mkataba huu. Kiongozi wa Kikundi hawajibiki moja kwa moja binafsi kwa kila mwanachama kwa sababu tu ni kiongozi, isipokuwa Masharti ya Kikundi yaseme hivyo.',
        ];
    }

    /**
     * Post-approval GPS (install + monitoring × tenure) from Settings.
     * Deactivation during recovery is not a borrower charge.
     *
     * @return array<string, mixed>|null
     */
    public function gpsPostApprovalFee(LoanApplication $application): ?array
    {
        if (! app(LoanAgreementProductProfile::class)->needsGpsPostApprovalFee($application)) {
            return null;
        }

        $months = max(1, (int) ($application->offered_tenure_months
            ?? $application->approved_tenure_months
            ?? $application->requested_tenure_months
            ?? $application->product?->default_tenure_months
            ?? 12));
        $estimate = app(GpsPricingService::class)->estimate($months);

        return [
            'install_amount' => (float) $estimate['device_cost'],
            'monthly_amount' => (float) $estimate['monthly_monitoring'],
            'months' => (int) $estimate['months'],
            'monitoring_total' => (float) $estimate['monitoring_total'],
            'markup' => (float) $estimate['markup'],
            'total' => (float) $estimate['total'],
            'display_en' => sprintf(
                'GPS is a post-approval fee paid before disbursement: installation TZS %s plus TZS %s per month for %d month(s) (monitoring TZS %s)%s, total TZS %s. A debt collector may instruct deactivation; that deactivation has no extra borrower charge.',
                format_number($estimate['device_cost'], 0),
                format_number($estimate['monthly_monitoring'], 0),
                $estimate['months'],
                format_number($estimate['monitoring_total'], 0),
                $estimate['markup'] > 0 ? ', plus platform markup TZS '.format_number($estimate['markup'], 0) : '',
                format_number($estimate['total'], 0)
            ),
            'display_sw' => sprintf(
                'GPS ni ada baada ya kuidhinishwa, inayolipwa kabla ya utoaji: usakinishaji TZS %s pamoja na TZS %s kwa mwezi kwa miezi %d (ufuatiliaji TZS %s)%s, jumla TZS %s. Mtozaji wa eneo anaweza kuagiza kuzimwa; kuzimwa hakuna ada ya ziada kwa mkopaji.',
                format_number($estimate['device_cost'], 0),
                format_number($estimate['monthly_monitoring'], 0),
                $estimate['months'],
                format_number($estimate['monitoring_total'], 0),
                $estimate['markup'] > 0 ? ', pamoja na ongezeko la jukwaa TZS '.format_number($estimate['markup'], 0) : '',
                format_number($estimate['total'], 0)
            ),
        ];
    }

    public function termsHash(array $snapshot): string
    {
        $canonical = $snapshot;
        unset(
            $canonical['borrower_signature'],
            $canonical['guarantor_signature'],
            $canonical['group_members'],
            $canonical['ceo_signature_path'],
            $canonical['finance_signature_path'],
            $canonical['company_signature_path'],
            $canonical['company_stamp_path'],
            $canonical['document_hash'],
            $canonical['terms_hash'],
            $canonical['generated_at'],
        );

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Post-approval charges from the product schedule and Settings.
     * GPS is disclosed separately. Insurance and valuation use live Settings rates.
     *
     * @return list<array<string, mixed>>
     */
    public function facilityCharges(LoanApplication $application): array
    {
        $application->loadMissing(['product.postApprovalFees', 'collateralAsset']);
        $principal = app(ApplicationOfferService::class)->effectiveAmount($application);
        $feeService = app(PostApprovalFeeService::class);
        $defaults = app(PartnerDefaultsService::class);
        $rows = [];
        $seen = [];

        $templates = $application->product?->postApprovalFees
            ?->where('is_active', true)
            ->sortBy('sort_order') ?? collect();

        foreach ($templates as $fee) {
            $code = strtoupper(trim((string) $fee->code));
            if ($this->isGpsCatalogCode($code) || $fee->fee_type === 'gps') {
                continue;
            }
            if ($code !== '' && isset($seen[$code])) {
                continue;
            }
            if ($code !== '') {
                $seen[$code] = true;
            }

            if ($this->isInsuranceCatalog($fee)) {
                // INS_FEE = loan/credit insurance (% of principal), not comprehensive asset cover.
                $rows[] = $this->loanInsuranceChargeRow($fee, $principal, $feeService, $application);
                continue;
            }
            if ($this->isValuationCatalog($fee)) {
                $rows[] = $this->valuationChargeRow($defaults);
                continue;
            }

            $amount = $feeService->calculateAmount($fee, $principal, $application);
            $percent = $fee->fee_type === 'percent';
            $rows[] = [
                'code' => $code,
                'name' => (string) $fee->name,
                'amount' => $amount,
                'display_en' => $percent
                    ? $this->pct((float) $fee->amount).'% of principal (TZS '.format_number($amount, 0).') as configured in Settings / the product fee schedule.'
                    : 'TZS '.format_number($amount, 0).' as configured in Settings / the product fee schedule.',
                'display_sw' => $percent
                    ? $this->pct((float) $fee->amount).'% ya msingi (TZS '.format_number($amount, 0).') kama ilivyowekwa kwenye Mipangilio / jedwali la ada ya bidhaa.'
                    : 'TZS '.format_number($amount, 0).' kama ilivyowekwa kwenye Mipangilio / jedwali la ada ya bidhaa.',
            ];
        }

        // Comprehensive collateral insurance is a separate BEFORE_DISBURSEMENT condition /
        // payment journey — never invent it here as INS_FEE for asset products.
        if ($application->collateralAsset && ! isset($seen['VAL_POST_FEE']) && ! isset($seen['VAL_FEE'])) {
            $rows[] = $this->valuationChargeRow($defaults);
        }

        return array_values(array_filter($rows));
    }

    /**
     * Worked examples using this file's principal, instalment and live penalty Settings.
     *
     * @param  array<string, mixed>  $penalty
     * @param  list<array<string, mixed>>  $schedule
     * @return array<string, string>
     */
    public function workedExamples(
        array $penalty,
        float $principal,
        float $instalment,
        array $schedule = [],
    ): array {
        $rate = (float) ($penalty['penalty_rate'] ?? 0);
        $grace = (int) ($penalty['grace_days'] ?? 0);
        $cap = (float) ($penalty['penalty_cap_percent'] ?? 0);
        $basisEn = (string) ($penalty['penalty_basis_label'] ?? 'per day');
        $basisSw = (string) ($penalty['penalty_basis_label_sw'] ?? 'kwa siku');
        $instalment = max(0, $instalment);
        $day1 = round($instalment * ($rate / 100), 0);
        $day2 = round($day1 * 2, 0);

        $interestDue = (float) ($schedule[0]['interest_due'] ?? 0);
        $penaltyDue = $day1;
        $payment = $instalment > 0 ? $instalment : $principal;
        $toPenalty = min($penaltyDue, $payment);
        $left = $payment - $toPenalty;
        $toInterest = min($interestDue, $left);
        $left -= $toInterest;
        $toPrincipal = min($principal, $left);
        $principalLeft = max(0, $principal - $toPrincipal);

        return [
            'allocation_en' => sprintf(
                'Example using this facility: if %s principal, %s interest and %s penalty are due and the Borrower pays %s, the payment first clears the %s penalty, then %s interest, then %s principal, leaving %s principal outstanding.',
                format_money($principal),
                format_money($interestDue),
                format_money($penaltyDue),
                format_money($payment),
                format_money($toPenalty),
                format_money($toInterest),
                format_money($toPrincipal),
                format_money($principalLeft)
            ),
            'allocation_sw' => sprintf(
                'Mfano kwa huduma hii: ikiwa %s msingi, %s riba na %s adhabu zinadaiwa na Mkopaji analipa %s, malipo yataondoa kwanza adhabu %s, kisha riba %s, kisha msingi %s, na %s ya msingi itabaki.',
                format_money($principal),
                format_money($interestDue),
                format_money($penaltyDue),
                format_money($payment),
                format_money($toPenalty),
                format_money($toInterest),
                format_money($toPrincipal),
                format_money($principalLeft)
            ),
            'penalty_en' => sprintf(
                'Example using this facility: an unpaid instalment of %s at %s%% %s after a %d-day grace is %s on the first penalty day on that instalment remainder, then %s cumulative on the next day, subject to the %s%% cap on all overdue remainders.',
                format_money($instalment),
                format_number($rate, 2),
                $basisEn,
                $grace,
                format_money($day1),
                format_money($day2),
                format_number($cap, 0)
            ),
            'penalty_sw' => sprintf(
                'Mfano kwa huduma hii: awamu ya %s isiyolipwa kwa %s%% %s baada ya msamaha wa siku %d ni %s siku ya kwanza ya adhabu kwenye salio la awamu hiyo, kisha %s jumla siku inayofuata, kulingana na kizuizi cha %s%% cha salio zote zilizochelewa.',
                format_money($instalment),
                format_number($rate, 2),
                $basisSw,
                $grace,
                format_money($day1),
                format_money($day2),
                format_number($cap, 0)
            ),
        ];
    }

    /**
     * Loan / credit insurance from the post-approval catalog (INS_FEE).
     * Distinct from comprehensive collateral insurance (separate payment/condition).
     *
     * @return array<string, mixed>
     */
    private function loanInsuranceChargeRow(
        LoanProductPostApprovalFee $fee,
        float $principal,
        PostApprovalFeeService $feeService,
        LoanApplication $application,
    ): array {
        $amount = $feeService->calculateAmount($fee, $principal, $application);
        $catalog = ChargesFee::query()->where('code', 'INS_FEE')->first();
        $rate = $fee->fee_type === 'percent'
            ? (float) $fee->amount
            : (float) ($catalog?->amount ?? 1.0);

        return [
            'code' => strtoupper(trim((string) $fee->code)) ?: 'INS_FEE',
            'name' => (string) ($fee->name ?: 'Loan insurance'),
            'amount' => $amount,
            'display_en' => sprintf(
                'Loan insurance: %s%% of principal (%s), total %s. Not comprehensive asset cover. Taken from Settings / product fee schedule.',
                $this->pct($rate),
                format_money($principal),
                format_money($amount)
            ),
            'display_sw' => sprintf(
                'Bima ya mkopo: %s%% ya msingi (%s), jumla %s. Si bima kamili ya mali. Inatokana na Mipangilio / jedwali la ada.',
                $this->pct($rate),
                format_money($principal),
                format_money($amount)
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function valuationChargeRow(PartnerDefaultsService $defaults): array
    {
        // Canonical whole-TZS quote (partner base + residual markup). Do not float-multiply.
        $quote = app(ValuationPricingService::class)->quote();
        $base = (float) $quote['base_cost'];
        $markup = (float) $quote['markup_percent'];
        $amount = (float) $quote['borrower_amount'];

        return [
            'code' => 'VAL_POST_FEE',
            'name' => 'Valuation',
            'amount' => $amount,
            'display_en' => sprintf(
                'Partner base %s%s, borrower total %s. Taken from Settings at generation.',
                format_money($base),
                $markup > 0 ? ' plus '.$this->pct($markup).'% markup' : '',
                format_money($amount)
            ),
            'display_sw' => sprintf(
                'Kiasi cha mshirika %s%s, jumla kwa mkopaji %s. Inatokana na Mipangilio wakati wa kutengenezwa.',
                format_money($base),
                $markup > 0 ? ' pamoja na '.$this->pct($markup).'% ongezeko' : '',
                format_money($amount)
            ),
        ];
    }

    private function isGpsCatalogCode(string $code): bool
    {
        $codes = array_map('strtoupper', config('gps_pricing.fee_codes', ['GPS_FEE']));

        return in_array($code, $codes, true);
    }

    private function isInsuranceCatalog(LoanProductPostApprovalFee $fee): bool
    {
        $code = strtoupper((string) $fee->code);

        return str_contains(strtolower((string) $fee->name), 'insurance') || str_starts_with($code, 'INS');
    }

    private function isValuationCatalog(LoanProductPostApprovalFee $fee): bool
    {
        $code = strtoupper((string) $fee->code);

        return str_contains(strtolower((string) $fee->name), 'valuat') || str_starts_with($code, 'VAL');
    }

    /** @return array{en: string, sw: string} */
    private function partnerLabels(string $type): array
    {
        return match ($type) {
            'call_center' => ['en' => 'Call centre', 'sw' => 'Kituo cha simu'],
            'debt_collector' => ['en' => 'Field collector', 'sw' => 'Mtozaji wa eneo'],
            'auctioneer' => ['en' => 'Auctioneer', 'sw' => 'Mnada'],
            'legal_partner' => ['en' => 'Legal partner', 'sw' => 'Mshirika wa kisheria'],
            'gps_partner' => ['en' => 'GPS partner', 'sw' => 'Mshirika wa GPS'],
            default => [
                'en' => $this->recovery->partnerTypeLabel($type),
                'sw' => $this->recovery->partnerTypeLabel($type),
            ],
        };
    }

    private function pct(float $value): string
    {
        return rtrim(rtrim(format_number($value, 2), '0'), '.');
    }
}
