<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Services\PartnerAutoAssignPolicy;
use App\Services\PartnerEfficiencyService;
use App\Services\PartnerPerformanceReviewService;
use App\Services\PartnerProfileService;
use App\Services\PartnerTermsService;
use App\Services\RecoveryPolicyService;
use App\Services\RecoverySlaReminderService;
use App\Support\PartnerPerformanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PartnerGovernanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_valuer_sla_defaults_remain_five_days_from_origination_settings(): void
    {
        $policy = app(PartnerAutoAssignPolicy::class);

        $this->assertSame(5, $policy->slaDaysForService('valuer'));
        $this->assertSame(120, $policy->slaHoursForService('valuer'));
        $this->assertSame(3, $policy->slaDaysForService('insurance'));
        $this->assertSame([12, 4], $policy->remindHoursForService('valuer'));
        $this->assertSame(2, $policy->graceHoursForService('valuer'));
        $this->assertSame(3, $policy->maxReassignmentsForService('valuer'));
    }

    public function test_valuer_terms_render_existing_sla_and_performance_settings(): void
    {
        $html = app(PartnerTermsService::class)->render('valuer');

        $this->assertStringContainsString('5 days', $html);
        $this->assertStringContainsString('120 hours', $html);
        $this->assertStringContainsString('starts when the job or case is assigned', $html);
        $this->assertStringContainsString('90%', $html);
        $this->assertStringNotContainsString('terms.valuer.sla', $html);
    }

    public function test_gps_terms_keep_origination_and_recovery_slas_distinct(): void
    {
        $html = app(PartnerTermsService::class)->render('gps_installer');
        $recoveryDays = app(RecoveryPolicyService::class)->slaDaysForType('gps_partner');

        $this->assertStringContainsString('5 days', $html);
        $this->assertStringContainsString((string) $recoveryDays, $html);
        $this->assertStringContainsString('different SLAs', $html);
    }

    public function test_recovery_terms_use_recovery_policy_sla_days(): void
    {
        $html = app(PartnerTermsService::class)->render('debt_collector');
        $this->assertStringContainsString(app(RecoveryPolicyService::class)->slaDaysForType('debt_collector').' days', $html);
        $this->assertStringContainsString('next recovery stage', $html);
    }

    public function test_terms_snapshot_is_immutable_after_settings_change(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-TERM',
            'name' => 'Terms Valuer',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $terms = app(PartnerTermsService::class);
        $acceptance = $terms->accept($partner, Request::create('/partner/terms', 'POST'));

        Setting::set('partner_auto_assign.service.valuer.sla_days', 9);
        $this->assertStringContainsString('5 days', $acceptance->fresh()->rendered_text);
        $this->assertStringNotContainsString('9 days', $acceptance->fresh()->rendered_text);
    }

    public function test_can_receive_jobs_requires_terms_but_does_not_replace_the_gate(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-ELIG',
            'name' => 'Blocked Valuer',
            'category' => 'valuer',
            'applicant_category' => 'individual',
            'status' => 'active',
            'phone' => '255712349303',
            'metadata' => [
                'identity' => ['national_id' => '19900101123456789012', 'national_id_front' => 'a.jpg', 'national_id_back' => 'b.jpg'],
                'face_captures' => ['front' => 'f.jpg', 'left' => 'l.jpg', 'right' => 'r.jpg', 'holding_id' => 'h.jpg'],
                'residence' => ['region' => 'Dar es Salaam', 'district' => 'Ilala'],
                'payout_account' => ['type' => 'mobile_money'],
            ],
        ]);
        $profile = app(PartnerProfileService::class);
        $this->assertSame('terms', $profile->jobBlockReason($partner));

        app(PartnerTermsService::class)->accept($partner, Request::create('/partner/terms', 'POST'));
        $this->assertSame('payment', $profile->jobBlockReason($partner->fresh()));
    }

    public function test_towing_is_not_forced_into_terms_or_invented_kpis(): void
    {
        $towing = Partner::create([
            'vendor_number' => 'PT-GOV-TOW',
            'name' => 'Tow Co',
            'category' => 'towing',
            'status' => 'active',
        ]);
        $this->assertFalse(app(PartnerTermsService::class)->appliesTo($towing));
        $this->assertNull(app(PartnerEfficiencyService::class)->summariesFor(collect([$towing]))[$towing->id] ?? null);
    }

    public function test_performance_is_explainable_and_uses_settings_targets(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-KPI',
            'name' => 'Explain Valuer',
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

        $row = app(PartnerEfficiencyService::class)->forPartner($partner);
        $this->assertNotNull($row);
        $this->assertSame(PartnerEfficiencyService::BAND_STRONG, $row['band']);
        $this->assertContains($row['status'], [
            PartnerPerformanceStatus::EXCELLENT,
            PartnerPerformanceStatus::GOOD_STANDING,
        ]);
        $this->assertSame(90.0, $row['target_on_time_percent']);
        $this->assertNotEmpty($row['why']);
        $this->assertNotEmpty($row['next_action']);
        $this->assertNotEmpty($row['kpi_rows']);
    }

    public function test_performance_suspension_can_auto_recover_and_admin_suspend_cannot(): void
    {
        Setting::set('partners.efficiency', [
            'min_jobs_for_score' => 3,
            'strong_score' => 80,
            'watch_score' => 60,
            'force_at_risk_escalation_percent' => 40,
            'force_at_risk_fail_percent' => 40,
            'auto_nudge' => true,
            'auto_suspend' => true,
            'auto_recover' => true,
            'warnings_before_suspend' => 2,
            'nudge_cooldown_days' => 1,
            'recover_lookback_days' => 90,
            'target_on_time_percent' => 90,
            'target_completion_percent' => 95,
        ]);

        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-REC',
            'name' => 'Recovering Collector',
            'category' => 'debt_collector',
            'status' => 'active',
            'phone' => '255712349101',
        ]);
        foreach (range(1, 3) as $i) {
            PartnerTask::create([
                'partner_id' => $partner->id,
                'task_type' => 'collection',
                'status' => 'failed',
                'due_at' => now()->subDay(),
                'completed_at' => now(),
            ]);
        }

        $reviews = app(PartnerPerformanceReviewService::class);
        $this->assertSame('nudged', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('suspended', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('performance', $partner->fresh()->suspend_kind);

        PartnerTask::query()->where('partner_id', $partner->id)->update([
            'status' => 'completed',
            'due_at' => now()->addDay(),
            'completed_at' => now(),
        ]);

        $this->assertSame('recovered', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('active', $partner->fresh()->status);
        $this->assertNull($partner->fresh()->suspend_kind);
        $this->assertNotSame(\App\Support\PartnerPerformanceStatus::SUSPENDED, $partner->fresh()->performance_status);

        $admin = Partner::create([
            'vendor_number' => 'PT-GOV-ADM',
            'name' => 'Admin Disabled',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        app(\App\Services\PartnerDeletionService::class)->deactivate($admin, null, 'admin');
        $this->assertSame('skipped', $reviews->reviewPartner($admin->fresh()));
        $this->assertSame('suspended', $admin->fresh()->status);
        $this->assertSame('admin', $admin->fresh()->suspend_kind);
    }

    public function test_recovery_reminders_use_sla_due_at_and_policy_days(): void
    {
        Setting::set('recovery.remind_days', '3,1');
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-REM',
            'name' => 'Call Desk',
            'category' => 'call_center',
            'status' => 'active',
            'phone' => '255712349202',
        ]);
        $customer = \App\Models\Customer::create([
            'customer_number' => 'CU-GOV-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Deni',
            'phone' => '25572'.random_int(1000000, 9999999),
        ]);
        $product = \App\Models\LoanProduct::create([
            'code' => 'IL-GOV-'.random_int(100, 999),
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = \App\Models\LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GOV-'.random_int(100, 999),
            'status' => 'disbursed',
            'current_stage' => 'disbursement',
            'requested_amount' => 100_000,
            'requested_tenure_months' => 6,
        ]);
        $loan = \App\Models\Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-GOV-'.random_int(100, 999),
            'principal_amount' => 100_000,
            'approved_amount' => 100_000,
            'outstanding_balance' => 40_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'status' => 'arrears',
        ]);
        $arrear = \App\Models\ArrearCase::create([
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
            'sla_due_at' => now()->addDays(3)->setTime(17, 0),
            'assigned_at' => now(),
        ]);

        $sent = app(RecoverySlaReminderService::class)->sendDueReminders();
        $this->assertGreaterThanOrEqual(1, $sent);
        $this->assertNotEmpty($assignment->fresh()->sla_reminder_meta);
    }

    public function test_valuer_profile_shows_governance_tabs_and_explainable_performance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $partner = Partner::create([
            'vendor_number' => 'PT-GOV-SHOW',
            'name' => 'Profile Valuer Gov',
            'category' => 'valuer',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Overview', false)
            ->assertSee('Agreements', false)
            ->assertSee('Documents', false)
            ->assertSee('History', false)
            ->assertSee('Performance', false);
    }

    public function test_swahili_terms_and_governance_copy_exist(): void
    {
        $this->assertNotSame('', __('partner_terms.valuer.title', [], 'sw'));
        $this->assertNotSame('partner_governance.status_at_risk', __('partner_governance.status_at_risk', [], 'sw'));
        $this->assertStringContainsString('SLA', app(PartnerTermsService::class)->render('call_center', null, 'sw'));
    }
}
