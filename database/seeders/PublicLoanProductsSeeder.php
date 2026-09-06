<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class PublicLoanProductsSeeder extends Seeder
{
    /**
     * Commercial columns that deployments must never overwrite once a product exists.
     * Fresh/bootstrap installs still receive seeder defaults on create.
     *
     * @var list<string>
     */
    private const COMMERCIAL_KEYS = [
        'application_fee_amount',
        'interest_rate',
        'min_amount',
        'max_amount',
        'tenure_min_months',
        'tenure_max_months',
        'repayment_cadence',
    ];

    public function run(): void
    {
        $products = [
            ['code' => 'IL', 'name' => 'Individual Loan', 'name_sw' => 'Mkopo wa Mdau', 'category' => 'individual', 'interest_rate' => 0.19, 'min_amount' => 500_000, 'max_amount' => 50000000, 'tenure_min_months' => 1, 'tenure_max_months' => 36, 'description' => 'Fast personal capital for any verified individual. No collateral for small tiers.', 'short_description' => 'Fast personal capital for verified individuals.', 'short_description_sw' => 'Mtaji wa haraka kwa watu waliothibitishwa.'],
            ['code' => 'SL', 'name' => 'Sharia Loan', 'name_sw' => 'Mkopo wa Sharia', 'category' => 'individual', 'interest_rate' => 0.19, 'min_amount' => 500_000, 'max_amount' => 50000000, 'tenure_min_months' => 1, 'tenure_max_months' => 36, 'hides_interest' => true, 'description' => 'Sharia-compliant personal financing for Muslim borrowers. Same limits and tiers as Individual Loan — you see the total you repay, not interest language.', 'short_description' => 'Sharia-compliant personal financing.', 'short_description_sw' => 'Ufadhili wa kibinafsi unaofuata Sharia.'],
            // Group: min 3 members × 200,000 per member = 600,000 total floor.
            // application_fee_amount is bootstrap-only; staging/production keep Settings-applied values on redeploy.
            ['code' => 'GL', 'name' => 'Group Loan', 'name_sw' => 'Mkopo wa Umoja', 'category' => 'group', 'interest_rate' => 0.18, 'min_amount' => 600_000, 'max_amount' => 10000000, 'tenure_min_months' => 3, 'tenure_max_months' => 12, 'repayment_cadence' => 'monthly', 'application_fee_amount' => 10_000, 'description' => 'Borrow together with shared liability. Best for chamas and savings circles.', 'short_description' => 'Borrow together with shared liability.', 'short_description_sw' => 'Kopa pamoja kwa dhamana ya pamoja.'],
            ['code' => 'AL', 'name' => 'Asset Lending', 'name_sw' => 'Mkopo wa Mali', 'category' => 'asset', 'interest_rate' => 0.155, 'min_amount' => 500_000, 'max_amount' => 100000000, 'tenure_min_months' => 3, 'tenure_max_months' => 60, 'description' => 'Own the asset over time. Pay monthly. Title transfers when fully paid.', 'short_description' => 'Own the asset over time with clear instalments.', 'short_description_sw' => 'Miliki mali hatua kwa hatua kwa malipo yaliyo wazi.'],
            ['code' => 'FC', 'name' => 'Artisans & Craftsmen Loan', 'name_sw' => 'Mkopo wa Sanaa', 'category' => 'business', 'interest_rate' => 0.17, 'min_amount' => 200_000, 'max_amount' => 5000000, 'tenure_min_months' => 1, 'tenure_max_months' => 12, 'description' => 'Funding capital for artisans, fundis and skilled tradespeople.', 'short_description' => 'Working capital for artisans and fundis.', 'short_description_sw' => 'Mtaji wa kazi kwa wasanii na fundi.'],
            ['code' => 'KB', 'name' => 'Kilimo Boost', 'name_sw' => 'Mkopo wa Kilimo', 'category' => 'agriculture', 'interest_rate' => 0.155, 'min_amount' => 500_000, 'max_amount' => 10000000, 'tenure_min_months' => 3, 'tenure_max_months' => 18, 'description' => 'Aligned to farming seasons. Grace periods supported.', 'short_description' => 'Financing aligned to farming seasons.', 'short_description_sw' => 'Ufadhili unaolingana na msimu wa kilimo.'],
            ['code' => 'BP', 'name' => 'Biashara Plus', 'name_sw' => 'Mkopo wa Biashara', 'category' => 'business', 'interest_rate' => 0.185, 'min_amount' => 500_000, 'max_amount' => 50000000, 'tenure_min_months' => 3, 'tenure_max_months' => 36, 'description' => 'Scale-up capital for registered businesses with cashflow history.', 'short_description' => 'Scale-up capital for registered businesses.', 'short_description_sw' => 'Mtaji wa kukuza biashara zilizosajiliwa.'],
            ['code' => 'EL', 'name' => 'Education Loan', 'name_sw' => 'Mkopo wa Elimu', 'category' => 'education', 'interest_rate' => 0.16, 'min_amount' => 500_000, 'max_amount' => 15000000, 'tenure_min_months' => 1, 'tenure_max_months' => 24, 'description' => 'For tuition, books and education pathways. Term-aligned repayments.', 'short_description' => 'For tuition, books and education pathways.', 'short_description_sw' => 'Kwa ada, vitabu na njia za elimu.'],
            ['code' => 'EM', 'name' => 'Emergency Loan', 'name_sw' => 'Mkopo wa Dharura', 'category' => 'individual', 'interest_rate' => 0.20, 'min_amount' => 500_000, 'max_amount' => 3000000, 'tenure_min_months' => 1, 'tenure_max_months' => 6, 'description' => 'When it cannot wait. Disbursed in hours after KYC clears.', 'short_description' => 'When it cannot wait — quick after KYC.', 'short_description_sw' => 'Wakati hauwezi kusubiri — baada ya KYC.'],
            ['code' => 'WL', 'name' => 'Women Loan', 'name_sw' => 'Mkopo wa Malkia', 'category' => 'individual', 'interest_rate' => 0.165, 'min_amount' => 500_000, 'max_amount' => 10000000, 'tenure_min_months' => 1, 'tenure_max_months' => 18, 'description' => 'Empowerment capital specifically for women-owned ventures.', 'short_description' => 'Capital for women-owned ventures.', 'short_description_sw' => 'Mtaji kwa biashara za wanawake.'],
            ['code' => 'AB', 'name' => 'Asset-Backed Loan', 'name_sw' => 'Mkopo wa Chap', 'category' => 'asset', 'interest_rate' => 0.15, 'min_amount' => 500_000, 'max_amount' => 100000000, 'tenure_min_months' => 3, 'tenure_max_months' => 60, 'description' => 'Use a vehicle, machine or property as security to unlock larger capital at the best rates.', 'short_description' => 'Unlock larger capital with asset security.', 'short_description_sw' => 'Fungua mtaji mkubwa kwa dhamana ya mali.'],
            ['code' => 'SAL-12', 'name' => 'Salary Advance', 'name_sw' => 'Mkopo wa Nivushe', 'category' => 'salary_loan', 'interest_rate' => 0.035, 'min_amount' => 200_000, 'max_amount' => 5000000, 'tenure_min_months' => 1, 'tenure_max_months' => 12, 'description' => 'Payroll-deducted salary advance for salaried employees.', 'short_description' => 'Payroll-deducted salary advance.', 'short_description_sw' => 'Mkopo wa mapema wa mshahara.', 'status' => 'coming_soon'],
        ];

        foreach ($products as $p) {
            $status = $p['status'] ?? 'active';
            unset($p['status']);

            $payload = array_merge($p, [
                'requires_collateral' => in_array($p['code'], ['AB', 'AL']),
                'requires_guarantor' => ! in_array($p['code'], ['GL', 'SAL-12'], true),
                'is_active' => $status === 'active',
                'status' => $status,
                'hides_interest' => (bool) ($p['hides_interest'] ?? false),
            ]);

            $existing = LoanProduct::query()->where('code', $p['code'])->first();
            if ($existing) {
                foreach (self::COMMERCIAL_KEYS as $key) {
                    unset($payload[$key]);
                }
                $existing->fill($payload)->save();

                continue;
            }

            LoanProduct::query()->create($payload);
        }
    }
}
