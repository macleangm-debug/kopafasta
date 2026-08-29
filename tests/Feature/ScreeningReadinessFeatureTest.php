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
        $this->assertStringContainsString('Review in progress', $readiness['headline']);
        $this->assertNotEmpty($readiness['next_steps']);
        $this->assertArrayHasKey('href', $readiness['next_steps'][0]);
        $this->assertTrue($readiness['income_gate_open']);
        $this->assertSame('gate', $readiness['next_steps'][0]['tone'] ?? null);
        $this->assertStringContainsString('Statement totals', $readiness['next_steps'][0]['label'] ?? '');
        $this->assertIsArray($readiness['auto_completed'] ?? null);
        $this->assertIsArray($readiness['blocking_items'] ?? null);
        $this->assertIsArray($readiness['needs_attention'] ?? null);
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
                $row = ['verdict' => 'pass'];
                if ($groupKey === 'activity_income' && $itemKey === 'income_evidence') {
                    $row['statement_deposits_total'] = 6_000_000;
                    $row['statement_months'] = 6;
                }
                $items[$groupKey][$itemKey] = $row;
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
        $this->assertSame('Ready for decision', $readiness['headline']);
    }

    public function test_income_attention_cta_never_routes_to_collateral(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $request = \App\Models\LoanApplicationDocumentRequest::create([
            'loan_application_id' => $app->id,
            'requested_by' => $admin->id,
            'label' => 'Updated Mobile Money Statement',
            'type' => 'document',
            'status' => 'pending',
        ]);

        $readiness = app(ScreeningReadinessService::class)->forApplication(
            $app->fresh(),
            ['customer' => $app->customer, 'affordability' => ['verdict' => 'pass'], 'crb' => ['score' => 700, 'recommendation' => 'approve']],
            null,
            [],
            $admin,
        );

        $this->assertFalse($readiness['ready']);
        $this->assertSame('Needs attention', $readiness['headline']);
        $income = collect($readiness['blocking_items'])->first(
            fn ($row) => str_contains((string) ($row['label'] ?? ''), 'Mobile Money')
        );
        $this->assertNotNull($income);
        $this->assertSame('Review statements', $income['cta']);
        $this->assertStringContainsString('gate=income', $income['href']);
        $this->assertStringContainsString('activity_income', $income['href']);
        $this->assertStringNotContainsString('open_group=collateral', $income['href']);
        $this->assertStringNotContainsString('desk_phase=security', $income['href']);
        $this->assertStringContainsString('Updated Mobile Money Statement', $income['label']);
        $this->assertStringNotContainsString('required document(s) not verified', json_encode($readiness['unresolved'] ?? []));
    }

    public function test_overview_snapshot_and_named_blockers_share_unresolved_list(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        \App\Models\LoanApplicationDocumentRequest::create([
            'loan_application_id' => $app->id,
            'requested_by' => $admin->id,
            'label' => 'Updated vehicle insurance certificate',
            'type' => 'document',
            'status' => 'pending',
            'created_at' => now()->subDays(2),
        ]);

        $readiness = app(ScreeningReadinessService::class)->forApplication(
            $app->fresh(),
            ['customer' => $app->customer, 'affordability' => ['verdict' => 'pass'], 'crb' => ['score' => 612, 'recommendation' => 'refer']],
            null,
            [],
            $admin,
        );

        $named = collect($readiness['blocking_items'])->first(
            fn ($row) => str_contains((string) ($row['label'] ?? ''), 'Updated vehicle insurance certificate')
        );
        $this->assertNotNull($named);
        $this->assertContains($named['cta'], ['Open request', 'Open collateral']);
        $this->assertStringContainsString('Requested', (string) ($named['detail'] ?? ''));
        $this->assertNotEmpty($readiness['overview_snapshot'] ?? []);
        $this->assertNotEmpty($readiness['gate_chips'] ?? []);
        $this->assertSame($readiness['unresolved'][0]['label'] ?? null, $named['label']);
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
            'monthly_income' => 2_000_000,
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
