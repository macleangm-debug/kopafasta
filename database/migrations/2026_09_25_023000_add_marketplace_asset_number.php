<?php

use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_assets')) {
            return;
        }

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_assets', 'asset_number')) {
                $table->string('asset_number', 48)->nullable()->unique()->after('slug');
            }
        });

        if (! Schema::hasColumn('marketplace_assets', 'asset_number')) {
            return;
        }

        $grouped = MarketplaceAsset::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MarketplaceAsset $asset) => (int) $asset->partner_id);

        foreach ($grouped as $vendorId => $assets) {
            $vendor = $vendorId ? Vendor::query()->find($vendorId) : null;
            $base = $vendor?->vendor_number ?: $vendor?->partner_number;
            if (! $base) {
                continue;
            }
            $seq = 1;
            foreach ($assets as $asset) {
                if (filled($asset->asset_number)) {
                    continue;
                }
                do {
                    $number = $base.'-A'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
                    $seq++;
                } while (MarketplaceAsset::query()->where('asset_number', $number)->where('id', '!=', $asset->id)->exists());
                $asset->forceFill(['asset_number' => $number])->save();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_assets') || ! Schema::hasColumn('marketplace_assets', 'asset_number')) {
            return;
        }

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            $table->dropUnique(['asset_number']);
            $table->dropColumn('asset_number');
        });
    }
};
