<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\LoanApplication;
use App\Models\Setting;

/**
 * Snapshot extras for the bilingual loan agreement: company identity,
 * penalty/recovery formulas, complaints contacts, and document version.
 */
class LoanAgreementDisclosureService
{
    public const DOCUMENT_VERSION = '2026-08-18-v1';

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
            'jurisdiction' => (string) ($legal['jurisdiction'] ?? 'United Republic of Tanzania'),
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
            'penalty_base_sw' => 'salio lisilolipwa la awamu ya kwanza iliyochelewa (kiasi cha awamu kinachodaiwa minus kiasi chochote kilicholipwa kwenye awamu hiyo)',
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
            : 'msingi wa mkopo (principal)';

        $grace = (int) ($this->penaltyDisclosure($application)['grace_days'] ?? 7);
        $lead = $this->recovery->callCenterLeadDays();
        $ccDay = max(1, $grace - $lead + 1);
        $auctionHold = $this->recovery->auctionHoldDays();

        $triggers = [
            'call_center' => [
                'en' => "Assigned when days past due reach day {$ccDay} (grace {$grace} minus lead {$lead}, plus 1). Charge posted immediately on assignment.",
                'sw' => "Inapelekwa siku ya {$ccDay} baada ya tarehe ya malipo (msamaha {$grace} minus siku {$lead} za maandalizi, pamoja na 1). Gharama inarekodiwa mara moja baada ya kupelekwa.",
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
            $feeType = $this->recovery->feeTypeForPartnerType($type);
            $commission = $this->recovery->defaultCommissionPercent($type);
            $markup = $this->recovery->defaultMarkupPercent($type);
            $fixed = $this->recovery->fixedAmountForType($type);
            $label = $this->recovery->partnerTypeLabel($type);

            if ($feeType === 'fixed') {
                $displayEn = 'TZS '.format_number((float) ($fixed ?? 0), 0)
                    .' partner fee plus '.$this->pct($markup).'% of that fee as company charge, posted when this stage is assigned.';
                $displaySw = 'Ada ya mshirika TZS '.format_number((float) ($fixed ?? 0), 0)
                    .' pamoja na '.$this->pct($markup).'% ya ada hiyo kama gharama ya kampuni, inarekodiwa hatua inapopelekwa.';
            } else {
                $total = $commission + $markup;
                $displayEn = $this->pct($total).'% of the '.$baseLabelEn
                    .' at assignment, comprising '.$this->pct($commission).'% recovery-partner commission and '
                    .$this->pct($markup).'% company charge. Posted only when this stage is actually assigned.';
                $displaySw = $this->pct($total).'% ya '.$baseLabelSw
                    .' wakati wa kupelekwa, ikijumuisha '.$this->pct($commission).'% kwa mshirika wa urejeshaji na '
                    .$this->pct($markup).'% gharama ya kampuni. Inarekodiwa pale tu hatua inapopelekwa.';
            }

            $stages[] = [
                'type' => $type,
                'label' => $label,
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
                    'Pale malipo ya mapema yanaporuhusiwa, ada ya katalogi ni %s%% ya salio (kodi EARLY_FEE), au kama ilivyoainishwa kwenye Jedwali la Ada wakati wa kusaini.',
                    format_number((float) $early->amount, 2)
                )
                : 'Pale malipo ya mapema yanaporuhusiwa, ada yoyote ya malipo ya mapema ni kama ilivyoainishwa kwenye Jedwali la Ada wakati wa kusaini.',
            'payment_allocation_en' => 'Unless applicable law requires otherwise: (1) accrued penalties; (2) accrued interest; (3) outstanding principal; (4) other amounts lawfully due. Oldest unpaid instalment first.',
            'payment_allocation_sw' => 'Isipokuwa sheria inayotumika iagize vinginevyo: (1) adhabu zilizokwishaingia; (2) riba iliyokwishaingia; (3) msingi wa mkopo ambao haujalipwa; (4) kiasi kingine kinachodaiwa kihalali. Deni la zamani zaidi kwanza.',
            'group_liability_en' => 'Group recovery is staged: first the defaulting member (individual), then group liability as configured, then an external recovery partner. The Group Leader is not automatically personally liable for every member merely by being leader, unless the Group Terms say so.',
            'group_liability_sw' => 'Urejeshaji wa kikundi una hatua: kwanza mwanachama aliyechelewa (binafsi), kisha uwajibikaji wa kikundi kama ulivyoainishwa, kisha mshirika wa nje. Kiongozi wa Kikundi hawajibiki moja kwa moja binafsi kwa kila mwanachama kwa sababu tu ni kiongozi, isipokuwa Masharti ya Kikundi yaseme hivyo.',
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

    private function pct(float $value): string
    {
        return rtrim(rtrim(format_number($value, 2), '0'), '.');
    }
}
