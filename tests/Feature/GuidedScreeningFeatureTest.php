<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CreditEligibilityPolicyService;
use App\Services\GroupLendingService;
use App\Services\GroupMemberReplacementService;
use App\Services\GuarantorSupplementService;
use App\Services\ScreeningChecklistService;
use App\Services\ScreeningExceptionService;
use App\Services\ScreeningNextActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GuidedScreeningFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_screening_opens_guided_wizard_and_persists_resume(): void
    {
        [$admin, $app] = $this->file();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->assertSee('Gate')
            ->assertSee('What happens next')
            ->assertSee('Kopafasta Credit')
            ->assertSee('Overall Screening');

        $app->refresh();
        $this->assertNotEmpty(data_get($app->screening_payload, 'guided.started_at'));
        $this->assertNotEmpty(data_get($app->screening_payload, 'guided.resume'));
    }

    public function test_statement_step_keeps_document_collapsed_and_opens_existing_preview(): void
    {
        [$admin, $app] = $this->file();
        $type = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => $type->id,
            'file_path' => 'customer/'.$app->customer_id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', [
                'loan_application' => $app,
                'at_item' => 'activity_income.income_evidence',
                'at_person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-guided-review', $html);
        $this->assertStringContainsString('Bank statement', $html);
        $this->assertStringContainsString('class="document-holder', $html);
        $this->assertStringContainsString('View document', $html);
        $this->assertStringContainsString('kfOpenDocumentPreview', $html);
        $this->assertStringNotContainsString('h-[80vh]', $html);
        $this->assertStringContainsString('Total deposits for the 6-month statement period', $html);
        $this->assertStringContainsString('Kopafasta records the totals and applies the existing Screening policy', $html);
        $this->assertStringNotContainsString('items[activity_income][income_evidence][verdict]', $html);
        $this->assertStringContainsString('id="kf-doc-drawer" class="fixed inset-0 z-[100] hidden"', $html);

        $desk = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Continue Reviewing', $desk);
        $this->assertStringContainsString(route('admin.loan-applications.guided-screening', $app), $desk);
        $this->assertStringNotContainsString('Review statements', $desk);
    }

    public function test_gate2_shows_engine_capacity_on_wizard_and_checklist_without_a_verdict(): void
    {
        [$admin, $app] = $this->file();
        $app->customer->update(['monthly_income' => 3_000_000]);
        $type = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => $type->id,
            'file_path' => 'customer/'.$app->customer_id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);
        $payload = $app->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'statement_deposits_total' => 11_265_630,
            'statement_months' => 6,
            'statement_monthly' => 1_877_605,
            'statement_weekly' => round(1_877_605 * 12 / 52, 2),
            'notes' => 'Reviewed the six-month total',
        ];
        $app->forceFill([
            'screening_payload' => $payload,
            'current_stage' => 'screening',
        ])->save();

        $evaluation = app(\App\Services\AffordabilityService::class)->evaluate($app->fresh());
        $ratioLabel = app(\App\Services\AffordabilityPolicyService::class)->formatRatioLabel(
            (float) $evaluation['repayment_ratio_pct']
        );
        $result = in_array($evaluation['verdict'], ['pass', 'warn'], true)
            ? '✓ CAPACITY PASSED'
            : '✕ CAPACITY FAILED';

        $wizard = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', [
                'loan_application' => $app,
                'at_item' => 'activity_income.income_evidence',
                'at_person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();
        $checklist = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
            ]))
            ->assertOk()
            ->getContent();

        foreach ([
            'VERIFIED REPAYMENT CAPACITY',
            'Required',
            'Supported',
            'Headroom',
            'Verified income',
            'Repayment',
            'Monthly evidence',
            'Policy',
            format_money((float) $evaluation['max_repayment_capacity']),
            format_money((float) $evaluation['proposed_installment']),
            $result,
            'Income discrepancy',
            '62.6%',
            '-'.format_money(1_122_395),
            format_money(1_877_605),
            $ratioLabel,
            'Evidence supports the required repayment.',
            'Affordability uses',
        ] as $needle) {
            $this->assertStringContainsString($needle, $wizard, 'wizard: '.$needle);
            if (! in_array($needle, ['Affordability uses'], true)) {
                $this->assertStringContainsString($needle, $checklist, 'checklist: '.$needle);
            }
        }
        $this->assertStringContainsString('Gate 2 summary', $wizard);
        $this->assertStringContainsString('Kopafasta records the totals and applies the existing Screening policy', $wizard);
        $this->assertStringNotContainsString('items[activity_income][income_evidence][verdict]', $wizard);
        $this->assertStringNotContainsString('Affordability uses Declared income', $wizard);

        $app->refresh();
        $item = $app->screening_payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'];
        $this->assertNull($item['verdict'] ?? null);
        $this->assertSame(11_265_630, (int) $item['statement_deposits_total']);
        $this->assertSame('screening', $app->current_stage);
        $this->assertSame('statement', $evaluation['income_basis']);
    }

    public function test_gate2_statement_save_without_verdict_applies_system_policy_and_opens_observation(): void
    {
        [$admin, $app] = $this->file();
        $app->customer->update(['monthly_income' => 2_000_000]);
        DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => DocumentType::query()->where('code', 'bank_statement')->value('id'),
            'file_path' => 'customer/'.$app->customer_id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'person' => 'borrower',
                'gate' => 'income',
                'open_item' => 'activity_income.income_evidence',
                'items' => [
                    'activity_income' => [
                        'income_evidence' => [
                            'statement_deposits_total' => '12,000,000',
                            'statement_months' => 6,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $item = $app->fresh()->screening_payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'];
        $this->assertSame('pass', $item['verdict'] ?? null);
        $this->assertSame('system', $item['source'] ?? null);
        $this->assertSame(2_000_000.0, (float) $item['statement_monthly']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Does activity support the stated income', $html);
        $this->assertStringContainsString('Yes — activity supports the stated income', $html);
        $this->assertStringContainsString('No — activity does not support the stated income', $html);
        $this->assertSame('screening', $app->fresh()->current_stage);
    }

    public function test_gate2_keeps_borrower_and_guarantor_on_income_gate_in_parallel(): void
    {
        [$admin, $app] = $this->fileWithGuarantor(3_000_000, 4_000_000);
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $app->forceFill(['screening_payload' => $payload])->save();
        DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => DocumentType::query()->where('code', 'bank_statement')->value('id'),
            'file_path' => 'customer/'.$app->customer_id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);

        $guided = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertSame('income', $guided['step']['gate'] ?? null);
        $this->assertGreaterThanOrEqual(2, count($guided['subjects'] ?? []));
        $this->assertSame('WAITING', $guided['gate2_summary']['overall'] ?? null);

        $guarantor = collect($guided['subjects'])->first(fn ($s) => ($s['person'] ?? '') === 'guarantor');
        $this->assertNotEmpty($guarantor['href'] ?? null);

        $html = $this->actingAs($admin, 'admin')
            ->get($guarantor['href'])
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Gate 2 summary', $html);
        $this->assertStringContainsString('Grace', $html);
        $this->assertStringContainsString('data-participant-switcher', $html);
        $this->assertStringContainsString('Gate 2 · WAITING', $html);
        $step = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin, [
            'focus_person' => 'guarantor',
            'focus_g' => $guarantor['g'] ?? null,
        ]);
        $this->assertSame('income', $step['step']['gate'] ?? null);
        $this->assertSame('guarantor', $step['step']['participant']['person'] ?? null);
        $this->assertSame('activity_income.income_evidence', $step['step']['item_key'] ?? null);
        $this->assertStringContainsString('Statement totals', (string) ($step['step']['title'] ?? ''));
        $this->assertStringNotContainsString('Assess guarantor capacity', $html);
        $this->assertStringNotContainsString('National ID required', $html);
    }

    public function test_after_completed_statement_stays_on_gate_2_not_gate_6(): void
    {
        [$admin, $app] = $this->fileWithGuarantor(3_000_000, 4_000_000);
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $app->forceFill(['screening_payload' => $payload])->save();
        DocumentType::firstOrCreate(
            ['code' => 'bank_statement'],
            ['name' => 'Bank statement', 'is_active' => true],
        );
        CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => DocumentType::query()->where('code', 'bank_statement')->value('id'),
            'file_path' => 'customer/'.$app->customer_id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);

        $guided = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $guarantor = collect($guided['subjects'] ?? [])->first(fn ($s) => ($s['person'] ?? '') === 'guarantor');
        $this->assertNotEmpty($guarantor);
        $g = isset($guarantor['g']) ? (int) $guarantor['g'] : null;
        $this->assertNotNull($g);

        // Mark the guarantor statement check complete — the staging Continue URL still
        // points after this item even though it no longer needs action.
        $subjectKey = 'guarantor:'.$g;
        $payload = $app->fresh()->screening_payload ?? [];
        $bySubject = $payload['screening_checklist']['by_subject'] ?? [];
        $bySubject[$subjectKey]['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'decided_at' => now()->toIso8601String(),
        ];
        $payload['screening_checklist']['by_subject'] = $bySubject;
        $app->forceFill(['screening_payload' => $payload])->save();

        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin, array_filter([
            'after_item' => 'activity_income.income_evidence',
            'after_person' => 'guarantor',
            'after_g' => $g,
        ]));

        $step = $next['step'] ?? [];
        $this->assertNotSame('gate_complete', $step['type'] ?? null);
        $this->assertNotSame(6, (int) ($step['gate_index'] ?? 0));
        $this->assertSame('income', $step['gate'] ?? null);
        $this->assertStringNotContainsString(
            'requirements remain before Screening can be completed',
            (string) ($step['prompt'] ?? '')
        );
        $this->assertStringNotContainsString('View Review Checklist', (string) ($step['primary'] ?? ''));

        $withoutG = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin, [
            'after_item' => 'activity_income.income_evidence',
            'after_person' => 'guarantor',
        ]);
        $this->assertSame('income', $withoutG['step']['gate'] ?? null);
        $this->assertNotSame(6, (int) ($withoutG['step']['gate_index'] ?? 0));
    }

    public function test_guarantor_capacity_confirmed_follows_system_capacity_not_a_second_human_ask(): void
    {
        [$admin, $app, $link] = $this->fileWithGuarantor(3_000_000, 8_000_000);
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $g = (int) $link->id;
        $subjectKey = 'guarantor:'.$g;
        $payload['screening_checklist']['by_subject'][$subjectKey]['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'checked' => true,
            'statement_deposits_total' => 48_000_000,
            'statement_months' => 6,
            'statement_monthly' => 8_000_000,
        ];
        $payload['screening_checklist']['by_subject'][$subjectKey]['items']['activity_income.activity_plausible'] = [
            'verdict' => 'pass',
            'checked' => true,
            'by' => $admin->id,
            'at' => now()->toIso8601String(),
        ];
        $payload['screening_checklist']['by_subject'][$subjectKey]['items']['activity_income.bank_or_mobile_money'] = [
            'verdict' => 'pass',
            'checked' => true,
            'by' => $admin->id,
            'at' => now()->toIso8601String(),
        ];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'checked' => true,
            'statement_deposits_total' => 18_000_000,
            'statement_months' => 6,
            'statement_monthly' => 3_000_000,
        ];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.activity_plausible'] = [
            'verdict' => 'pass',
            'checked' => true,
            'by' => $admin->id,
        ];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.bank_or_mobile_money'] = [
            'verdict' => 'pass',
            'checked' => true,
            'by' => $admin->id,
        ];
        $app->forceFill(['screening_payload' => $payload])->save();

        DocumentType::firstOrCreate(
            ['code' => 'bank_statement'],
            ['name' => 'Bank statement', 'is_active' => true],
        );
        CustomerDocument::create([
            'customer_id' => $app->customer_id,
            'document_type_id' => DocumentType::query()->where('code', 'bank_statement')->value('id'),
            'file_path' => 'customer/'.$app->customer_id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);

        $review = app(\App\Services\LoanApplicationReviewService::class)->dossier($app->fresh());
        $desk = app(\App\Services\ScreeningChecklistService::class)
            ->deskViewModel($app->fresh(), $review, [], $admin, 'guarantor', $g, null);
        $wrap = collect($desk['groups'] ?? [])
            ->flatMap(fn ($group) => $group['items'] ?? [])
            ->firstWhere('key', 'guarantor_wrap.capacity_confirmed');
        $this->assertNotEmpty($wrap);
        $this->assertSame('pass', $wrap['verdict'] ?? null);
        $this->assertTrue(in_array(($wrap['source'] ?? ''), ['system', ''], true) || ! empty($wrap['system_checked']) || ! empty($wrap['catalog_system']));

        $guided = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertSame('PASSED', $guided['gate2_summary']['overall'] ?? null);
        $this->assertSame(0, (int) ($guided['gate2_summary']['gate_remaining'] ?? -1));
        $this->assertSame('gate_2_passed', $guided['step']['type'] ?? null);
        $this->assertSame('income', $guided['step']['gate'] ?? null);
        $this->assertSame(2, (int) ($guided['step']['gate_index'] ?? 0));
        $this->assertStringContainsString('Continue to Gate 3', (string) ($guided['step']['primary'] ?? ''));
        $this->assertNotSame('guarantor_wrap.capacity_confirmed', $guided['step']['item_key'] ?? null);
    }

    public function test_wizard_answer_writes_the_same_checklist_record(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'person' => 'borrower',
                'gate' => 'identity',
                'open_item' => 'contacts.call_next_of_kin',
                'items' => [
                    'contacts' => [
                        'call_next_of_kin' => [
                            'verdict' => 'pass',
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', [
                'loan_application' => $app,
                'focus_person' => 'borrower',
            ]));

        $stored = data_get($app->fresh()->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $this->assertSame('pass', $stored['contacts.call_next_of_kin']['verdict'] ?? null);
        $this->assertSame($admin->id, (int) ($stored['contacts.call_next_of_kin']['by'] ?? 0));
    }

    public function test_system_nida_pass_is_not_the_current_human_step(): void
    {
        [$admin, $app] = $this->file();
        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $stepKey = $next['step']['item_key'] ?? '';
        $this->assertNotSame('identity.nida_vs_dob', $stepKey);
    }

    public function test_outstanding_document_moves_file_to_waiting(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'type' => 'document',
                'presets' => ['Updated National ID'],
                'confirmed' => '1',
                'return_workspace' => 'guided',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $queued = $app->documentRequests()->latest('id')->first();
        $this->assertNotNull($queued);
        $this->assertTrue($queued->isQueued());
        $this->assertFalse($queued->needsBorrowerAction());

        $reviewing = app(ScreeningNextActionService::class)->forApplication($app->fresh(['documentRequests']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $reviewing['bucket']);
        $this->assertSame('continue', $reviewing['cta_kind']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'dispatch_queued' => '1',
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $this->assertFalse($queued->fresh()->isQueued());
        $this->assertTrue($queued->fresh()->needsBorrowerAction());

        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_WAITING, $next['bucket']);
        $this->assertSame('waiting', $next['cta_kind']);
        $this->assertSame('document', $next['waiting']['kind'] ?? null);

        $app->documentRequests()->update(['status' => 'uploaded']);
        $reviewing = app(ScreeningNextActionService::class)->forApplication($app->fresh(['documentRequests']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $reviewing['bucket']);
        $this->assertSame('continue', $reviewing['cta_kind']);

        $app->documentRequests()->update(['status' => 'satisfied']);
        $after = app(ScreeningNextActionService::class)->forApplication($app->fresh(['documentRequests']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $after['bucket']);
        $this->assertSame('continue', $after['cta_kind']);
    }

    public function test_next_action_is_shared_by_list_and_wizard(): void
    {
        [$admin, $app] = $this->file();
        $a = app(ScreeningNextActionService::class)->forApplication($app, $admin);
        $b = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertSame($a['cta'], $b['cta']);
        $this->assertSame($a['bucket'], $b['bucket']);
        $this->assertSame($a['step']['item_key'] ?? $a['step']['type'], $b['step']['item_key'] ?? $b['step']['type']);
    }

    public function test_checklist_save_still_works_alongside_the_wizard(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'contacts' => [
                        'call_next_of_kin' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect();

        $desk = app(ScreeningChecklistService::class)->viewModel($app->fresh(), $admin);
        $item = collect($desk['groups'] ?? [])->flatMap(fn ($g) => $g['items'] ?? [])->firstWhere('key', 'contacts.call_next_of_kin');
        $this->assertSame('pass', $item['verdict'] ?? null);
    }

    public function test_screening_home_shows_do_now_bucket(): void
    {
        [$admin, $app] = $this->file();
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.teams.screening'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Do now', $html);
        $this->assertStringContainsString($app->application_number, $html);
        $this->assertStringContainsString('Continue Reviewing', $html);
    }

    public function test_committee_clarification_resumes_in_the_wizard(): void
    {
        [$admin, $app] = $this->file();
        $payload = $app->screening_payload ?? [];
        $payload['guided']['committee_clarification'] = [
            'question' => 'Explain the difference between June deposits and verified monthly income.',
            'from_stage' => 'pre_approval',
            'at' => now()->toIso8601String(),
            'by' => $admin->id,
            'response' => null,
            'returned_at' => null,
        ];
        $app->update(['screening_payload' => $payload]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->assertSee('Committee clarification')
            ->assertSee('June deposits');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'committee_clarification_response' => 'June deposits include a one-off contract payment.',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->assertSee('Return to Committee');
    }

    public function test_committee_guided_walk_opens_and_resumes_step(): void
    {
        [$admin, $app] = $this->file();
        $app->update(['current_stage' => 'pre_approval']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-committee', $app))
            ->assertOk()
            ->assertSee('Facility')
            ->assertSee('Committee')
            ->assertSee('Kopafasta Credit');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-committee', ['loan_application' => $app, 'step' => 2]))
            ->assertOk()
            ->assertSee('Repayment capacity');

        $this->assertSame(2, (int) data_get($app->fresh()->screening_payload, 'guided.committee_step'));
    }

    public function test_crb_page_from_guided_review_is_not_a_dead_end(): void
    {
        [$admin, $app] = $this->file();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
                'desk_phase' => 'capacity',
                'capacity_tab' => 'crb',
                'from' => 'guided',
            ]))
            ->assertOk()
            ->assertSee('Back to Review', false)
            ->assertSee('Manual CRB review required', false)
            ->assertSee('CRB recommendation', false)
            ->assertSee('Status: REFER', false)
            ->assertSee('Not provided', false)
            ->assertSee('What happens next', false)
            ->assertSee('Accept &amp; continue', false)
            ->assertDontSee('Score —', false)
            ->assertDontSee('Quick red flags', false);
    }

    public function test_accepting_reviewable_crb_finding_returns_to_guided_review_and_keeps_system_outcome(): void
    {
        [$admin, $app] = $this->file();
        $app->update([
            'screening_payload' => [
                'screening_checklist' => [
                    'by_subject' => [
                        'borrower' => [
                            'items' => [
                                'identity.name_vs_crb' => [
                                    'key' => 'identity.name_vs_crb',
                                    'verdict' => 'fail',
                                    'fail_reason_code' => 'crb_name_unusable',
                                    'source' => 'system',
                                    'catalog_system' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.discrepancy-waiver', $app), [
                'code' => 'crb_name_unusable',
                'reason' => 'CRB returned no usable name, but identity was confirmed from the National ID photo.',
                'from' => 'guided',
                'review_person' => 'borrower',
                'open_item' => 'identity.name_vs_crb',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $app->refresh();
        $item = data_get($app->screening_payload, 'screening_checklist.by_subject.borrower.items')['identity.name_vs_crb'] ?? null;
        $this->assertSame('fail', $item['verdict'] ?? null);
        $this->assertSame('crb_name_unusable', $item['fail_reason_code'] ?? null);
        $this->assertSame('accepted', $item['analyst_review'] ?? null);
        $exception = collect(data_get($app->screening_payload, 'screening_exceptions', []))->first();
        $this->assertIsArray($exception);
        $this->assertSame('REFER', $exception['system_outcome'] ?? null);
        $this->assertSame('accepted', $exception['analyst_outcome'] ?? null);

        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertNotSame('identity.name_vs_crb', $next['step']['item_key'] ?? null);
    }

    public function test_hard_policy_failure_cannot_be_accepted_as_a_discrepancy(): void
    {
        [$admin, $app] = $this->file();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', $app))
            ->post(route('admin.loan-applications.discrepancy-waiver', $app), [
                'code' => 'name_mismatch',
                'reason' => 'I want to override a hard identity mismatch against policy.',
            ])
            ->assertSessionHasErrors('code');

        $this->assertEmpty(data_get($app->fresh()->screening_payload, 'screening_exceptions'));
    }

    public function test_accepting_from_committee_returns_to_committee_review(): void
    {
        [$admin, $app] = $this->file();
        $app->update(['current_stage' => 'pre_approval']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.discrepancy-waiver', $app), [
                'code' => 'crb_refer',
                'reason' => 'Bureau referred the file; identity and income evidence are consistent enough to continue.',
                'from' => 'committee',
                'review_person' => 'borrower',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-committee', $app));
    }

    public function test_committee_sees_and_acknowledges_screening_exceptions(): void
    {
        [$admin, $app] = $this->file();
        app(ScreeningExceptionService::class)->accept(
            $app,
            $admin,
            'crb_name_unusable',
            'CRB returned no usable name, but the National ID photo confirmed identity.',
            'CRB record without a usable name',
        );
        $app->refresh()->update(['current_stage' => 'pre_approval']);
        $exceptionId = data_get($app->fresh()->screening_payload, 'screening_exceptions.0.id');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-committee', $app))
            ->assertOk()
            ->assertSee('Exceptions from Screening', false)
            ->assertSee('System recommendation', false)
            ->assertSee('REFER', false)
            ->assertSee('ACCEPTED', false)
            ->assertSee('1 exception requires Committee attention', false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-exceptions.acknowledge', [$app, $exceptionId]))
            ->assertRedirect(route('admin.loan-applications.guided-committee', $app));

        $this->assertSame(
            'acknowledged',
            data_get($app->fresh()->screening_payload, 'screening_exceptions.0.committee.status')
        );
    }

    public function test_post_approval_walk_opens(): void
    {
        [$admin, $app] = $this->file();
        $app->update(['current_stage' => 'approval', 'status' => 'approved', 'offer_status' => 'accepted']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-post-approval', $app))
            ->assertOk()
            ->assertSee('Post-approval')
            ->assertSee('What happens next')
            ->assertSee('Kopafasta Credit')
            ->assertDontSee('Start Management Review', false);
    }

    public function test_stale_resume_is_ignored_in_favour_of_the_checklist(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'person' => 'borrower',
                'gate' => 'identity',
                'open_item' => 'contacts.call_next_of_kin',
                'items' => [
                    'contacts' => [
                        'call_next_of_kin' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect();

        $payload = $app->fresh()->screening_payload ?? [];
        $payload['guided']['resume'] = [
            'gate' => 'final',
            'person' => 'borrower',
            'item' => 'does.not.exist',
        ];
        $app->update(['screening_payload' => $payload]);

        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertNotSame('does.not.exist', $next['step']['item_key'] ?? null);
        $this->assertNotSame('final', $next['step']['gate'] ?? null);
    }

    public function test_wizard_html_has_sticky_mobile_actions_and_save_next(): void
    {
        [$admin, $app] = $this->file();
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-guided-review', $html);
        $this->assertStringContainsString('sticky bottom-0', $html);
        $this->assertStringNotContainsString('fixed inset-x-0 bottom-0', $html);
        $this->assertStringContainsString('Kopafasta Credit', $html);
        $this->assertTrue(
            str_contains($html, 'Save & Next')
            || (str_contains($html, 'Continue to') && str_contains($html, 'Verified Income'))
            || str_contains($html, 'Confirm in the card')
        );
        $this->assertStringContainsString('Review Checklist', $html);
        $this->assertStringContainsString('whitespace-normal', $html);
        $this->assertTrue(
            str_contains($html, 'data-loading-label="Saving…"')
            || (str_contains($html, 'Continue to') && str_contains($html, 'Verified Income'))
            || str_contains($html, 'Confirm in the card')
        );
        if (getenv('DUMP_GUIDED_HTML')) {
            @mkdir('/tmp/kopafasta-qa', 0777, true);
            file_put_contents('/tmp/kopafasta-qa/guided-wizard.html', $html);
        }
    }

    public function test_logout_and_login_continues_from_the_same_derived_step(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk();
        $before = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);

        $this->post(route('admin.logout'))->assertRedirect();

        $this->actingAs($admin, 'admin');
        $this->get(route('admin.teams.screening'))
            ->assertOk()
            ->assertSee($before['cta']);

        $this->get(route('admin.loan-applications.guided-screening', $app))->assertOk();
        $after = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertSame($before['step']['item_key'] ?? $before['step']['type'], $after['step']['item_key'] ?? $after['step']['type']);
        $this->assertSame($before['gate_index'], $after['gate_index']);
        $this->assertSame($before['cta'], $after['cta']);
    }

    public function test_application_overview_is_the_screening_landing(): void
    {
        [$admin, $app] = $this->file();
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Facility summary', $html);
        $this->assertStringContainsString('Continue Reviewing', $html);
        $this->assertStringContainsString('Review Checklist', $html);
        $this->assertStringNotContainsString('Guided Screening', $html);
        $this->assertStringNotContainsString('Save & Next', $html);
    }

    public function test_save_and_next_advances_to_a_different_item(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin');

        $cursor = [];
        $step = [];
        for ($i = 0; $i < 20; $i++) {
            $before = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin, $cursor);
            $step = $before['step'] ?? [];
            $type = $step['type'] ?? '';
            if ($type === 'gate_1') {
                $this->post(route('admin.loan-applications.guided-screening.save', $app), [
                    'ack_gate' => 'declared',
                ])->assertRedirect();
                $cursor = [];

                continue;
            }
            if ($type === 'attention') {
                $cursor = array_filter([
                    'after_item' => $step['item_key'] ?? null,
                    'after_person' => $step['participant']['person'] ?? null,
                    'after_m' => $step['participant']['m'] ?? null,
                    'after_g' => $step['participant']['g'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');

                continue;
            }
            if ($type === 'human') {
                break;
            }
            $this->assertNotSame('decision', $type);
            $this->assertStringNotContainsString('Screening is complete', (string) ($before['what_happens_next'] ?? ''));

            return;
        }

        $this->assertSame('human', $step['type'] ?? null);
        $key = (string) ($step['item_key'] ?? '');
        $this->assertNotSame('', $key);
        [$group, $short] = array_pad(explode('.', $key, 2), 2, '');
        $response = $this->post(route('admin.loan-applications.guided-screening.save', $app), [
            'person' => $step['participant']['person'] ?? 'borrower',
            'gate' => $step['gate'] ?? 'identity',
            'open_item' => $key,
            'm' => $step['participant']['m'] ?? null,
            'g' => $step['participant']['g'] ?? null,
            'items' => [
                $group => [
                    $short => ['verdict' => 'pass'],
                ],
            ],
        ]);
        $response->assertRedirect(route('admin.loan-applications.guided-screening', $app));
        $response->assertSessionMissing('status');

        $subject = match ($step['participant']['person'] ?? 'borrower') {
            'member' => 'member:'.($step['participant']['m'] ?? ''),
            'guarantor' => 'guarantor:'.($step['participant']['g'] ?? ''),
            default => 'borrower',
        };
        $items = $app->fresh()->screening_payload['screening_checklist']['by_subject'][$subject]['items'] ?? [];
        $this->assertSame('pass', $items[$key]['verdict'] ?? null);

        $after = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertNotSame($key, $after['step']['item_key'] ?? null);
        if (in_array($after['step']['type'] ?? '', ['human', 'attention', 'request'], true)) {
            $this->assertStringNotContainsString('Screening is complete', (string) ($after['what_happens_next'] ?? ''));
        }

        $html = $this->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->assertSessionMissing('status')
            ->getContent();
        $this->assertStringNotContainsString("message: @js('Saved.')", $html);
        $this->assertDoesNotMatchRegularExpression('/showAdminFeedback\(\{[^}]*Saved\./', $html);
    }

    public function test_catalog_system_items_are_not_pass_concern_questions(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'ack_gate' => 'declared',
            ]);
        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $key = (string) ($next['step']['item_key'] ?? '');
        if ($key === '') {
            $this->assertNotSame('human', $next['step']['type'] ?? 'human');

            return;
        }
        [$group, $short] = array_pad(explode('.', $key, 2), 2, '');
        $system = (bool) data_get(config('screening_checklist'), $group.'.items.'.$short.'.system');
        if ($system) {
            $this->assertSame('attention', $next['step']['type'] ?? null);
            $this->assertSame([], $next['step']['outcomes'] ?? []);
        }
    }

    public function test_manual_checklist_answer_is_skipped_by_the_wizard(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.screening-checklist', $app), [
                'person' => 'borrower',
                'items' => [
                    'contacts' => [
                        'call_next_of_kin' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect();

        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);
        $this->assertNotSame('contacts.call_next_of_kin', $next['step']['item_key'] ?? null);
    }

    public function test_ordinary_save_does_not_open_a_success_modal(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'person' => 'borrower',
                'gate' => 'identity',
                'open_item' => 'contacts.call_next_of_kin',
                'items' => [
                    'contacts' => [
                        'call_next_of_kin' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionMissing('status');
    }

    public function test_completion_copy_is_not_shown_while_a_question_is_open(): void
    {
        [$admin, $app] = $this->file();
        $next = app(ScreeningNextActionService::class)->forApplication($app, $admin);
        $type = $next['step']['type'] ?? '';
        if (in_array($type, ['human', 'request', 'attention', 'waiting', 'gate_1'], true)) {
            $this->assertStringNotContainsString('Screening is complete', (string) ($next['what_happens_next'] ?? ''));
            $this->assertNotSame('decision', $type);
        }
    }

    public function test_hard_refresh_keeps_the_same_derived_step(): void
    {
        [$admin, $app] = $this->file();
        $first = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk();
        $before = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);

        $second = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk();
        $after = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin);

        $this->assertSame($before['step']['item_key'] ?? $before['step']['type'], $after['step']['item_key'] ?? $after['step']['type']);
        $this->assertSame($before['gate_index'], $after['gate_index']);
        $this->assertSame($first->status(), $second->status());
    }

    public function test_guided_back_keeps_the_saved_answer(): void
    {
        [$admin, $app] = $this->file();
        $this->actingAs($admin, 'admin');
        $this->post(route('admin.loan-applications.guided-screening.save', $app), [
            'person' => 'borrower',
            'gate' => 'income',
            'open_item' => 'activity_income.activity_plausible',
            'items' => [
                'activity_income' => [
                    'activity_plausible' => ['verdict' => 'pass'],
                ],
            ],
        ])->assertRedirect();

        $this->get(route('admin.loan-applications.guided-screening', [
            'loan_application' => $app,
            'at_item' => 'activity_income.activity_plausible',
            'at_person' => 'borrower',
        ]))
            ->assertOk()
            ->assertSee('Already recorded')
            ->assertSee('Back to Screening')
            ->assertDontSee('Saved.');

        $items = $app->fresh()->screening_payload['screening_checklist']['by_subject']['borrower']['items'] ?? [];
        $this->assertSame('pass', $items['activity_income.activity_plausible']['verdict'] ?? null);

        $this->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'overview']))
            ->assertOk()
            ->assertSee('Facility summary')
            ->assertDontSee('Save & Next');
    }

    public function test_guarantor_fail_replacement_waiting_then_continue_preserves_borrower_work(): void
    {
        Setting::setMany([
            'underwriting.guarantor_gate_1_required' => true,
            'underwriting.guarantor_hard_fail_action' => 'replace',
            'underwriting.guarantor_replacement_hours' => 48,
        ]);
        [$admin, $app, $link] = $this->fileWithGuarantor(5_000_000, 1_000);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-screening.save', $app), [
                'person' => 'borrower',
                'gate' => 'identity',
                'open_item' => 'contacts.call_next_of_kin',
                'items' => [
                    'contacts' => [
                        'call_next_of_kin' => ['verdict' => 'pass'],
                    ],
                ],
            ])
            ->assertRedirect();

        $policy = app(CreditEligibilityPolicyService::class)->evaluate($app->fresh([
            'customer', 'product', 'customerGuarantors.invitation',
        ]));
        $this->assertSame(CreditEligibilityPolicyService::ACTION_REPLACE_GUARANTOR, $policy['application_action']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->assertSee('Replace guarantor');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guarantor-change', [$app, $link]), [
                'notes' => "The guarantor's current financial commitments do not provide enough capacity for this guarantee.",
                'return_workspace' => 'guided',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $waiting = app(ScreeningNextActionService::class)->forApplication($app->fresh(['documentRequests', 'customerGuarantors.invitation']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_WAITING, $waiting['bucket']);
        $this->assertSame('guarantor', $waiting['waiting']['kind'] ?? null);

        $replacement = $this->attachReplacementGuarantor($app, $admin, 5_000_000);
        app(GuarantorSupplementService::class)->markSatisfied($app->fresh());

        $after = app(ScreeningNextActionService::class)->forApplication($app->fresh(['documentRequests', 'customerGuarantors.invitation']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $after['bucket']);
        $this->assertSame('continue', $after['cta_kind']);

        $stored = data_get($app->fresh()->screening_payload, 'screening_checklist.by_subject.borrower.items', []);
        $this->assertSame('pass', $stored['contacts.call_next_of_kin']['verdict'] ?? null);
        $this->assertNotNull($replacement->id);
    }

    public function test_group_one_member_fail_can_continue_with_three(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);
        [$admin, $app, $members] = $this->groupFile(1);
        $weak = $members['weak'][0];

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->assertSee('Continue with');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.continue-with-eligible-members', $app), [
                'reason' => 'Continue with 3 eligible members. Failed members remain on the file historically.',
                'return_workspace' => 'guided',
            ])
            ->assertRedirect();

        $this->assertSame('ineligible', $weak->fresh()->member_status);
        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(['loanGroup.members.customer']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $next['bucket']);
        $this->assertNotSame('waiting', $next['cta_kind']);
    }

    public function test_group_below_minimum_requires_replacement_then_resumes(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);
        [$admin, $app, $members] = $this->groupFile(2);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Request replacement', $html);
        $this->assertStringNotContainsString('Continue with 2 eligible', $html);

        $ids = $app->fresh()->loanGroup->members
            ->filter(fn ($m) => in_array((int) $m->customer_id, collect($members['weak'])->pluck('customer_id')->all(), true))
            ->pluck('id')
            ->all();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guided-member-replacements', $app), [
                'member_ids' => $ids,
                'reason' => 'Members do not meet the requirements for this loan.',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $waiting = app(ScreeningNextActionService::class)->forApplication($app->fresh(['loanGroup.members.customer']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_WAITING, $waiting['bucket']);

        $failedMembers = $app->fresh()->loanGroup->members->whereIn('id', $ids)->values();
        foreach ($failedMembers as $index => $old) {
            $replacement = Customer::create([
                'customer_number' => 'CU-GD-R'.random_int(100, 999).$index,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Replacement',
                'last_name' => 'Member'.$index,
                'phone' => '25579'.random_int(1000000, 9999999),
                'monthly_income' => 5_000_000,
                'branch_id' => $admin->branch_id,
            ]);
            app(GroupMemberReplacementService::class)->replaceWithInternalMember(
                $app->fresh(['loanGroup.members.customer']),
                $app->customer,
                $old->fresh(),
                $replacement,
            );
        }

        $after = app(ScreeningNextActionService::class)->forApplication($app->fresh(['loanGroup.members.customer']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $after['bucket']);
        $this->assertSame('continue', $after['cta_kind']);
        $this->assertSame('replaced', $failedMembers[0]->fresh()->member_status);
    }

    /** @return array{0: User, 1: LoanApplication} */
    private function file(): array
    {
        $branch = Branch::create([
            'code' => 'GD'.random_int(1000, 9999),
            'name' => 'Guided Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'GD-'.random_int(100, 999),
            'name' => 'Guided Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GD-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Guided',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
            'national_id' => '19900101123456789012',
            'date_of_birth' => '1990-01-01',
            'monthly_income' => 2_000_000,
            'nok_name' => 'Jane Kin',
            'nok_relationship' => 'Sister',
            'nok_phone' => '255712000999',
            'lga_officer_name' => 'Macmillan George',
            'lga_officer_position' => 'Afisa Wa Mtaa',
            'lga_officer_phone' => '2551234567',
        ]);
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-GD-'.random_int(1000, 9999),
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
            'assigned_analyst_id' => $admin->id,
        ]);

        return [$admin, $app];
    }

    /** @return array{0: User, 1: LoanApplication, 2: CustomerGuarantor} */
    private function fileWithGuarantor(float $borrowerIncome, float $guarantorIncome): array
    {
        [$admin, $app] = $this->file();
        $app->product->update([
            'requires_guarantor' => true,
            'guarantor_gate_1_required' => true,
        ]);
        $app->customer->update(['monthly_income' => $borrowerIncome]);

        $guarantorCustomer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GDG-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => '25576'.random_int(1000000, 9999999),
            'branch_id' => $admin->branch_id,
            'monthly_income' => $guarantorIncome,
        ]);
        $contact = Guarantor::create([
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => $guarantorCustomer->phone,
            'relationship' => 'sibling',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $app->customer_id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $app->id,
            'status' => 'approved',
        ]);
        GuarantorInvitation::create([
            'customer_id' => $app->customer_id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $app->loan_product_id,
            'guarantor_customer_id' => $guarantorCustomer->id,
            'type' => 'member',
            'status' => 'accepted',
            'contact' => $guarantorCustomer->phone,
            'invitee_name' => 'Grace Guarantor',
            'token' => 'tok-gd-'.random_int(10000, 99999),
        ]);

        return [$admin, $app->fresh(['customer', 'product', 'customerGuarantors.invitation']), $link->fresh('invitation')];
    }

    private function attachReplacementGuarantor(LoanApplication $app, User $admin, float $income): CustomerGuarantor
    {
        $guarantorCustomer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GDN-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'New',
            'last_name' => 'Guarantor',
            'phone' => '25577'.random_int(1000000, 9999999),
            'branch_id' => $admin->branch_id,
            'monthly_income' => $income,
        ]);
        $contact = Guarantor::create([
            'first_name' => 'New',
            'last_name' => 'Guarantor',
            'phone' => $guarantorCustomer->phone,
            'relationship' => 'friend',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $app->customer_id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $app->id,
            'status' => 'approved',
        ]);
        GuarantorInvitation::create([
            'customer_id' => $app->customer_id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $app->loan_product_id,
            'guarantor_customer_id' => $guarantorCustomer->id,
            'type' => 'member',
            'status' => 'accepted',
            'contact' => $guarantorCustomer->phone,
            'invitee_name' => 'New Guarantor',
            'token' => 'tok-gdn-'.random_int(10000, 99999),
        ]);

        return $link;
    }

    /**
     * @return array{0: User, 1: LoanApplication, 2: array{weak: Collection}}
     */
    private function groupFile(int $weakCount): array
    {
        [$admin] = $this->file();
        $product = LoanProduct::create([
            'code' => 'GL-GD-'.random_int(100, 999),
            'name' => 'Guided Group',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 200_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $people = [];
        for ($i = 0; $i < 4; $i++) {
            $weak = $i > 0 && $i <= $weakCount;
            $people[] = Customer::create([
                'customer_number' => 'CU-GL-'.random_int(1000, 9999).$i,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => $weak ? 'Weak' : 'Ok',
                'last_name' => 'Member'.$i,
                'phone' => '25571'.random_int(1000000, 9999999),
                'monthly_income' => $weak ? 1_000 : 5_000_000,
                'branch_id' => $admin->branch_id,
            ]);
        }
        $leader = $people[0];
        $app = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $admin->branch_id,
            'application_number' => 'APP-GL-GD-'.random_int(1000, 9999),
            'requested_amount' => 1_200_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
            'assigned_analyst_id' => $admin->id,
        ]);
        $rows = [];
        foreach ($people as $index => $person) {
            $rows[] = [
                'customer_id' => $person->id,
                'requested_amount' => 300_000,
                'role' => $index === 0 ? 'leader' : 'member',
            ];
        }
        app(GroupLendingService::class)->createForApplication($app, $rows, 'Guided Group', 'Business');
        $app = $app->fresh(['customer', 'product', 'loanGroup.members.customer']);
        $weakIds = collect($people)->slice(1, $weakCount)->pluck('id')->all();
        $weak = $app->loanGroup->members->filter(fn ($m) => in_array((int) $m->customer_id, $weakIds, true))->values();

        return [$admin, $app, ['weak' => $weak]];
    }

    public function test_residence_proof_is_first_in_gate_5_residence_sequence(): void
    {
        $keys = array_keys(config('screening_checklist.residence.items'));
        $this->assertSame('utility_or_proof', $keys[0] ?? null);
        $this->assertSame('address_consistency', $keys[1] ?? null);
        $this->assertSame('local_government', $keys[2] ?? null);
    }

    public function test_checklist_opened_from_guided_review_returns_to_the_wizard(): void
    {
        [$admin, $app] = $this->file();
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
                'from' => 'guided',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Back to Guided Review', $html);
        $this->assertStringContainsString('Continue Reviewing', $html);
    }

    public function test_inline_document_request_uses_settings_due_days_and_stores_checklist_context(): void
    {
        [$admin, $app] = $this->file();
        Setting::set('underwriting.document_request_default_due_days', 7);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'type' => 'document',
                'presets' => ['Updated National ID'],
                'confirmed' => '1',
                'return_workspace' => 'guided',
                'open_item' => 'identity.id_document_quality',
                'gate' => 'identity',
                'request_reason' => 'Current copy is unclear.',
                'due_at' => now()->addDays(30)->toDateString(),
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $request = $app->documentRequests()->latest('id')->first();
        $this->assertNotNull($request);
        $this->assertTrue($request->isQueued());
        $this->assertSame('identity.id_document_quality', $request->checklist_item);
        $this->assertSame('identity', $request->gate);
        $this->assertSame('Current copy is unclear.', $request->request_reason);
        $this->assertTrue($request->due_at->lte(now()->addDays(7)->endOfDay()));
        $this->assertTrue($request->due_at->gte(now()->addDays(6)->startOfDay()));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Added to this review', $html);
        $this->assertStringContainsString('Send together', $html);
        $this->assertStringContainsString('one 7-day deadline', $html);
        $this->assertStringNotContainsString('Request sent', $html);
        $this->assertStringNotContainsString('Screening paused', $html);
        $this->assertStringNotContainsString('name="due_at"', $html);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'dispatch_queued' => '1',
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $this->assertFalse($request->fresh()->isQueued());
        $this->assertTrue($request->fresh()->due_at->lte(now()->addDays(7)->endOfDay()));
        $this->assertTrue($request->fresh()->due_at->gte(now()->addDays(6)->startOfDay()));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Request sent', $html);
        $this->assertStringContainsString('Waiting for document', $html);
        $this->assertStringNotContainsString('Send together', $html);
        $this->assertStringNotContainsString('name="due_at"', $html);
    }

    public function test_guided_batch_shares_one_deadline_when_sent_together(): void
    {
        [$admin, $app] = $this->file();
        Setting::set('underwriting.document_request_default_due_days', 7);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'type' => 'document',
                'presets' => ['Updated National ID'],
                'confirmed' => '1',
                'return_workspace' => 'guided',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'type' => 'document',
                'presets' => ['Updated Bank Statement'],
                'confirmed' => '1',
                'return_workspace' => 'guided',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $queued = $app->documentRequests()->orderBy('id')->get();
        $this->assertCount(2, $queued);
        $this->assertTrue($queued->every(fn ($row) => $row->isQueued()));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('2 requests ready — one 7-day deadline', $html);
        $this->assertStringContainsString('Updated National ID', $html);
        $this->assertStringContainsString('Updated Bank Statement', $html);

        $this->travel(2)->days();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $app), [
                'dispatch_queued' => '1',
                'confirmed' => '1',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app));

        $sent = $app->documentRequests()->orderBy('id')->get();
        $this->assertTrue($sent->every(fn ($row) => ! $row->isQueued()));
        $this->assertTrue($sent->every(fn ($row) => $row->needsBorrowerAction()));
        $this->assertEquals(
            $sent[0]->due_at->toDateString(),
            $sent[1]->due_at->toDateString(),
        );
        $this->assertTrue($sent[0]->due_at->gte(now()->addDays(6)->startOfDay()));
        $this->assertTrue($sent[0]->due_at->lte(now()->addDays(7)->endOfDay()));
    }

    public function test_evidence_return_url_pins_the_current_checklist_item(): void
    {
        [$admin, $app] = $this->file();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
                'from' => 'guided',
                'open_item' => 'identity.id_document_quality',
                'review_person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('at_item=identity.id_document_quality', $html);
        $this->assertStringContainsString('Back to Guided Review', $html);
        $this->assertStringNotContainsString('Open member National ID', $html);
    }

    public function test_guided_collateral_request_stays_on_same_surface_and_opens_secure_journey(): void
    {
        [$admin, $app] = $this->file();
        $app->product->update(['requires_collateral' => true]);
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'pass',
            'source' => 'system',
            'statement_deposits_total' => 12_000_000,
            'statement_months' => 6,
            'statement_monthly' => 2_000_000,
        ];
        foreach (['identity.name_vs_crb', 'identity.marital_vs_crb', 'credit_file.crb_reviewed'] as $key) {
            $payload['screening_checklist']['by_subject']['borrower']['items'][$key] = [
                'verdict' => 'pass',
                'source' => 'system',
            ];
        }
        $app->update(['screening_payload' => $payload]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Collateral is required', $html);
        $this->assertStringContainsString('Review &amp; request collateral', $html);
        $this->assertStringContainsString('A valuer is not assigned yet', $html);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.guided-screening', $app))
            ->post(route('admin.loan-applications.request-collateral-secure', $app), [
                'return_workspace' => 'guided',
                'notes' => 'Need pledged land',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app))
            ->assertSessionHasErrors('confirmed');
        $this->assertNull(data_get($app->fresh()->screening_payload, 'collateral_secure.status'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.request-collateral-secure', $app), [
                'return_workspace' => 'guided',
                'confirmed' => '1',
                'notes' => 'Need pledged land',
            ])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $app))
            ->assertSessionHas('status');

        $this->assertSame(
            \App\Services\CollateralSecureService::STATUS_AWAITING_BORROWER,
            data_get($app->fresh()->screening_payload, 'collateral_secure.status')
        );

        $next = app(ScreeningNextActionService::class)->forApplication($app->fresh(['customer', 'product', 'documentRequests']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_WAITING, $next['bucket']);
        $this->assertSame('collateral', $next['waiting']['kind'] ?? null);
        $this->assertSame('Waiting for collateral', $next['waiting']['label'] ?? null);
        $this->assertSame('Waiting for collateral', $next['cta'] ?? null);
    }

    public function test_gate3_shows_freshness_subject_match_and_automatic_identity_findings(): void
    {
        [$admin, $app] = $this->file();
        Setting::set('kyc.crb_freshness_days', 90);
        \App\Models\CreditHistory::create([
            'customer_id' => $app->customer_id,
            'source' => 'crb_stub',
            'score' => 612,
            'risk_grade' => 'B',
            'payload' => array_merge(
                app(\App\Services\Crb\StubCrbCreditFixture::class)->build($app->customer, 'wrong_subject'),
                ['report_type' => 'credit'],
            ),
            'checked_at' => now()->subDays(10),
        ]);
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $payload['guided']['seen_gates']['income'] = true;
        foreach ([
            'activity_income.income_evidence',
            'activity_income.activity_plausible',
            'activity_income.bank_or_mobile_money',
        ] as $key) {
            $payload['screening_checklist']['by_subject']['borrower']['items'][$key] = [
                'verdict' => 'pass',
                'source' => 'system',
                'checked' => true,
                'statement_deposits_total' => 12_000_000,
                'statement_months' => 6,
                'statement_monthly' => 2_000_000,
            ];
        }
        $app->update(['screening_payload' => $payload]);

        $guided = app(ScreeningNextActionService::class)->forApplication($app->fresh(), $admin, [
            'at_item' => 'identity.name_vs_crb',
            'at_person' => 'borrower',
        ]);
        $this->assertSame('crb', $guided['step']['gate'] ?? null);
        $panel = $guided['step']['gate3_panel'] ?? null;
        $this->assertIsArray($panel);
        $this->assertSame('CURRENT', $panel['freshness']['status'] ?? null);
        $this->assertSame(10, (int) ($panel['freshness']['age_days'] ?? -1));
        $this->assertSame(90, (int) ($panel['freshness']['valid_for_days'] ?? -1));
        $this->assertFalse((bool) ($panel['subject']['matches'] ?? true));
        $this->assertSame('FAILED', $panel['chip'] ?? null);
        $this->assertTrue((bool) ($panel['block_secondary'] ?? false));
        $codes = collect($panel['flags'] ?? [])->pluck('code')->all();
        $this->assertContains('name_mismatch', $codes);
        $this->assertSame([], $guided['step']['outcomes'] ?? ['x']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', [
                'loan_application' => $app,
                'at_item' => 'identity.name_vs_crb',
                'at_person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('GATE 3 · CRB', $html);
        $this->assertStringContainsString('Identity comparison', $html);
        $this->assertStringContainsString('Amina Juma Mwinyi', $html);
        $this->assertStringContainsString('SUBJECT MISMATCH', $html);
        $this->assertStringContainsString('Hard policy failure', $html);
        $this->assertStringNotContainsString('name="items[identity][name_vs_crb][verdict]"', $html);
        $this->assertSame('FAILED', $guided['gate3_summary']['overall'] ?? null);
    }

    public function test_gate3_expired_crb_is_waiting_and_cannot_pass(): void
    {
        [$admin, $app] = $this->file();
        Setting::set('kyc.crb_freshness_days', 30);
        app(\App\Services\CrbCreditCheckService::class)->installStubReport(
            $app->customer,
            'clean',
            now()->subDays(45),
        );
        $this->assertSame(30, app(\App\Services\CrbFreshnessService::class)->freshnessDays());
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $payload['guided']['seen_gates']['income'] = true;
        $app->update(['screening_payload' => $payload]);

        $panel = app(\App\Services\Gate3CrbPanelService::class)->panel($app->fresh(), $app->customer->fresh(), [
            'person' => 'borrower',
        ], $admin);
        $this->assertSame('EXPIRED', $panel['freshness']['status'] ?? null);
        $this->assertSame('WAITING', $panel['chip'] ?? null);
        $this->assertStringContainsString('current crb report', strtolower((string) ($panel['next_action'] ?? '')));

        $auto = app(\App\Services\ScreeningChecklistAutoVerdictService::class)->suggest($app, 'borrower', [
            'customer' => $app->customer,
            'crb' => app(\App\Services\CrbCreditCheckService::class)->summaryForCustomer($app->customer, $app),
        ]);
        $this->assertSame('crb_expired', $auto['identity.name_vs_crb']['fail_reason_code'] ?? null);
    }

    public function test_gate3_keeps_borrower_and_guarantor_crb_isolated(): void
    {
        [$admin, $app, $link] = $this->fileWithGuarantor(3_000_000, 4_000_000);
        $guarantorCustomer = $link->invitation?->guarantorCustomer;
        $this->assertNotNull($guarantorCustomer);
        $crb = app(\App\Services\CrbCreditCheckService::class);
        // Distinct stub subjects — do not leave a fresher matching install above these rows.
        $borrowerPayload = app(\App\Services\Crb\StubCrbCreditFixture::class)->build($app->customer, 'clean');
        $borrowerPayload['personal']['full_name'] = 'Borrower Stub Subject';
        \App\Models\CreditHistory::create([
            'customer_id' => $app->customer_id,
            'source' => 'crb_stub',
            'score' => 612,
            'payload' => $borrowerPayload,
            'checked_at' => now()->subDays(2),
        ]);
        $guarantorPayload = app(\App\Services\Crb\StubCrbCreditFixture::class)->build($guarantorCustomer, 'clean');
        $guarantorPayload['personal']['full_name'] = 'Guarantor Stub Subject';
        \App\Models\CreditHistory::create([
            'customer_id' => $guarantorCustomer->id,
            'source' => 'crb_stub',
            'score' => 640,
            'payload' => $guarantorPayload,
            'checked_at' => now()->subDays(3),
        ]);
        $payload = $app->screening_payload ?? [];
        $payload['guided']['seen_gates']['declared'] = true;
        $payload['guided']['seen_gates']['income'] = true;
        $app->update(['screening_payload' => $payload]);

        $borrowerPanel = app(\App\Services\Gate3CrbPanelService::class)->panel($app->fresh(), $app->customer->fresh(), [
            'person' => 'borrower',
        ], $admin);
        $guarantorPanel = app(\App\Services\Gate3CrbPanelService::class)->panel($app->fresh(), $guarantorCustomer->fresh(), [
            'person' => 'guarantor',
            'g' => $link->id,
        ], $admin);

        $this->assertSame('Borrower Stub Subject', $borrowerPanel['subject']['crb_name'] ?? null);
        $this->assertSame('Guarantor Stub Subject', $guarantorPanel['subject']['crb_name'] ?? null);
        $this->assertNotSame($borrowerPanel['subject']['crb_name'], $guarantorPanel['subject']['crb_name']);
    }
}
