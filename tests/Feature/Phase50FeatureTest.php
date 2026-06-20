<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase50FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partners_table_uses_partner_number_column(): void
    {
        $this->assertTrue(Schema::hasColumn('partners', 'partner_number'));
        $this->assertFalse(Schema::hasColumn('partners', 'vendor_number'));
    }

    public function test_vendor_number_accessor_maps_to_partner_number(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P50-001',
            'name'          => 'Accessor Partner',
            'category'      => 'supplier',
            'status'        => 'active',
            'phone'         => '255712345898',
        ]);

        $this->assertDatabaseHas('partners', [
            'id'             => $partner->id,
            'partner_number' => 'PTR-P50-001',
        ]);
        $this->assertSame('PTR-P50-001', $partner->fresh()->vendor_number);
    }

    public function test_finance_sidebar_groups_setup_transactions_and_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Setup', false)
            ->assertSee('Transactions', false)
            ->assertSee('Financial reports', false)
            ->assertSee('Trial Balance', false)
            ->assertSee('Portfolio', false);
    }
}
