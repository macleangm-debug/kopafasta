<?php

namespace Tests\Feature;

use App\Livewire\Admin\VendorTasksTable;
use App\Models\ArrearCase;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GpsPartnerService;
use App\Services\PartnerTaskReassignmentService;
use App\Services\RecoveryAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerTaskReassignmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_gps_task_can_be_reassigned_to_another_regional_partner(): void
    {
        [$application, $current, $replacement] = $this->gpsSetup();
        $admin = User::factory()->create(['role' => 'admin']);
        $task = app(GpsPartnerService::class)->assign($application, $current, $admin, 'Initial GPS.');

        app(PartnerTaskReassignmentService::class)->reassign(
            $task,
            $admin,
            $replacement->id,
            'Reassigned from partner tasks.',
        );

        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertDatabaseHas('partner_tasks', [
            'loan_application_id' => $application->id,
            'partner_id' => $replacement->id,
            'task_type' => 'gps_install',
            'status' => 'assigned',
        ]);
    }

    public function test_screening_officer_can_reassign_gps_but_not_recovery(): void
    {
        [$application, $current, $replacement] = $this->gpsSetup();
        $admin = User::factory()->create(['role' => 'admin']);
        $officer = User::factory()->create(['role' => 'officer']);
        $gpsTask = app(GpsPartnerService::class)->assign($application, $current, $admin);

        $service = app(PartnerTaskReassignmentService::class);
        $this->assertTrue($service->can($officer, $gpsTask));

        $service->reassign($gpsTask, $officer, $replacement->id);

        $this->assertSame('cancelled', $gpsTask->fresh()->status);

        [$recoveryTask] = $this->openRecoveryTask();
        $this->assertFalse($service->can($officer, $recoveryTask));
        $this->expectException(ValidationException::class);
        $service->reassign($recoveryTask, $officer, null);
    }

    public function test_manager_can_reassign_open_recovery_task(): void
    {
        [$task, $assignment, $replacement] = $this->openRecoveryTask();
        $manager = User::factory()->create(['role' => 'manager']);

        $service = app(PartnerTaskReassignmentService::class);
        $this->assertTrue($service->can($manager, $task));

        $service->reassign($task, $manager, $replacement->id, 'Management reassigned.');

        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertSame(RecoveryAssignment::STATUS_CANCELLED, $assignment->fresh()->status);
        $this->assertDatabaseHas('recovery_assignments', [
            'arrear_case_id' => $assignment->arrear_case_id,
            'partner_id' => $replacement->id,
            'status' => RecoveryAssignment::STATUS_ASSIGNED,
        ]);
    }

    public function test_partner_tasks_page_shows_assign_another_for_open_gps_and_livewire_reassigns(): void
    {
        [$application, $current, $replacement] = $this->gpsSetup();
        $admin = User::factory()->create(['role' => 'admin']);
        $task = app(GpsPartnerService::class)->assign($application, $current, $admin);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.tasks'))
            ->assertOk()
            ->assertSee('Assign another', false)
            ->assertSee($replacement->name, false)
            ->assertSee('outside region', false)
            ->assertSee('Amina Kopa', false)
            ->assertSee('Visit location', false)
            ->assertSee('Dar es Salaam', false)
            ->assertSee('Instructions', false)
            ->assertSee('Partner fee', false)
            ->assertSee('Related file', false);

        Livewire::actingAs($admin, 'admin')
            ->test(VendorTasksTable::class)
            ->set('reassignTo.'.$task->id, $replacement->id)
            ->call('reassign', $task->id)
            ->assertHasNoErrors()
            ->assertSet('notice', 'Task reassigned to another partner.');

        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertDatabaseHas('partner_tasks', [
            'loan_application_id' => $application->id,
            'partner_id' => $replacement->id,
            'task_type' => 'gps_install',
            'status' => 'assigned',
        ]);
    }

    public function test_completed_task_cannot_be_reassigned(): void
    {
        [$application, $current] = $this->gpsSetup();
        $admin = User::factory()->create(['role' => 'admin']);
        $task = app(GpsPartnerService::class)->assign($application, $current, $admin);
        $task->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertFalse(app(PartnerTaskReassignmentService::class)->can($admin, $task->fresh()));
    }

    public function test_rejected_application_closes_open_job_instead_of_assign_another(): void
    {
        [$application, $current, $replacement] = $this->gpsSetup();
        $admin = User::factory()->create(['role' => 'admin']);
        $task = app(GpsPartnerService::class)->assign($application, $current, $admin);
        $application->update(['status' => 'rejected', 'current_stage' => 'rejected']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.tasks'))
            ->assertOk()
            ->assertDontSee('Assign another');

        $this->assertSame('cancelled', $task->fresh()->status);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->assertSee($application->application_number, false);

        $this->assertFalse(app(\App\Services\PartnerDeletionService::class)->hasOpenWork($current->fresh()));
    }

    public function test_rejecting_an_application_cancels_open_origination_tasks(): void
    {
        [$application, $current] = $this->gpsSetup();
        $admin = User::factory()->create(['role' => 'admin']);
        $task = app(GpsPartnerService::class)->assign($application, $current, $admin);

        app(\App\Services\PartnerTaskLifecycleService::class)->closeForApplication(
            $application,
            'Closed because the application was rejected.',
        );

        $this->assertSame('cancelled', $task->fresh()->status);
    }

    /** @return array{0: LoanApplication, 1: Vendor, 2: Vendor} */
    private function gpsSetup(): array
    {
        $customer = Customer::create([
            'customer_number' => 'CU-REAS-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Kopa',
            'phone' => '25571'.random_int(1000000, 9999999),
            'region' => 'Dar es Salaam',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-REAS-'.random_int(100, 999),
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-REAS-'.random_int(100, 999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 1_500_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $current = Vendor::create([
            'vendor_number' => 'PT-GPS-A-'.random_int(100, 999),
            'name' => 'Demo GPS Installers',
            'category' => 'gps_installer',
            'status' => 'active',
            'phone' => '255700'.random_int(100000, 999999),
            'coverage_type' => 'nationwide',
        ]);

        $replacement = Vendor::create([
            'vendor_number' => 'PT-GPS-B-'.random_int(100, 999),
            'name' => 'Coast GPS Partners',
            'category' => 'gps_installer',
            'status' => 'active',
            'phone' => '255700'.random_int(100000, 999999),
            'coverage_type' => 'regions',
            'regions' => ['Arusha'],
        ]);

        return [$application, $current, $replacement];
    }

    /** @return array{0: PartnerTask, 1: RecoveryAssignment, 2: Vendor} */
    private function openRecoveryTask(): array
    {
        $customer = Customer::create([
            'customer_number' => 'CU-RCV-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Juma',
            'last_name' => 'Deni',
            'phone' => '25572'.random_int(1000000, 9999999),
            'region' => 'Dar es Salaam',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-RCV-'.random_int(100, 999),
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-RCV-'.random_int(100, 999),
            'status' => 'disbursed',
            'current_stage' => 'disbursement',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-RCV-'.random_int(100, 999),
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 400_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'status' => 'active',
        ]);

        $arrearCase = ArrearCase::create([
            'loan_id' => $loan->id,
            'days_past_due' => 14,
            'amount_in_arrears' => 50_000,
            'penalty_amount' => 2_500,
            'status' => 'open',
        ]);

        $current = Vendor::create([
            'vendor_number' => 'PT-CC-A-'.random_int(100, 999),
            'name' => 'First Call Center',
            'category' => 'call_center',
            'status' => 'active',
            'phone' => '255701'.random_int(100000, 999999),
            'coverage_type' => 'nationwide',
        ]);

        $replacement = Vendor::create([
            'vendor_number' => 'PT-CC-B-'.random_int(100, 999),
            'name' => 'Second Call Center',
            'category' => 'call_center',
            'status' => 'active',
            'phone' => '255701'.random_int(100000, 999999),
            'coverage_type' => 'nationwide',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $assignment = app(RecoveryAssignmentService::class)->assign(
            $arrearCase->fresh(['loan.customer']),
            $current,
            'call_center',
            $admin,
            'Initial recovery.',
        );

        return [$assignment->vendorTask, $assignment, $replacement];
    }
}
