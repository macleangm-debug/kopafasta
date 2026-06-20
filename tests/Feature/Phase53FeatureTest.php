<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase53FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_task_tables_are_renamed_to_partner(): void
    {
        $this->assertTrue(Schema::hasTable('partner_tasks'));
        $this->assertTrue(Schema::hasTable('partner_documents'));
        $this->assertTrue(Schema::hasTable('partner_payments'));
        $this->assertFalse(Schema::hasTable('vendor_tasks'));
        $this->assertFalse(Schema::hasTable('vendor_documents'));
        $this->assertFalse(Schema::hasTable('vendor_payments'));
    }

    public function test_vendor_task_model_alias_uses_partner_tasks_table(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P53-001',
            'name'          => 'GPS Partner',
            'category'      => 'gps_installer',
            'status'        => 'active',
            'phone'         => '255712345893',
        ]);

        $task = VendorTask::create([
            'vendor_id'   => $partner->id,
            'task_type'   => 'gps_installation',
            'status'      => 'assigned',
            'fee_amount'  => 50000,
        ]);

        $this->assertDatabaseHas('partner_tasks', [
            'id'         => $task->id,
            'partner_id' => $partner->id,
        ]);
        $this->assertSame('partner_tasks', $task->getTable());
        $this->assertInstanceOf(PartnerTask::class, VendorTask::find($task->id));
    }

    public function test_admin_partner_views_use_partners_folder(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.all'))
            ->assertOk()
            ->assertSee('Partners', false);

        $this->assertTrue(Route::has('admin.partners.all'));
        $this->assertTrue(view()->exists('admin.partners.all'));
        $this->assertTrue(view()->exists('admin.partners.show'));
    }
}
