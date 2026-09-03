<?php

namespace Tests\Feature;

use App\Models\ArrearCase;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateCommissionWalletService;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use App\Services\PartnerAutoAssignPolicy;
use App\Services\PartnerDeletionService;
use App\Services\PartnerEfficiencyPolicy;
use App\Services\PartnerEfficiencyService;
use App\Services\PartnerPerformanceReviewService;
use App\Services\PartnerProfileService;
use App\Services\PartnerProfileTabs;
use App\Services\PartnerSettlementService;
use App\Services\PartnerTermsService;
use App\Services\RecoveryEscalationService;
use App\Services\RecoveryPolicyService;
use App\Services\ServicePartnerReassignmentService;
use App\Services\ValuationPartnerService;
use App\Support\PartnerPerformanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\CompletesPartnerJobs;
use Tests\TestCase;

class PartnerGovernanceClosureFeatureTest extends TestCase
{
    use CompletesPartnerJobs;
    use RefreshDatabase;

    public function test_settings_change_flows_into_runtime_and_new_terms_not_old_snapshots(): void
    {
        $terms = app(PartnerTermsService::class);
        $before = $terms->render('valuer');
        $this->assertStringContainsString('5 days', $before);

        Setting::set('partner_auto_assign.service.valuer.sla_days', 7);
        Setting::set('partner_auto_assign.service.valuer.remind_hours', '24,6');
        Setting::set('recovery.remind_days', '5,2');
        Setting::set('partners.efficiency', array_merge(
            app(PartnerEfficiencyPolicy::class)->settings(),
            ['target_on_time_percent' => 88]
        ));

        $this->assertSame(7, app(PartnerAutoAssignPolicy::class)->slaDaysForService('valuer'));
        $this->assertSame(168, app(PartnerAutoAssignPolicy::class)->slaHoursForService('valuer'));
        $this->assertSame([24, 6], app(PartnerAutoAssignPolicy::class)->remindHoursForService('valuer'));
        $this->assertSame([5, 2], app(RecoveryPolicyService::class)->remindDaysForType('debt_collector'));

        $after = $terms->render('valuer');
        $this->assertStringContainsString('7 days', $after);
        $this->assertStringContainsString('168 hours', $after);
        $this->assertStringContainsString('24, 6', $after);
        $this->assertStringContainsString('88%', $after);

        $recovery = $terms->render('debt_collector');
        $this->assertStringContainsString('5, 2', $recovery);
    }

    public function test_affiliate_terms_consume_evaluation_settings(): void
    {
        Setting::set('affiliates.evaluation', array_merge(
            app(AffiliateSettingsService::class)->evaluationSettings(),
            [
                'period_days' => 45,
                'monthly_registration_target' => 12,
                'kpis' => [
                    'qualified_referrals' => ['enabled' => true, 'target' => 12, 'weight' => 1],
                ],
            ]
        ));

        $html = app(AffiliateTermsService::class)->render();
        $this->assertStringContainsString('45 days', $html);
        $this->assertStringContainsString('12', $html);
    }

