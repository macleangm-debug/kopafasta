<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\ValuationPricingService;
use Illuminate\Database\Seeder;

/**
 * Sets valuation partner defaults to 1,000 TZS per asset + 10% markup (borrower 1,100 per asset).
 */
class ValuationPricingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setMany([
            'partner_defaults.valuer.base_cost' => 1000,
            'partner_defaults.valuer.has_markup' => true,
            'partner_defaults.valuer.markup_percent' => 10,
        ]);

        app(ValuationPricingService::class)->syncChargesFees();
    }
}
