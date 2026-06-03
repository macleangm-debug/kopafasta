<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Services\LoanRateTierTemplateService;
use Illuminate\Database\Seeder;

class LoanProductRateTierSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(LoanRateTierTemplateService::class);

        LoanProduct::query()->each(function (LoanProduct $product) use ($service) {
            if ($service->shouldSkip($product)) {
                return;
            }

            $product->rateTiers()->delete();

            foreach ($service->tiersForProduct($product) as $tier) {
                $product->rateTiers()->create($tier);
            }
        });
    }
}
