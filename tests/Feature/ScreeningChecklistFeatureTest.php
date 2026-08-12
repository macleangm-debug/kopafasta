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
            'code' => 'SC'.random_int(1000, 9999),
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
            'national_id' => '19900101123456789012',
            'date_of_birth' => now()->subYears(30)->toDateString(),
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

    public function test_review_desk_shows_on_screening_page(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="review-desk"', $html);
        $this->assertStringContainsString('Review checklist', $html);
        $this->assertStringContainsString('Compare NIDA number to date of birth', $html);
        $this->assertStringContainsString('Pass ✓', $html);
        $this->assertStringContainsString('Fail ✗', $html);
        $this->assertStringNotContainsString('tab=checklist', $html);
    }

    public function test_pass_and_fail_with_reason_are_saved(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'identity' => [
                        'nida_vs_dob' => ['verdict' => 'pass'],
                        'face_vs_nida' => [
                            'verdict' => 'fail',
                            'fail_reason_code' => 'face_mismatch',
                        ],
                    ],
                ],
            ]);
        $response->assertRedirect();
        $this->assertStringContainsString('open_reject=1', $response->headers->get('Location'));
        $this->assertStringContainsString('workspace=decision', $response->headers->get('Location'));

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $this->assertSame('pass', $items['identity.nida_vs_dob']['verdict'] ?? null);
        $this->assertSame('fail', $items['identity.face_vs_nida']['verdict'] ?? null);
        $this->assertSame('face_mismatch', $items['identity.face_vs_nida']['fail_reason_code'] ?? null);

        $suggestion = app(ScreeningChecklistService::class)->suggestedRejection($app);
        $this->assertTrue($suggestion['prompt_reject']);
        $this->assertContains('face_verification_failed', $suggestion['codes']);
    }

    public function test_gate2_income_fail_maps_to_letter_reason_and_prompts_reject(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => [
                            'verdict' => 'fail',
                            'fail_reason_code' => 'revenue_mismatch',
                        ],
                    ],
                ],
            ]);
        $response->assertRedirect();
        $this->assertStringContainsString('open_reject=1', $response->headers->get('Location'));
        $response->assertSessionHas('checklist_reject_codes');

        $app->refresh();
        $suggestion = app(ScreeningChecklistService::class)->suggestedRejection($app);
        $this->assertTrue($suggestion['prompt_reject']);
        $this->assertContains('insufficient_income', $suggestion['codes']);
        $this->assertStringContainsString('Match financial statements', $suggestion['summary']);
        $this->assertSame('insufficient_income', $app->screening_rejection_reason_code);
    }

    public function test_fail_without_reason_is_rejected(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', $app))
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'identity' => [
                        'nida_vs_dob' => ['verdict' => 'fail'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
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

    public function test_group_member_checklist_progress_is_isolated_per_member(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $svc = app(ScreeningChecklistService::class);

        $svc->save($app, $admin, [
            'identity' => [
                'nida_vs_dob' => ['verdict' => 'pass'],
                'face_vs_nida' => [
                    'verdict' => 'fail',
                    'fail_reason_code' => 'face_mismatch',
                ],
            ],
        ], 'borrower');

        $svc->save($app->fresh(), $admin, [
            'identity' => [
                'nida_vs_dob' => ['verdict' => 'pass'],
            ],
        ], 'member', null, 10);

        $svc->save($app->fresh(), $admin, [
            'identity' => [
                'nida_vs_dob' => [
                    'verdict' => 'fail',
                    'fail_reason_code' => 'nida_dob_mismatch',
                ],
            ],
        ], 'member', null, 11);

        $app->refresh();
        $member10 = (array) data_get($app->screening_payload, 'screening_checklist.by_subject.member:10.items', []);
        $member11 = (array) data_get($app->screening_payload, 'screening_checklist.by_subject.member:11.items', []);
        $borrower = (array) data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items', []);

        $this->assertSame('pass', $member10['identity.nida_vs_dob']['verdict'] ?? null);
        $this->assertSame('fail', $member11['identity.nida_vs_dob']['verdict'] ?? null);
        $this->assertSame('fail', $borrower['identity.face_vs_nida']['verdict'] ?? null);

        $groupReview = [
            'members' => [
                ['id' => 1, 'role' => 'leader', 'name' => 'Leader', 'customer_id' => $app->customer_id, 'file' => []],
                ['id' => 10, 'role' => 'member', 'name' => 'Member A', 'customer_id' => $app->customer_id, 'file' => []],
                ['id' => 11, 'role' => 'member', 'name' => 'Member B', 'customer_id' => $app->customer_id, 'file' => []],
            ],
        ];

        $subjects = collect($svc->deskSubjects($app, ['customer' => $app->customer], $groupReview, $admin));
        $memberA = $subjects->firstWhere('key', 'member:10');
        $memberB = $subjects->firstWhere('key', 'member:11');
        $leader = $subjects->firstWhere('key', 'borrower');

        $this->assertNotNull($memberA);
        $this->assertNotNull($memberB);
        $this->assertNotNull($leader);
        // Same decided count is fine — both members have nida decided — but fails must differ,
        // and neither member chip may mirror the leader's borrower checklist totals.
        $this->assertNotSame($memberA['failed'], $memberB['failed']);
        $this->assertGreaterThan(0, (int) $memberB['failed']);
        $this->assertNotSame($leader['total'], $memberA['total']);
        $this->assertGreaterThan(0, (int) $memberA['total']);
    }
}
