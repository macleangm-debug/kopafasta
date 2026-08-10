<?php

namespace Tests\Feature;

use App\Models\PartnerTask;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerDeletionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_partner_is_hard_deleted_and_user_disabled(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $partner = Vendor::query()->create([
            'user_id' => $user->id,
            'name' => 'Empty Partner',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-DEL-1',
            'phone' => '255700000001',
        ]);

        $result = app(PartnerDeletionService::class)->remove($partner);

        $this->assertSame('deleted', $result['action']);
        $this->assertDatabaseMissing('partners', ['id' => $partner->id]);
        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_partner_with_history_is_deactivated_not_deleted(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $partner = Vendor::query()->create([
            'user_id' => $user->id,
            'name' => 'Busy Partner',
            'category' => 'affiliate',
            'status' => 'active',
            'vendor_number' => 'PV-DEL-2',
            'phone' => '255700000002',
            'affiliate_lifecycle_status' => 'active',
        ]);

        PartnerTask::query()->create([
            'partner_id' => $partner->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
        ]);

        $result = app(PartnerDeletionService::class)->remove($partner);

        $this->assertSame('deactivated', $result['action']);
        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'status' => 'suspended',
            'affiliate_lifecycle_status' => 'terminated',
        ]);
        $this->assertFalse((bool) $user->fresh()->is_active);
    }
}
