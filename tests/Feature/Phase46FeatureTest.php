<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase46FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_only_mode_blocks_manual_repayment_recording(): void
    {
        Setting::setMany(['finance.collections_gateway_only' => true]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.repayments.create'))
            ->assertForbidden();
    }

    public function test_asset_manager_can_access_marketplace_assets(): void
    {
        $assetManager = User::factory()->create(['role' => 'asset_manager']);

        $this->actingAs($assetManager, 'admin')
            ->get(route('admin.marketplace-assets.index'))
            ->assertOk();
    }

    public function test_officer_without_marketplace_permission_is_blocked(): void
    {
        $officer = User::factory()->create(['role' => 'officer']);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.marketplace-assets.index'))
            ->assertForbidden();
    }
}
