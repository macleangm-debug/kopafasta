<?php

use App\Models\Setting;
use App\Services\ValuationPricingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Setting::set('partner_defaults.valuer.base_cost', 1000);

        if (Schema::hasTable('charges_fees')) {
            app(ValuationPricingService::class)->syncChargesFees();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Setting::set('partner_defaults.valuer.base_cost', 300);

        if (Schema::hasTable('charges_fees')) {
            app(ValuationPricingService::class)->syncChargesFees();
        }
    }
};