    public function test_material_terms_change_blocks_jobs_until_reacceptance(): void
    {
        $partner = Vendor::create([
            'vendor_number' => 'PT-GOV-REACC',
            'name' => 'Reaccept Valuer',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $this->completePartnerForJobs($partner);
        $terms = app(PartnerTermsService::class);
        $profile = app(PartnerProfileService::class);
        $v1 = $terms->latestAcceptance($partner->fresh());
        $this->assertNotNull($v1);
        $this->assertNull($profile->jobBlockReason($partner->fresh()));

        $stored = $terms->settings();
        $stored['material_change_requires_reacceptance'] = true;
        $stored['types']['valuer']['version'] = ((int) ($stored['types']['valuer']['version'] ?? 1)) + 1;
        Setting::set('partners.terms', $stored);

        $this->assertSame('terms', $profile->jobBlockReason($partner->fresh()));
        $this->assertStringContainsString('5 days', $v1->fresh()->rendered_text);

        $terms->accept($partner->fresh(), Request::create('/partner/terms', 'POST'));
        $this->assertNull($profile->jobBlockReason($partner->fresh()));
        $this->assertSame(2, $terms->latestAcceptance($partner->fresh())?->agreement_version);
    }

    public function test_sw_and_en_terms_are_distinct_and_complete(): void
    {
        $en = app(PartnerTermsService::class)->render('valuer', null, 'en');
        $sw = app(PartnerTermsService::class)->render('valuer', null, 'sw');
        $this->assertStringContainsString('Valuer Terms', $en);
        $this->assertStringContainsString('Masharti', $sw);
        $this->assertNotSame($en, $sw);

        foreach (['gps_installer', 'insurance', 'call_center', 'auctioneer', 'legal_partner'] as $type) {
            $this->assertStringContainsString('SLA', app(PartnerTermsService::class)->render($type, null, 'en'));
            $this->assertNotSame('', __('partner_terms.'.$type.'.title', [], 'sw'));
        }
    }

    public function test_fraud_and_compliance_suspension_are_not_auto_recovered(): void
    {
        Setting::set('partners.efficiency', array_merge(
            app(PartnerEfficiencyPolicy::class)->settings(),
            ['auto_recover' => true, 'min_jobs_for_score' => 3]
        ));
        $reviews = app(PartnerPerformanceReviewService::class);
        $deletion = app(PartnerDeletionService::class);

        foreach (['fraud', 'compliance'] as $kind) {
            $partner = Partner::create([
                'vendor_number' => 'PT-GOV-'.strtoupper($kind),
                'name' => ucfirst($kind).' Valuer',
                'category' => 'valuer',
                'status' => 'active',
            ]);
            foreach (range(1, 3) as $i) {
                PartnerTask::create([
                    'partner_id' => $partner->id,
                    'task_type' => 'asset_valuation',
                    'status' => 'completed',
                    'due_at' => now()->addDay(),
                    'completed_at' => now(),
                ]);
            }
            $deletion->deactivate($partner, null, $kind);
            $this->assertSame('skipped', $reviews->reviewPartner($partner->fresh()));
            $this->assertSame('suspended', $partner->fresh()->status);
            $this->assertSame($kind, $partner->fresh()->suspend_kind);
            $this->assertSame($kind === 'fraud' ? 'compliance' : 'compliance', app(PartnerProfileService::class)->jobBlockReason($partner->fresh()));
        }
    }

    public function test_recovery_expiry_escalates_stage_and_does_not_peer_reassign(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-ESC',
            'name' => 'Call Desk',
            'category' => 'call_center',
            'status' => 'active',
            'phone' => '255712349505',
        ]);
        $customer = Customer::create([
            'customer_number' => 'CU-GOV-ESC',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Deni',
            'phone' => '255721111222',
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-ESC',
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
            'application_number' => 'APP-ESC-1',
            'status' => 'disbursed',
            'current_stage' => 'disbursement',
            'requested_amount' => 100_000,
            'requested_tenure_months' => 6,
        ]);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-ESC-1',
            'principal_amount' => 100_000,
            'approved_amount' => 100_000,
            'outstanding_balance' => 40_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'status' => 'arrears',
        ]);
        $arrear = ArrearCase::create([
            'loan_id' => $loan->id,
            'days_past_due' => 14,
            'amount_in_arrears' => 40_000,
            'status' => 'open',
        ]);
        $assignment = RecoveryAssignment::create([
            'arrear_case_id' => $arrear->id,
            'partner_id' => $partner->id,
            'partner_type' => 'call_center',
            'status' => RecoveryAssignment::STATUS_ASSIGNED,
            'original_outstanding' => 40_000,
            'sla_due_at' => now()->subDay(),
            'assigned_at' => now()->subDays(8),
        ]);

        $result = app(RecoveryEscalationService::class)->processExpiredSlas();
        $this->assertGreaterThanOrEqual(1, $result['escalated']);
        $fresh = $assignment->fresh();
        $this->assertSame(RecoveryAssignment::STATUS_ESCALATED, $fresh->status);
        $this->assertSame($partner->id, $fresh->partner_id);
        $this->assertSame(0, RecoveryAssignment::query()
            ->where('arrear_case_id', $arrear->id)
            ->where('partner_type', 'call_center')
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->count());
        $this->assertSame('debt_collector', app(RecoveryEscalationService::class)->nextPartnerType('call_center'));
    }

    public function test_valuer_sla_clock_is_five_days_from_assignment_not_accept(): void
    {
        $this->assertSame(120, app(PartnerAutoAssignPolicy::class)->slaHoursForService('valuer'));
        $assignedAt = now();
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-CLOCK',
            'name' => 'Clock Valuer',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $task = PartnerTask::create([
            'partner_id' => $partner->id,
            'task_type' => 'asset_valuation',
            'status' => 'assigned',
            'created_at' => $assignedAt,
            'due_at' => $assignedAt->copy()->addHours(120),
        ]);

        $this->travel(6)->hours();
        $task->update(['accepted_at' => now(), 'status' => 'in_progress']);
        $this->assertEquals(
            $assignedAt->copy()->addHours(120)->timestamp,
            $task->fresh()->due_at->timestamp
        );
        $this->assertTrue($task->fresh()->due_at->gt($task->fresh()->accepted_at->copy()->addHours(100)));
    }

    public function test_admin_profiles_for_governed_types_expose_operation_and_agreements(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $tabs = app(PartnerProfileTabs::class);

        foreach (['gps_installer', 'insurance', 'call_center', 'auctioneer', 'legal_partner'] as $i => $category) {
            $partner = Vendor::create([
                'vendor_number' => 'PT-GOV-P'.$i,
                'name' => 'Profile '.$category,
                'category' => $category,
                'status' => 'active',
            ]);
            $keys = array_keys($tabs->tabs($partner));
            $this->assertContains('performance', $keys);
            $this->assertContains('agreements', $keys);
            $this->assertContains('documents', $keys);
            $this->assertContains('history', $keys);

            $this->actingAs($admin, 'admin')
                ->get(route('admin.partners.show', $partner))
                ->assertOk()
                ->assertSee('Can receive work', false)
                ->assertSee('Agreements', false)
                ->assertSee('History', false);
        }
    }

    public function test_yard_and_supplier_are_not_given_invented_performance_scores(): void
    {
        $yard = Partner::create(['vendor_number' => 'PT-GOV-YARD', 'name' => 'Yard Co', 'category' => 'yard', 'status' => 'active']);
        $supplier = Partner::create(['vendor_number' => 'PT-GOV-SUP', 'name' => 'Supply Co', 'category' => 'supplier', 'status' => 'active']);
        $this->assertNull(app(PartnerEfficiencyService::class)->forPartner($yard));
        $this->assertNull(app(PartnerEfficiencyService::class)->forPartner($supplier));
        $this->assertFalse(app(PartnerTermsService::class)->appliesTo($yard));
        $this->assertFalse(app(PartnerTermsService::class)->appliesTo($supplier));
    }

    public function test_swahili_governance_notifications_exist(): void
    {
        $this->assertNotSame('partner_governance.nudge_subject', __('partner_governance.nudge_subject', [], 'sw'));
        $this->assertNotSame('partner_governance.task_due_body', __('partner_governance.task_due_body', ['hours' => 12], 'sw'));
        $this->assertStringContainsString('12', __('partner_governance.task_due_body', ['hours' => 12], 'sw'));
    }

    public function test_performance_status_language_is_standardized(): void
    {
        $this->assertSame('Needs attention', PartnerPerformanceStatus::label(PartnerPerformanceStatus::NEEDS_ATTENTION, 'en'));
        $this->assertSame('At risk', PartnerPerformanceStatus::label(PartnerPerformanceStatus::AT_RISK, 'en'));
        $this->assertNotSame(
            PartnerPerformanceStatus::label(PartnerPerformanceStatus::NEEDS_ATTENTION, 'en'),
            PartnerPerformanceStatus::label(PartnerPerformanceStatus::NEEDS_ATTENTION, 'sw')
        );
    }

    public function test_performance_recovery_does_not_clear_expired_membership_or_historical_pay(): void
    {
        Setting::set('partners.efficiency', array_merge(
            app(PartnerEfficiencyPolicy::class)->settings(),
            [
                'min_jobs_for_score' => 3,
                'auto_nudge' => true,
                'auto_suspend' => true,
                'auto_recover' => true,
                'warnings_before_suspend' => 1,
                'nudge_cooldown_days' => 0,
            ]
        ));

        $partner = Vendor::create([
            'vendor_number' => 'PT-GOV-MEM',
            'name' => 'Membership Valuer',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $this->completePartnerForJobs($partner);

        $pay = app(PartnerSettlementService::class)->accrue(
            $partner,
            12_500,
            AffiliateCommissionWalletService::SOURCE_TYPE,
            1,
            'Historical valuation fee',
        );

        foreach (range(1, 3) as $i) {
            PartnerTask::create([
                'partner_id' => $partner->id,
                'task_type' => 'asset_valuation',
                'status' => 'failed',
                'due_at' => now()->subDay(),
                'completed_at' => now(),
            ]);
        }

        $reviews = app(PartnerPerformanceReviewService::class);
        $this->assertSame('suspended', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('performance', $partner->fresh()->suspend_kind);

        $partner->update([
            'membership_status' => 'expired',
            'membership_expires_at' => now()->subDays(40),
        ]);

        PartnerTask::query()->where('partner_id', $partner->id)->update([
            'status' => 'completed',
            'due_at' => now()->addDay(),
            'completed_at' => now(),
        ]);

        $this->assertSame('recovered', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('active', $partner->fresh()->status);
        $this->assertNull($partner->fresh()->suspend_kind);
        $this->assertSame('payment', app(PartnerProfileService::class)->jobBlockReason($partner->fresh()));
        $this->assertSame(12_500.0, (float) $pay->fresh()->amount);
        $this->assertNotSame('cancelled', $pay->fresh()->status);
    }

    public function test_warning_notification_uses_live_settings_remaining_count(): void
    {
        Setting::set('partners.efficiency', array_merge(
            app(PartnerEfficiencyPolicy::class)->settings(),
            [
                'min_jobs_for_score' => 3,
                'auto_nudge' => true,
                'auto_suspend' => true,
                'auto_recover' => true,
                'warnings_before_suspend' => 5,
                'nudge_cooldown_days' => 0,
            ]
        ));

        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-NUDGE',
            'name' => 'Nudge Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'phone' => '255712349808',
        ]);
        foreach (range(1, 3) as $i) {
            PartnerTask::create([
                'partner_id' => $partner->id,
                'task_type' => 'asset_valuation',
                'status' => 'failed',
                'due_at' => now()->subDay(),
                'completed_at' => now(),
            ]);
        }

        $this->assertSame('nudged', app(PartnerPerformanceReviewService::class)->reviewPartner($partner->fresh()));
        $log = NotificationLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertTrue(
            str_contains((string) $log->message, '4 warning')
            || str_contains((string) ($log->meta['params']['remaining'] ?? ''), '4'),
            'nudge remaining warnings must follow Settings, not a hardcoded count'
        );
    }

    public function test_valuer_sla_reassigns_after_grace_and_old_partner_cannot_write(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $firstUser = User::factory()->create(['role' => 'vendor']);
        $first = Vendor::create([
            'user_id' => $firstUser->id,
            'vendor_number' => 'PT-GOV-SLA-1',
            'name' => 'First Clock Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'regions' => ['Dar es Salaam'],
        ]);
        $this->completePartnerForJobs($first);
        $second = Vendor::create([
            'vendor_number' => 'PT-GOV-SLA-2',
            'name' => 'Peer Clock Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'regions' => ['Dar es Salaam'],
        ]);
        $this->completePartnerForJobs($second);

        $branch = Branch::query()->firstOrCreate(
            ['code' => 'BR-GOV-SLA'],
            ['name' => 'Gov SLA Branch', 'region' => 'Dar es Salaam', 'is_active' => true],
        );
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CU-GOV-SLA',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Owner',
            'phone' => '255721000111',
            'region' => 'Dar es Salaam',
        ]);
        $product = LoanProduct::query()->firstOrCreate(
            ['code' => 'AB'],
            [
                'name' => 'Asset Backed',
                'is_active' => true,
                'interest_rate' => 3.5,
                'min_amount' => 100_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
            ],
        );
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GOV-SLA',
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
        ]);
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Toyota Rav4',
            'registration_number' => 'T123GOV',
            'is_active' => true,
        ]);
        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => true,
            'valuation_status' => 'awaiting_valuation',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $assignment = app(ValuationPartnerService::class)->assign($application, $first, $admin, 'Field inspection');
        $task = $assignment->fresh('vendorTask')->vendorTask;
        $this->assertNotNull($task);
        $this->assertSame(120, (int) $task->created_at->diffInHours($task->due_at));

        $task->update([
            'status' => 'in_progress',
            'due_at' => now()->subHours(3),
        ]);

        $result = app(ServicePartnerReassignmentService::class)->processSla();
        $this->assertGreaterThanOrEqual(1, $result['reassigned']);
        $this->assertSame('cancelled', $task->fresh()->status);

        $replacement = PartnerTask::query()
            ->where('loan_application_id', $application->id)
            ->where('partner_id', $second->id)
            ->where('task_type', 'asset_valuation')
            ->first();
        $this->assertNotNull($replacement);

        $this->actingAs($firstUser)
            ->post(route('site.partner.task.start', $task))
            ->assertForbidden();
    }
}
