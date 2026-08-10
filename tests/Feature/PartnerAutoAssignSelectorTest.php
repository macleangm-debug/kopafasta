<?php

namespace Tests\Feature;

use App\Models\PartnerTask;
use App\Models\Setting;
use App\Models\Vendor;
use App\Services\PartnerAutoAssignSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerAutoAssignSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_least_load_prefers_partner_with_fewer_open_tasks(): void
    {
        Setting::set('partner_auto_assign.service.valuer.enabled', true);
        Setting::set('partner_auto_assign.service.valuer.strategy', 'least_load');
        Setting::set('partner_auto_assign.service.valuer.require_region', false);

        $busy = Vendor::query()->create([
            'name' => 'Busy Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-BUSY',
            'phone' => '255700000101',
        ]);
        $free = Vendor::query()->create([
            'name' => 'Free Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-FREE',
            'phone' => '255700000102',
        ]);

        PartnerTask::query()->create([
            'partner_id' => $busy->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
            'customer_name' => 'A',
        ]);
        PartnerTask::query()->create([
            'partner_id' => $busy->id,
            'task_type' => 'asset_valuation',
            'status' => 'in_progress',
            'customer_name' => 'B',
        ]);

        $picked = app(PartnerAutoAssignSelector::class)->pickService(
            'valuer',
            collect([$busy, $free]),
        );

        $this->assertSame($free->id, $picked?->id);
    }

    public function test_max_open_filters_capacity(): void
    {
        Setting::set('partner_auto_assign.service.valuer.enabled', true);
        Setting::set('partner_auto_assign.service.valuer.strategy', 'least_load');
        Setting::set('partner_auto_assign.service.valuer.max_open', 1);
        Setting::set('partner_auto_assign.service.valuer.require_region', false);

        $full = Vendor::query()->create([
            'name' => 'Full Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-FULL',
            'phone' => '255700000201',
        ]);
        $open = Vendor::query()->create([
            'name' => 'Open Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-OPEN',
            'phone' => '255700000202',
        ]);

        PartnerTask::query()->create([
            'partner_id' => $full->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
            'customer_name' => 'C',
        ]);

        $picked = app(PartnerAutoAssignSelector::class)->pickService(
            'valuer',
            collect([$full, $open]),
        );

        $this->assertSame($open->id, $picked?->id);
    }

    public function test_disabled_auto_assign_returns_null(): void
    {
        Setting::set('partner_auto_assign.service.valuer.enabled', false);

        $valuer = Vendor::query()->create([
            'name' => 'Any Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-ANY',
            'phone' => '255700000301',
        ]);

        $picked = app(PartnerAutoAssignSelector::class)->pickService(
            'valuer',
            collect([$valuer]),
        );

        $this->assertNull($picked);
    }
}
