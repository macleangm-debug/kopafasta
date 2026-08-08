<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\ValuationPricingService;
use Illuminate\Database\Seeder;

/**
 * Sets valuation partner defaults to base 300 + 10% markup (borrower 330).
 */
class ValuationPricingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setMany([
            'partner_defaults.valuer.base_cost' => 300,
            'partner_defaults.valuer.has_markup' => true,
            'partner_defaults.valuer.markup_percent' => 10,
        ]);

        app(ValuationPricingService::class)->syncChargesFees();
    }
}
