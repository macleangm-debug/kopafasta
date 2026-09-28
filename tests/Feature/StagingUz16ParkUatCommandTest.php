<?php

namespace Tests\Feature;

use App\Console\Commands\StagingUz16ParkUatCommand;
use App\Models\Branch;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\ApplicationIntakeReadinessService;
use App\Services\CapacityAutoRejectService;
use App\Services\ProfileCompletionService;
use Database\Seeders\KycDocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingUz16ParkUatCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_parks_via_canonical_first_gate_and_does_not_reject(): void
    {
        $this->seed(KycDocumentTypeSeeder::class);
        Branch::create([
            'code' => 'UAT1',
            'name' => 'UAT Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        LoanProduct::create([
            'code' => 'IL-UAT',
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 50_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);

        $this->artisan('staging:uz16-park-uat')->assertSuccessful();

        $application = LoanApplication::query()
            ->where('application_number', StagingUz16ParkUatCommand::APPLICATION_NUMBER)
            ->with('customer')
            ->first();
        $this->assertNotNull($application);
        $this->assertTrue(app(ProfileCompletionService::class)->isFullyComplete($application->customer));
        $this->assertSame('submitted', $application->status);
        $this->assertSame(ApplicationIntakeReadinessService::STATE_HOLD, $application->current_stage);
        $this->assertTrue(app(CapacityAutoRejectService::class)->isPending($application));
        $this->assertSame(0, GuarantorInvitation::query()->where('loan_application_id', $application->id)->count());
        $this->assertNotSame('rejected', $application->status);
        $this->assertSame(750_000.0, (float) $application->customer->monthly_income);
        $this->assertSame(40_800_000.0, (float) $application->requested_amount);
    }
}
