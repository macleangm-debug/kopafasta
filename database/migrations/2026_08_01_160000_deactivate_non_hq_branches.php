<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Digital-first platform: only Head Office remains active.
        Branch::query()->where('code', '!=', 'HQ001')->update(['is_active' => false]);
        Branch::query()->where('code', 'HQ001')->update([
            'name' => 'Head Office',
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // Intentionally left blank — re-activating branches is a business decision.
    }
};
