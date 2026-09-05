<?php

namespace Database\Seeders;

use App\Models\ChargesFee;
use App\Services\ValuationPricingService;
use Illuminate\Database\Seeder;

class ChargesFeeSeeder extends Seeder
{
    public function run(): void
    {
        $fees = [
            [
                'name'        => 'Loan application fee',
                'code'        => 'APP_FEE',
                'type'        => 'processing',
                'basis'       => 'fixed',
                'amount'      => 5000,
                'charge_when' => 'application',
                'description' => 'One-time fee charged when a loan application is submitted.',
            ],
            [
                'name'        => 'Membership registration fee (catalog mirror)',
                'code'        => 'REG_FEE',
                'type'        => 'processing',
                'basis'       => 'fixed',
                'amount'      => 10000,
                'charge_when' => 'application',
                'description' => 'Catalog mirror only. Live membership / onboarding fee is controlled in Settings → Membership — do not treat this as a second registration fee.',
            ],
            [
                'name'        => 'Loan origination fee',
                'code'        => 'ORIG_FEE',
                'type'        => 'origination',
                'basis'       => 'percentage',
                'amount'      => 2.0000,
                'min_amount'  => 5000,
                'max_amount'  => 500000,
                'charge_when' => 'post_approval',
                'description' => '2% of approved principal, due after approval before disbursement.',
            ],
            [
                'name'        => 'Insurance premium',
                'code'        => 'INS_FEE',
                'type'        => 'insurance',
                'basis'       => 'percentage',
                'amount'      => 1.0000,
                'charge_when' => 'post_approval',
                'description' => '1% loan insurance premium, due after approval.',
            ],
            [
                'name'        => 'Disbursement processing fee',
                'code'        => 'DISB_FEE',
                'type'        => 'processing',
                'basis'       => 'fixed',
                'amount'      => 10000,
                'charge_when' => 'post_approval',
                'description' => 'Fixed fee before funds are released.',
            ],
            [
                'name'        => 'Late payment penalty',
                'code'        => 'LATE_FEE',
                'type'        => 'late_fee',
                'basis'       => 'per_day',
                'amount'      => 1.0000,
                'charge_when' => 'late',
                'description' => '1% of overdue balance per day after grace (BOT cumulative cap 30%).',
            ],
            [
                'name'        => 'Early settlement fee',
                'code'        => 'EARLY_FEE',
                'type'        => 'early_settlement',
                'basis'       => 'percentage',
                'amount'      => 1.0000,
                'charge_when' => 'event',
                'description' => '1% of outstanding balance on early settlement.',
            ],
            [
                'name'        => 'Restructure fee',
                'code'        => 'RESTR_FEE',
                'type'        => 'restructure',
                'basis'       => 'fixed',
                'amount'      => 10000,
                'charge_when' => 'event',
                'description' => 'Fee charged when a loan is restructured.',
            ],
            [
                'name'        => 'GPS tracker fee',
                'code'        => 'GPS_FEE',
                'type'        => 'gps',
                'basis'       => 'fixed',
                'amount'      => 50000,
                'charge_when' => 'post_approval',
                'description' => 'GPS installation plus monthly monitoring for the loan tenure, priced from Settings at the time the fee is generated. Paid after approval, before disbursement.',
            ],
            [
                'name'        => 'Legal fee',
                'code'        => 'LEGAL_FEE',
                'type'        => 'other',
                'basis'       => 'fixed',
                'amount'      => 75000,
                'charge_when' => 'post_approval',
                'description' => 'Legal documentation and contract processing.',
            ],
            [
                'name'        => 'Asset registration / transfer fee',
                'code'        => 'REG_POST_FEE',
                'type'        => 'other',
                'basis'       => 'fixed',
                'amount'      => 35000,
                'charge_when' => 'post_approval',
                'description' => 'Asset registration or ownership transfer fee charged after loan approval (before disbursement). Not the membership registration fee.',
            ],
            [
                'name'        => 'Valuation fee (post-approval)',
                'code'        => 'VAL_POST_FEE',
                'type'        => 'valuation',
                'basis'       => 'fixed',
                'amount'      => 1100, // valuer base 1000 per asset + 10% platform markup
                'charge_when' => 'post_approval',
                'description' => 'Collateral valuation fee per pledged asset after approval (borrower total = partner base + markup).',
            ],
            [
                'name'        => 'Valuation fee',
                'code'        => 'VAL_FEE',
                'type'        => 'valuation',
                'basis'       => 'fixed',
                'amount'      => 1100, // valuer base 1000 per asset + 10% platform markup
                'charge_when' => 'application',
                'description' => 'Collateral valuation fee per pledged asset (borrower total = partner base + markup).',
            ],
        ];

        foreach ($fees as $f) {
            ChargesFee::firstOrCreate(['code' => $f['code']], $f + ['is_active' => true]);
        }

        if (! ChargesFee::query()->whereIn('code', ['VAL_FEE', 'VAL_POST_FEE'])->exists()) {
            app(ValuationPricingService::class)->syncChargesFees();
        }
    }
}
