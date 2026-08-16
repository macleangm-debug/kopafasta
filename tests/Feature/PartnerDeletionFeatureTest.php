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

    public function test_explicit_deactivate_suspends_partner_with_history(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $partner = Vendor::query()->create([
            'user_id' => $user->id,
            'name' => 'Busy Partner',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-DEL-3',
            'phone' => '255700000003',
        ]);

        PartnerTask::query()->create([
            'partner_id' => $partner->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
        ]);

        $result = app(PartnerDeletionService::class)->deactivate($partner);

        $this->assertSame('deactivated', $result['action']);
        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'status' => 'suspended',
        ]);
        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->assertDatabaseHas('partner_tasks', [
            'partner_id' => $partner->id,
            'status' => 'cancelled',
        ]);
        $this->assertStringContainsString('open job', $result['message']);
    }

    public function test_hard_delete_rejects_partner_with_history(): void
    {
        $partner = Vendor::query()->create([
            'name' => 'Busy Partner',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-DEL-4',
            'phone' => '255700000004',
        ]);

        PartnerTask::query()->create([
            'partner_id' => $partner->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PartnerDeletionService::class)->hardDelete($partner);
    }

    public function test_halt_cancels_open_work_and_does_not_delete_the_partner(): void
    {
        $partner = Vendor::query()->create([
            'name' => 'Halt Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-HALT-1',
            'phone' => '255700000005',
        ]);

        $task = PartnerTask::query()->create([
            'partner_id' => $partner->id,
            'task_type' => 'asset_valuation',
            'status' => 'in_progress',
        ]);

        $result = app(PartnerDeletionService::class)->haltOpenWork($partner);

        $this->assertSame(1, $result['halted_tasks']);
        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'status' => 'active',
        ]);
    }

    public function test_halt_reassigns_valuation_to_another_active_valuer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        $customer = \App\Models\Customer::query()->create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-HALT-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Test',
            'phone' => '255712000111',
            'region' => 'Dar es Salaam',
        ]);
        $product = \App\Models\LoanProduct::query()->create([
            'code' => 'IL-HALT',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = \App\Models\LoanApplication::query()->create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-HALT-1',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $asset = \App\Models\CustomerAsset::query()->create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz',
            'is_active' => true,
        ]);
        \App\Models\LoanApplicationAsset::query()->create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => \App\Models\LoanApplicationAsset::UW_PENDING,
            'valuation_fee_paid_at' => now(),
        ]);

        $outgoing = Vendor::query()->create([
            'name' => 'Outgoing Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-HALT-OUT',
            'phone' => '255700000006',
            'coverage_type' => 'nationwide',
            'regions' => [],
        ]);
        $incoming = Vendor::query()->create([
            'name' => 'Incoming Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-HALT-IN',
            'phone' => '255700000007',
            'coverage_type' => 'nationwide',
            'regions' => [],
        ]);

        $task = PartnerTask::query()->create([
            'partner_id' => $outgoing->id,
            'loan_application_id' => $application->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
        ]);
        $assignment = \App\Models\ValuationAssignment::query()->create([
            'loan_application_id' => $application->id,
            'vendor_id' => $outgoing->id,
            'vendor_task_id' => $task->id,
            'status' => \App\Models\ValuationAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $result = app(PartnerDeletionService::class)->haltOpenWork($outgoing, $admin);

        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertSame('cancelled', $assignment->fresh()->status);
        $this->assertSame(1, $result['reassigned']);
        $this->assertDatabaseHas('valuation_assignments', [
            'loan_application_id' => $application->id,
            'partner_id' => $incoming->id,
            'status' => \App\Models\ValuationAssignment::STATUS_ASSIGNED,
        ]);
        $this->assertSame('active', $outgoing->fresh()->status);
    }

    public function test_admin_can_halt_open_work_from_partner_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $partner = Vendor::query()->create([
            'name' => 'HTTP Halt Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PV-HALT-HTTP',
            'phone' => '255700000008',
        ]);
        PartnerTask::query()->create([
            'partner_id' => $partner->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.halt-open-work', $partner))
            ->assertRedirect(route('admin.partners.show', $partner));

        $this->assertDatabaseHas('partner_tasks', [
            'partner_id' => $partner->id,
            'status' => 'cancelled',
        ]);
    }
}
