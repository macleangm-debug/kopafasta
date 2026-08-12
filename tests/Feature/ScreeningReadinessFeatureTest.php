<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ScreeningReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningReadinessFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_checklist_holds_and_incomplete_file_is_not_ready(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $readiness = app(ScreeningReadinessService::class)->forApplication(
            $app,
            ['customer' => $app->customer, 'affordability' => ['verdict' => 'pass'], 'crb' => ['score' => 700, 'recommendation' => 'approve']],
            null,
            [],
            $admin,
        );

        $this->assertFalse($readiness['ready']);
        $this->assertSame('hold', $readiness['suggestion']);
        $this->assertStringContainsString('Not ready', $readiness['headline']);
        $this->assertNotEmpty($readiness['next_steps']);
        $this->assertArrayHasKey('href', $readiness['next_steps'][0]);
        $this->assertTrue($readiness['income_gate_open']);
        $this->assertSame('gate', $readiness['next_steps'][0]['tone'] ?? null);
        $this->assertStringContainsString('Gate 2', $readiness['next_steps'][0]['label'] ?? '');
        $this->assertStringContainsString('N/A counts as reviewed', $readiness['na_note']);
    }

    public function test_affordability_fail_and_weak_crb_lean_reject_when_checklist_complete(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        // Mark all borrower checklist items pass so readiness can open.
        $catalog = config('screening_checklist');
        $items = [];
        foreach ($catalog as $groupKey => $group) {
            $subjects = $group['subjects'] ?? ['borrower'];
            if (! in_array('borrower', $subjects, true)) {
                continue;
            }
            foreach (array_keys($group['items'] ?? []) as $itemKey) {
                $items[$groupKey][$itemKey] = ['verdict' => 'pass'];
            }
        }
        app(\App\Services\ScreeningChecklistService::class)->save(
            $app,
            $admin,
            $items,
            'borrower',
        );

        $readiness = app(ScreeningReadinessService::class)->forApplication(
            $app,
            [
                'customer' => $app->customer,
                'affordability' => ['verdict' => 'fail', 'pass' => false],
                'crb' => ['score' => 420, 'recommendation' => 'reject'],
            ],
            null,
            [['severity' => 'critical', 'title' => 'Test', 'detail' => 'x']],
            $admin,
        );

        $this->assertTrue($readiness['ready']);
        $this->assertSame('reject', $readiness['suggestion']);
    }

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'SR'.random_int(10, 99),
            'name' => 'SR Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'SR-'.random_int(100, 999),
            'name' => 'SR Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SR-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Ready',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $actor->branch_id,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $actor->branch_id,
            'application_number' => 'APP-SR-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }
}
