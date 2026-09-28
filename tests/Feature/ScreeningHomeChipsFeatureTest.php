<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningHomeChipsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_screening_home_chips_use_short_labels_and_canonical_counts(): void
    {
        $admin = $this->staff();
        $this->parked();
        $this->ready();
        $this->awaiting();
        $this->active();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.teams.screening'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('admin.intake.system_sorted_chip_parked').' · 1', $html);
        $this->assertStringContainsString(__('admin.intake.system_sorted_chip_ready').' · 1', $html);
        $this->assertStringContainsString(__('admin.intake.system_sorted_chip_awaiting').' · 1', $html);
        $this->assertStringContainsString(route('admin.loan-applications.pipeline.system-sorted', ['section' => 'parked']), $html);
        $this->assertStringContainsString('Do now', $html);
        $this->assertStringContainsString('APP-IL-SCRN', $html);
        $this->assertStringNotContainsString('APP-IL-LQU6', $html);
        $this->assertStringNotContainsString('APP-IL-FF24', $html);
        $this->assertStringNotContainsString('APP-IL-RZNT', $html);
    }

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'CH'.random_int(10, 99),
            'name' => 'Chip Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function product(): LoanProduct
    {
        return LoanProduct::query()->first() ?? LoanProduct::create([
            'code' => 'CH-1',
            'name' => 'Chip Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
    }

    private function borrower(string $tag): Customer
    {
        return Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-CH-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => $tag,
            'last_name' => 'Chip',
            'phone' => '25571'.random_int(1000000, 9999999),
        ]);
    }

    private function parked(): void
    {
        LoanApplication::create([
            'customer_id' => $this->borrower('Parked')->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-IL-FF24',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'initial_decision_hold',
            'submitted_at' => now(),
            'screening_payload' => [
                'capacity_auto_reject' => [
                    'status' => CapacityAutoRejectService::STATUS_PENDING,
                    'auto_reject_at' => now()->addHours(12)->toIso8601String(),
                ],
            ],
        ]);
    }

    private function ready(): void
    {
        LoanApplication::create([
            'customer_id' => $this->borrower('Ready')->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-IL-LQU6',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'ready_for_screening',
            'submitted_at' => now(),
            'screening_payload' => [
                'intake' => ['initial_gate' => ['result' => 'passed']],
            ],
        ]);
    }

    private function awaiting(): void
    {
        LoanApplication::create([
            'customer_id' => $this->borrower('Await')->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-IL-RZNT',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now(),
        ]);
    }

    private function active(): void
    {
        LoanApplication::create([
            'customer_id' => $this->borrower('Active')->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-IL-SCRN',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }
}
