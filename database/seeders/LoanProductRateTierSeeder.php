<?php

namespace Database\Seeders;

use App\Models\ChargesFee;
use App\Models\LoanProduct;
use App\Services\LoanRateTierTemplateService;
use Illuminate\Database\Seeder;

class LoanProductRateTierSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(LoanRateTierTemplateService::class);
        $defaultAppFee = (int) (ChargesFee::query()->where('code', 'APP_FEE')->value('amount') ?? 5000);

        LoanProduct::query()->each(function (LoanProduct $product) use ($service, $defaultAppFee) {
            $updates = [];

            if (! $product->application_fee_amount) {
                $updates['application_fee_amount'] = $defaultAppFee;
            }

            if ($updates !== []) {
                $product->update($updates);
            }

            $service->applyDefaults($product, replaceExisting: true);
        });
    }
}
