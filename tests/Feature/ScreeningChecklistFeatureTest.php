<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ScreeningChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningChecklistFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        $branch = Branch::create([
            'code' => 'SC'.random_int(10, 99),
            'name' => 'Screening Checklist Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => $role,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'SC-'.random_int(100, 999),
            'name' => 'Screening Checklist Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SC-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Checklist',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $actor->branch_id,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $actor->branch_id,
            'application_number' => 'APP-SC-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }

    public function test_checklist_tab_shows_grouped_items_for_screening(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'tab' => 'checklist',
                'person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Verification checklist', $html);
        $this->assertStringContainsString('Compare NIDA number to date of birth', $html);
        $this->assertStringContainsString('Map customer name to CRB report', $html);
        $this->assertStringContainsString('Check with Local Government Officer', $html);
        $this->assertStringContainsString('Check with guarantor', $html);
        $this->assertStringContainsString('Check insurance cover and expiry deadline', $html);
        $this->assertStringContainsString('Save checklist', $html);
        $this->assertStringContainsString('tab=checklist', $html);
    }

    public function test_screening_can_save_and_uncheck_checklist_items(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'items' => [
                    'identity' => ['nida_vs_dob' => '1'],
                    'contacts' => ['call_guarantor' => '1'],
                ],
            ])
            ->assertRedirect();

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.items', []);
        $this->assertTrue((bool) ($items['identity.nida_vs_dob']['checked'] ?? false));
        $this->assertTrue((bool) ($items['contacts.call_guarantor']['checked'] ?? false));
        $this->assertSame($admin->id, (int) ($items['identity.nida_vs_dob']['by'] ?? 0));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'items' => [
                    'identity' => ['nida_vs_dob' => '1'],
                ],
            ])
            ->assertRedirect();

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.items', []);
        $this->assertTrue((bool) ($items['identity.nida_vs_dob']['checked'] ?? false));
        $this->assertArrayNotHasKey('contacts.call_guarantor', $items);
    }

    public function test_committee_inputs_show_checklist_progress_link(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $app->update([
            'current_stage' => 'pre_approval',
            'recommendation_type' => 'approve',
            'recommended_amount' => 400_000,
            'recommended_at' => now(),
            'recommended_by' => $admin->id,
            'screening_payload' => [
                'screening_checklist' => [
                    'items' => [
                        'identity.nida_vs_dob' => [
                            'checked' => true,
                            'at' => now()->toIso8601String(),
                            'by' => $admin->id,
                        ],
                    ],
                    'updated_at' => now()->toIso8601String(),
                    'updated_by' => $admin->id,
                ],
            ],
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('View what was done', $html);
        $this->assertStringContainsString('Checklist 1/', $html);
    }

    public function test_view_model_marks_non_reviewers_read_only(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $viewer = $this->staff('credit_committee');

        $vm = app(ScreeningChecklistService::class)->viewModel($app, $viewer);

        $this->assertFalse($vm['can_edit']);
        $this->assertNotEmpty($vm['groups']);
        $this->assertGreaterThan(0, $vm['total']);
    }
}
