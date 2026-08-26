<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Services\CreditDeskAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditDeskAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_screening_and_committee_teams_cannot_combine(): void
    {
        $und = Department::query()->create(['name' => 'Underwriting', 'code' => 'UND']);
        $crc = Department::query()->create(['name' => 'Credit Committee', 'code' => 'CRC']);

        $this->expectException(ValidationException::class);

        app(CreditDeskAssignmentService::class)->assertCompatible(
            'credit_analyst',
            [$und->id, $crc->id],
        );
    }

    public function test_committee_and_management_may_combine(): void
    {
        $crc = Department::query()->create(['name' => 'Credit Committee', 'code' => 'CRC']);
        $crm = Department::query()->create(['name' => 'Credit Management', 'code' => 'CRM']);

        app(CreditDeskAssignmentService::class)->assertCompatible(
            'credit_committee',
            [$crc->id, $crm->id],
        );

        $this->assertTrue(true);
    }

    public function test_admin_is_exempt(): void
    {
        $und = Department::query()->create(['name' => 'Underwriting', 'code' => 'UND']);
        $crc = Department::query()->create(['name' => 'Credit Committee', 'code' => 'CRC']);

        app(CreditDeskAssignmentService::class)->assertCompatible(
            'admin',
            [$und->id, $crc->id],
        );

        $this->assertTrue(true);
    }

    public function test_rejected_files_are_for_screening_and_committee_not_management(): void
    {
        $und = Department::query()->create(['name' => 'Underwriting', 'code' => 'UND']);
        $crc = Department::query()->create(['name' => 'Credit Committee', 'code' => 'CRC']);
        $crm = Department::query()->create(['name' => 'Credit Management', 'code' => 'CRM']);
        $desk = app(CreditDeskAssignmentService::class);

        $analyst = \App\Models\User::factory()->create(['role' => 'credit_analyst', 'department_id' => $und->id]);
        $committee = \App\Models\User::factory()->create(['role' => 'credit_committee', 'department_id' => $crc->id]);
        $manager = \App\Models\User::factory()->create(['role' => 'manager', 'department_id' => $crm->id]);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $this->assertTrue($desk->canViewRejected($analyst));
        $this->assertTrue($desk->canViewRejected($committee));
        $this->assertTrue($desk->canViewRejected($admin));
        $this->assertFalse($desk->canViewRejected($manager));
        $this->assertTrue($desk->isManagementOnly($manager));
        $this->assertFalse($desk->isManagementOnly($committee));
    }

    public function test_management_only_sees_committee_approved_authorization_and_post_approval_ops(): void
    {
        $und = Department::query()->create(['name' => 'Underwriting', 'code' => 'UND']);
        $crc = Department::query()->create(['name' => 'Credit Committee', 'code' => 'CRC']);
        $crm = Department::query()->create(['name' => 'Credit Management', 'code' => 'CRM']);
        $desk = app(CreditDeskAssignmentService::class);

        $manager = \App\Models\User::factory()->create(['role' => 'manager', 'department_id' => $crm->id]);
        $analyst = \App\Models\User::factory()->create(['role' => 'credit_analyst', 'department_id' => $und->id]);
        $committee = \App\Models\User::factory()->create(['role' => 'credit_committee', 'department_id' => $crc->id]);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $screening = new \App\Models\LoanApplication(['current_stage' => 'screening', 'status' => 'under_review']);
        $committeeFile = new \App\Models\LoanApplication(['current_stage' => 'pre_approval', 'status' => 'pre_approved']);
        $awaiting = new \App\Models\LoanApplication(['current_stage' => 'awaiting_management', 'status' => 'pre_approved']);
        $ops = new \App\Models\LoanApplication(['current_stage' => 'approval', 'status' => 'approved']);

        $this->assertFalse($desk->canViewApplication($manager, $screening));
        $this->assertFalse($desk->canViewApplication($manager, $committeeFile));
        $this->assertTrue($desk->canViewApplication($manager, $awaiting));
        $this->assertTrue($desk->canViewApplication($manager, $ops));
        $this->assertTrue($desk->canViewApplication($analyst, $screening));
        $this->assertTrue($desk->canViewApplication($committee, $committeeFile));
        $this->assertTrue($desk->canViewApplication($admin, $screening));
    }
}
