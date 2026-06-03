<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Services\FeeCatalogService;
use Illuminate\Database\Seeder;

class LoanProductPostApprovalFeeCatalogSeeder extends Seeder
{
    /** Attach default post-approval catalog fees to every active loan product. */
    public function run(): void
    {
        $catalog = app(FeeCatalogService::class);
        $fees = $catalog->postApprovalFees();

        if ($fees->isEmpty()) {
            return;
        }

        LoanProduct::query()->each(function (LoanProduct $product) use ($catalog, $fees): void {
            if ($product->postApprovalFees()->exists()) {
                return;
            }

            $order = 0;
            foreach ($fees as $fee) {
                $snapshot = $catalog->snapshotForProduct($fee);
                $product->postApprovalFees()->create([
                    ...$snapshot,
                    'sort_order' => $order++,
                    'is_active'  => true,
                ]);
            }
        });
    }
}
