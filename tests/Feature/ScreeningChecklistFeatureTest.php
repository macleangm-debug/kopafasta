<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
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
            'monthly_income' => 2_000_000,
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
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'checklist']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="review-desk"', $html);
        $this->assertStringContainsString('Review checklist', $html);
        $this->assertStringContainsString('Next action', $html);
        $this->assertStringContainsString('2.1 Statement totals', $html);
        $this->assertStringContainsString('Pass ✓', $html);
        $this->assertStringContainsString('Concern', $html);
        $this->assertStringContainsString('data-money-input', $html);
        $this->assertStringContainsString('items[activity_income][income_evidence][statement_deposits_total]', $html);
        $this->assertStringNotContainsString('items[activity_income][income_evidence][verdict]', $html);
        $this->assertStringContainsString('Period is always 6 months', $html);
        $this->assertStringNotContainsString('tab=checklist', $html);
        $this->assertStringNotContainsString('>Expand</span>', $html);
        $this->assertStringContainsString('2.4 Affordability', $html);
        $this->assertStringContainsString('System checked', $html);
        $this->assertStringContainsString('Needs attention', $html);
        $this->assertStringContainsString('3 Identity · Locked', $html);
        $this->assertStringContainsString('Income &amp; Statement Review to continue screening.', $html);
    }

    public function test_overview_shows_four_cards_and_checklist_hides_them(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $overview = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Facility summary', $overview);
        $this->assertStringContainsString('Borrower CRB', $overview);
        $this->assertStringContainsString('Open review checklist', $overview);
        $this->assertStringNotContainsString('id="review-desk"', $overview);
        $this->assertStringNotContainsString('>Expand</span>', $overview);

        $checklist = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'checklist']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="review-desk"', $checklist);
        $this->assertStringContainsString('id="screening-readiness"', $checklist);
        $this->assertStringContainsString('2.1 Statement totals', $checklist);
        $this->assertStringContainsString('Borrower submissions', $checklist);
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
        $this->assertStringContainsString('workspace=checklist', $response->headers->get('Location'));

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
        $app->customer->update(['monthly_income' => 2_000_000]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => [
                            'statement_deposits_total' => 600_000,
                            'statement_months' => 6,
                        ],
                    ],
                ],
            ]);
        $response->assertRedirect();
        $this->assertStringContainsString('workspace=checklist', $response->headers->get('Location'));
        $response->assertSessionHas('checklist_reject_codes');

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $item = $items['activity_income.income_evidence'] ?? [];
        $this->assertSame('fail', $item['verdict'] ?? null);
        $this->assertSame('revenue_mismatch', $item['fail_reason_code'] ?? null);
        $this->assertSame('system', $item['source'] ?? null);

        $suggestion = app(ScreeningChecklistService::class)->suggestedRejection($app);
        $this->assertTrue($suggestion['prompt_reject']);
        $this->assertContains('insufficient_income', $suggestion['codes']);
        $this->assertStringContainsString('Statement totals', $suggestion['summary']);
        $this->assertSame('insufficient_income', $app->screening_rejection_reason_code);
    }

    public function test_nida_dob_is_derived_from_the_first_eight_digits(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $customer = $app->customer;
        $customer->forceFill([
            'national_id' => '19900924xxxxxxxxxxxx',
            'date_of_birth' => '1990-09-24',
        ])->save();
        $customer = $customer->fresh();

        $suggestions = app(\App\Services\ScreeningChecklistAutoVerdictService::class)->suggest(
            $app->fresh(),
            'borrower',
            ['customer' => $customer],
        );

        $this->assertSame('pass', $suggestions['identity.nida_vs_dob']['verdict'] ?? null);

        $customer->forceFill(['date_of_birth' => '1988-03-12'])->save();
        $mismatch = app(\App\Services\ScreeningChecklistAutoVerdictService::class)->suggest(
            $app->fresh(),
            'borrower',
            ['customer' => $customer->fresh()],
        );
        $this->assertSame('fail', $mismatch['identity.nida_vs_dob']['verdict'] ?? null);
        $this->assertSame('nida_dob_mismatch', $mismatch['identity.nida_vs_dob']['fail_reason_code'] ?? null);

        $customer->forceFill(['national_id' => '12'])->save();
        $short = app(\App\Services\ScreeningChecklistAutoVerdictService::class)->suggest(
            $app->fresh(),
            'borrower',
            ['customer' => $customer->fresh()],
        );
        $this->assertSame('nida_malformed', $short['identity.nida_vs_dob']['fail_reason_code'] ?? null);

        $customer->forceFill(['national_id' => '123456789102984678399477484'])->save();
        $impossible = app(\App\Services\ScreeningChecklistAutoVerdictService::class)->suggest(
            $app->fresh(),
            'borrower',
            ['customer' => $customer->fresh()],
        );
        $this->assertSame('nida_impossible', $impossible['identity.nida_vs_dob']['fail_reason_code'] ?? null);

        $named = app(\App\Services\ScreeningChecklistAutoVerdictService::class)->suggest(
            $app->fresh(),
            'borrower',
            ['customer' => $customer->fresh(), 'crb' => []],
        );
        $this->assertSame('crb_never_checked', $named['identity.name_vs_crb']['fail_reason_code'] ?? null);
        $this->assertSame('photos_missing', $named['identity.face_vs_nida']['fail_reason_code'] ?? null);
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

    public function test_activity_checklist_pulls_profile_activity_details(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $app->customer->update([
            'activity_type' => 'employed',
            'income_range' => '1m_5m',
            'monthly_income' => 1_500_000,
            'activity_details' => [
                'employer_name' => 'Dar Logistics Ltd',
                'job_title' => 'Dispatcher',
            ],
        ]);

        $vm = app(ScreeningChecklistService::class)->viewModel($app->fresh(['customer']), $admin);
        $item = collect($vm['groups'] ?? [])
            ->flatMap(fn ($g) => $g['items'] ?? [])
            ->firstWhere('key', 'activity_income.activity_plausible');

        $this->assertNotNull($item);
        $labels = collect($item['evidence']['rows'] ?? [])->pluck('value')->implode(' | ');
        $this->assertStringContainsString('Dar Logistics Ltd', $labels);
        $this->assertStringContainsString('Dispatcher', $labels);
        $this->assertStringContainsString('Activity documents', (string) ($item['evidence']['documents_heading'] ?? ''));
    }

    public function test_statement_pattern_fail_prompts_reject_with_letter_code(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'bank_or_mobile_money' => [
                            'verdict' => 'fail',
                            'fail_reason_code' => 'gambling_betting',
                        ],
                    ],
                ],
            ]);
        $response->assertRedirect();
        $this->assertStringContainsString('workspace=checklist', $response->headers->get('Location'));

        $suggestion = app(ScreeningChecklistService::class)->suggestedRejection($app->fresh());
        $this->assertTrue($suggestion['prompt_reject']);
        $this->assertContains('unstable_income_pattern', $suggestion['codes']);
        $this->assertStringContainsString('Gambling', $suggestion['summary']);
    }

    public function test_gate2_pass_requires_statement_totals(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', $app))
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_gate2_pass_stores_monthly_and_weekly_from_total_deposits(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $app->customer->update(['monthly_income' => 1_000_000]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => [
                            'verdict' => 'pass',
                            'statement_deposits_total' => 6_000_000,
                            'statement_months' => 6,
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $item = $items['activity_income.income_evidence'] ?? [];
        $this->assertSame('pass', $item['verdict'] ?? null);
        $this->assertEquals(6_000_000, (float) ($item['statement_deposits_total'] ?? 0));
        $this->assertSame(6, (int) ($item['statement_months'] ?? 0));
        $this->assertEquals(1_000_000, (float) ($item['statement_monthly'] ?? 0));
        $this->assertEquals(round(1_000_000 * 12 / 52, 2), (float) ($item['statement_weekly'] ?? 0));
    }

    public function test_gate2_parses_comma_formatted_deposits(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $app->customer->update(['monthly_income' => 1_000_000]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => [
                            'statement_deposits_total' => '6,000,000',
                            'statement_months' => 6,
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $item = $items['activity_income.income_evidence'] ?? [];
        $this->assertSame('pass', $item['verdict'] ?? null);
        $this->assertEquals(6_000_000, (float) ($item['statement_deposits_total'] ?? 0));
        $this->assertEquals(1_000_000, (float) ($item['statement_monthly'] ?? 0));
        $this->assertSame('system', $item['source'] ?? null);
    }

    public function test_gate2_save_without_verdict_auto_passes_when_monthly_covers_declared(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $app->customer->update(['monthly_income' => 1_000_000]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => [
                            'statement_deposits_total' => 6_000_000,
                            'statement_months' => 6,
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $app->refresh();
        $items = data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $item = $items['activity_income.income_evidence'] ?? [];
        $this->assertSame('pass', $item['verdict'] ?? null);
        $this->assertSame('system', $item['source'] ?? null);
        $this->assertNull($item['fail_reason_code'] ?? null);
    }

    public function test_gate2_explicit_fail_without_deposits_still_works(): void
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
                            'fail_reason_code' => 'statements_missing',
                        ],
                    ],
                ],
            ]);
        $response->assertRedirect();
        $this->assertStringContainsString('workspace=checklist', $response->headers->get('Location'));

        $items = data_get($app->fresh()->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $item = $items['activity_income.income_evidence'] ?? [];
        $this->assertSame('fail', $item['verdict'] ?? null);
        $this->assertSame('statements_missing', $item['fail_reason_code'] ?? null);
    }

    public function test_group_collateral_stays_on_leader_desk_and_does_not_apply_on_unsecured_il(): void
    {
        $admin = $this->staff();
        $il = $this->application($admin);
        CustomerAsset::create([
            'customer_id' => $il->customer_id,
            'asset_type' => 'land',
            'label' => 'Plot on IL profile',
            'is_active' => true,
            'photo_paths' => ['a.jpg', 'b.jpg'],
            'metadata' => ['ownership_document_path' => 'own.pdf'],
        ]);

        $svc = app(ScreeningChecklistService::class);
        $this->assertFalse($svc->collateralReviewApplies($il->fresh()));

        $product = LoanProduct::create([
            'code' => 'GL',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $leader = $il->customer;
        $memberUser = User::factory()->create(['role' => 'borrower']);
        $member = Customer::create([
            'user_id' => $memberUser->id,
            'customer_number' => 'CU-SC-GM-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rogathe',
            'last_name' => 'Member',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $admin->branch_id,
        ]);
        $app = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $admin->branch_id,
            'application_number' => 'APP-GL-SC-'.random_int(1000, 9999),
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-SC-'.random_int(100, 999),
            'name' => 'Collateral Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $app->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $app->id,
            'role' => 'leader',
            'requested_amount' => 400_000,
            'sort_order' => 1,
            'member_status' => 'active',
        ]);
        $memberRow = LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $member->id,
            'loan_application_id' => $app->id,
            'role' => 'member',
            'requested_amount' => 400_000,
            'sort_order' => 2,
            'member_status' => 'active',
        ]);
        $app->update(['loan_group_id' => $group->id]);

        CustomerAsset::create([
            'customer_id' => $member->id,
            'asset_type' => 'land',
            'label' => 'Member plot',
            'is_active' => true,
            'photo_paths' => ['c.jpg', 'd.jpg'],
            'metadata' => ['ownership_document_path' => 'own2.pdf'],
        ]);

        $this->assertFalse($svc->collateralReviewApplies($app->fresh(), 'member'));
        $this->assertTrue($svc->collateralReviewApplies($app->fresh(), 'borrower'));

        $groupReview = [
            'members' => [
                ['id' => 1, 'role' => 'leader', 'name' => 'Leader', 'customer_id' => $leader->id, 'file' => []],
                ['id' => $memberRow->id, 'role' => 'member', 'name' => 'Rogathe', 'customer_id' => $member->id, 'file' => []],
            ],
        ];
        $memberVm = $svc->viewModel($app->fresh(), $admin, 'member', null, $memberRow->id, ['customer' => $member], $groupReview);
        $this->assertNull(collect($memberVm['groups'] ?? [])->firstWhere('key', 'collateral'));

        $leaderVm = $svc->viewModel($app->fresh(), $admin, 'borrower', null, null, ['customer' => $leader], $groupReview);
        $collateral = collect($leaderVm['groups'] ?? [])->firstWhere('key', 'collateral');
        $this->assertNotNull($collateral);
        $this->assertGreaterThan(0, (int) ($collateral['total'] ?? 0));
        $this->assertNull(collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.ownership_docs'));
        $this->assertNull(collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.valuer_assigned'));
        $this->assertNotNull(collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.valuation_fee'));
        $ltv = collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.ltv_covers');
        $this->assertNotNull($ltv);
        $this->assertTrue($ltv['awaiting_data'] ?? false);
        $this->assertSame('There is no data for this checklist', $ltv['awaiting_message'] ?? null);
        $this->assertTrue($ltv['catalog_system'] ?? false);
        $this->assertTrue($ltv['read_only'] ?? false);
        $fee = collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.valuation_fee');
        $this->assertTrue($fee['catalog_system'] ?? false);
        $this->assertTrue(($fee['awaiting_data'] ?? false) || in_array($fee['verdict'] ?? null, ['pass', 'fail', 'na'], true));
        $this->assertStringNotContainsString('Confirm valuer assignment', json_encode($collateral));
        $this->assertStringNotContainsString('Review ownership / transfer documents', json_encode($collateral));

        $this->assertFalse(
            LoanApplicationAsset::query()
                ->where('loan_application_id', $app->id)
                ->whereHas('customerAsset', fn ($q) => $q->where('customer_id', $member->id))
                ->exists()
        );
    }

    public function test_group_party_label_uses_leader_first_name_plus_other_members(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $leader = $app->customer;
        $leader->update(['first_name' => 'Gaspari', 'last_name' => 'Shiliba']);
        $product = LoanProduct::create([
            'code' => 'GL-NM',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $app->update(['loan_product_id' => $product->id]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-NM-'.random_int(100, 999),
            'name' => 'Nyella',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $app->id,
            'status' => 'active',
            'target_member_count' => 4,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $app->id,
            'role' => 'leader',
            'requested_amount' => 200_000,
            'sort_order' => 1,
            'member_status' => 'active',
        ]);
        for ($i = 2; $i <= 4; $i++) {
            $member = Customer::create([
                'user_id' => User::factory()->create(['role' => 'borrower'])->id,
                'customer_number' => 'CU-NM-'.$i.random_int(10, 99),
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Member'.$i,
                'last_name' => 'Nyella',
                'phone' => '25571'.random_int(1000000, 9999999),
                'branch_id' => $admin->branch_id,
            ]);
            LoanGroupMember::create([
                'loan_group_id' => $group->id,
                'customer_id' => $member->id,
                'loan_application_id' => $app->id,
                'role' => 'member',
                'requested_amount' => 200_000,
                'sort_order' => $i,
                'member_status' => 'active',
            ]);
        }
        $app->update(['loan_group_id' => $group->id]);

        $this->assertSame('Gaspari + 3 others', $app->fresh()->partyLabel());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('Gaspari + 3 others', false)
            ->assertDontSee('Review ownership / transfer documents', false);
    }

    public function test_collateral_inbox_clears_only_when_every_collateral_check_has_a_verdict(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $product = LoanProduct::create([
            'code' => 'GL-COL',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $app->update(['loan_product_id' => $product->id]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-COL-'.random_int(100, 999),
            'name' => 'Collateral Inbox',
            'leader_customer_id' => $app->customer_id,
            'primary_application_id' => $app->id,
            'status' => 'active',
            'target_member_count' => 1,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $app->customer_id,
            'loan_application_id' => $app->id,
            'role' => 'leader',
            'requested_amount' => 500_000,
            'sort_order' => 1,
            'member_status' => 'active',
        ]);
        $app->update(['loan_group_id' => $group->id]);

        $docService = app(ApplicationDocumentRequestService::class);
        $request = $docService->create($app->fresh(), $admin, 'Add collateral asset');
        $request->update(['status' => 'uploaded']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'collateral' => [
                        'asset_identity' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect();

        $items = data_get($app->fresh()->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $this->assertFalse(app(ScreeningChecklistService::class)->collateralChecksComplete($items, 'borrower'));
        $this->assertSame('uploaded', $request->fresh()->status);

        $complete = [];
        foreach (array_keys(config('screening_checklist.collateral.items')) as $key) {
            $complete['collateral.'.$key] = ['verdict' => 'pass'];
        }
        $this->assertTrue(app(ScreeningChecklistService::class)->collateralChecksComplete($complete, 'borrower'));

        $this->assertSame(1, $docService->satisfyUploadedCollateralRequests(
            $app->fresh(),
            $admin,
            'borrower',
            $app->customer_id,
        ));
        $this->assertSame('satisfied', $request->fresh()->status);
    }

    public function test_crb_wrap_does_not_auto_pass_a_clean_score_and_links_to_the_real_tabs(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $vm = app(ScreeningChecklistService::class)->viewModel($app, $admin, 'borrower', null, null, [
            'customer' => $app->customer,
            'crb' => [
                'recommendation' => 'approve',
                'score' => 701,
                'existing_loans' => 0,
                'outstanding_balance' => 0,
                'delinquencies' => 0,
            ],
        ]);
        $item = collect($vm['groups'] ?? [])
            ->flatMap(fn ($group) => $group['items'] ?? [])
            ->firstWhere('key', 'credit_file.crb_reviewed');

        $this->assertNotNull($item);
        $this->assertNull($item['verdict'] ?? null);
        $encoded = json_encode($item['evidence']['rows'] ?? []);
        $this->assertStringContainsString('Open CRB', $encoded);
        $this->assertStringContainsString('capacity_tab=crb', $encoded);
        $this->assertStringContainsString('activity_income.income_evidence', $encoded);
        $this->assertStringContainsString('capacity_tab=checks', $encoded);
        $this->assertStringNotContainsString('filter Missing / To verify', $encoded);
    }

    public function test_crb_wrap_auto_fails_on_bureau_delinquencies(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $vm = app(ScreeningChecklistService::class)->viewModel($app, $admin, 'borrower', null, null, [
            'customer' => $app->customer,
            'crb' => [
                'recommendation' => 'refer',
                'score' => 400,
                'existing_loans' => 2,
                'outstanding_balance' => 1_500_000,
                'delinquencies' => 2,
            ],
        ]);
        $item = collect($vm['groups'] ?? [])
            ->flatMap(fn ($group) => $group['items'] ?? [])
            ->firstWhere('key', 'credit_file.crb_reviewed');

        $this->assertSame('fail', $item['verdict'] ?? null);
        $this->assertSame('delinquencies', $item['fail_reason_code'] ?? null);
    }

    public function test_recommendation_ready_auto_passes_when_the_rest_of_the_desk_is_decided(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $items = [];
        foreach (app(ScreeningChecklistService::class)->catalog('borrower') as $groupKey => $group) {
            foreach (array_keys($group['items'] ?? []) as $itemKey) {
                $full = $groupKey.'.'.$itemKey;
                if ($full === 'credit_file.recommendation_ready') {
                    continue;
                }
                $items[$full] = ['verdict' => 'pass', 'source' => 'staff', 'by' => $admin->id];
            }
        }
        $app->update([
            'screening_payload' => [
                'screening_checklist' => [
                    'by_subject' => [
                        'borrower' => ['items' => $items],
                    ],
                ],
            ],
        ]);

        $vm = app(ScreeningChecklistService::class)->viewModel($app->fresh(), $admin, 'borrower', null, null, [
            'customer' => $app->customer,
        ]);
        $ready = collect($vm['groups'] ?? [])
            ->flatMap(fn ($group) => $group['items'] ?? [])
            ->firstWhere('key', 'credit_file.recommendation_ready');

        $this->assertSame('pass', $ready['verdict'] ?? null);
        $this->assertTrue($ready['system_checked'] ?? false);
    }

    public function test_stale_missing_document_fails_clear_when_nothing_is_outstanding(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $app->update([
            'screening_payload' => [
                'screening_checklist' => [
                    'by_subject' => [
                        'borrower' => [
                            'items' => [
                                'documents.required_docs_complete' => [
                                    'verdict' => 'fail',
                                    'fail_reason_code' => 'docs_missing',
                                    'source' => null,
                                ],
                                'documents.requested_docs_reviewed' => [
                                    'verdict' => 'fail',
                                    'fail_reason_code' => 'still_open',
                                    'source' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $vm = app(ScreeningChecklistService::class)->viewModel($app->fresh(), $admin, 'borrower', null, null, [
            'customer' => $app->customer,
            'documents' => ['required' => 0, 'satisfied' => 0],
        ]);
        $items = collect($vm['groups'] ?? [])->flatMap(fn ($group) => $group['items'] ?? []);
        $required = $items->firstWhere('key', 'documents.required_docs_complete');
        $requested = $items->firstWhere('key', 'documents.requested_docs_reviewed');

        $this->assertSame('pass', $required['verdict'] ?? null);
        $this->assertSame('pass', $requested['verdict'] ?? null);
    }

    public function test_discrepancy_waiver_is_stored_without_changing_crb(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);
        $crbBefore = $app->credit_appraisal_payload;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.discrepancy-waiver', $app), [
                'code' => 'spouse_missing_on_crb',
                'reason' => 'CRB data does not contain spouse information; verified from borrower profile and supporting documentation.',
            ])
            ->assertRedirect();

        $app->refresh();
        $waiver = data_get($app->screening_payload, 'discrepancy_waivers.spouse_missing_on_crb');
        $this->assertIsArray($waiver);
        $this->assertSame($admin->id, $waiver['by'] ?? null);
        $this->assertStringContainsString('CRB data does not contain spouse', (string) ($waiver['reason'] ?? ''));
        $this->assertEquals($crbBefore, $app->credit_appraisal_payload);
    }

    public function test_retracting_a_document_request_stops_blocking_committee(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'type' => 'document',
                'presets' => ['Updated Bank Statement'],
                'request_subject' => 'borrower',
                'confirmed' => '1',
            ])
            ->assertRedirect();

        $request = $app->fresh()->documentRequests()->first();
        $this->assertNotNull($request);
        $this->assertNotEmpty(app(\App\Services\LoanApplicationWorkflowService::class)->screeningDocumentBlockers($app->fresh()));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.cancel', $app), [
                'ids' => [$request->id],
                'confirmed' => '1',
                'reason' => 'No longer required for this file',
                'return_workspace' => 'checklist',
            ])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertStringContainsString('Retracted by', (string) $request->admin_notes);
        $this->assertStringContainsString('No longer required', (string) $request->admin_notes);
        $blockers = app(\App\Services\LoanApplicationWorkflowService::class)->screeningDocumentBlockers($app->fresh());
        $this->assertFalse(collect($blockers)->contains(fn ($label) => str_contains((string) $label, 'Updated Bank Statement')));
    }
}
