<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use App\Services\CreditEligibilityPolicyService;
use App\Services\GroupLendingService;
use App\Services\PartnerEarningsCatalogService;
use App\Services\ScreeningSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScreeningSequenceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
        Setting::set('underwriting.verified_capacity_auto_reject_delay_hours', 6);
        Setting::set('underwriting.group_member_hard_fail_action', 'replace_member');
    }

    public function test_gate_1_fail_parks_for_configured_12_hours_and_cannot_be_sent_to_committee(): void
    {
        $application = $this->individual(100_000, 2_000_000);
        $service = app(CapacityAutoRejectService::class);
        $state = $service->evaluateAndPark($application->fresh(['customer', 'product']));

        $this->assertNotNull($state);
        $this->assertSame('declared', $state['gate'] ?? null);
        $this->assertSame(12, (int) $state['delay_hours']);
        $this->assertTrue($service->isPending($application->fresh()));

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());
        $this->assertFalse($snap['later_unlocked']);
        $this->assertTrue($snap['pending_rejection']);
        $this->assertFalse($snap['declared']['pass']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.workflow', $application), [
                'action' => 'submit_recommendation',
                'recommendation_type' => 'approve',
                'remarks' => 'Trying to override a hard Gate 1 fail.',
            ])
            ->assertSessionHasErrors();
    }

    public function test_gate_1_pass_unlocks_gate_2_and_locks_later_gates(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $service = app(CapacityAutoRejectService::class);
        $this->assertNull($service->evaluateAndPark($application->fresh(['customer', 'product'])));

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());
        $this->assertTrue($snap['declared']['pass']);
        $this->assertFalse($snap['verified']['pass']);
        $this->assertFalse($snap['later_unlocked']);
        $this->assertTrue(app(ScreeningSequenceService::class)->gateUnlocked($application->fresh(), 'income'));
        $this->assertFalse(app(ScreeningSequenceService::class)->gateUnlocked($application->fresh(), 'identity'));
    }

    public function test_gate_2_system_fail_uses_settings_delay_and_freezes_deadline(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $this->keyStatements($application, 1_000);

        Setting::set('underwriting.verified_capacity_auto_reject_delay_hours', 6);
        $state = app(CapacityAutoRejectService::class)->evaluateVerifiedAndPark($application->fresh(['customer', 'product']));
        $this->assertNotNull($state);
        $this->assertSame('verified', $state['gate'] ?? null);
        $this->assertSame(6, (int) $state['delay_hours']);
        $frozen = $state['auto_reject_at'];

        Setting::set('underwriting.verified_capacity_auto_reject_delay_hours', 24);
        $again = app(CapacityAutoRejectService::class)->evaluateVerifiedAndPark($application->fresh());
        $this->assertSame($frozen, $again['auto_reject_at'] ?? null);
        $this->assertSame(6, (int) data_get($application->fresh()->screening_payload, 'capacity_auto_reject.delay_hours'));
    }

    public function test_qualitative_concern_does_not_start_gate_2_timer(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.bank_or_mobile_money'] = [
            'verdict' => 'fail',
            'fail_reason_code' => 'custom',
            'fail_reason_custom' => 'Odd pattern noted',
            'source' => 'human',
        ];
        $application->update(['screening_payload' => $payload]);

        $this->assertNull(app(CapacityAutoRejectService::class)->evaluateVerifiedAndPark($application->fresh(['customer', 'product'])));
        $this->assertFalse(app(CapacityAutoRejectService::class)->isPending($application->fresh()));
    }

    public function test_duplicate_fire_due_does_not_double_reject(): void
    {
        $application = $this->individual(100_000, 2_000_000);
        $service = app(CapacityAutoRejectService::class);
        $service->evaluateAndPark($application->fresh(['customer', 'product']));
        Carbon::setTestNow(now()->addHours(13));
        $this->assertCount(1, $service->fireDue());
        $this->assertCount(0, $service->fireDue());
        $this->assertSame('rejected', $application->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_existing_file_with_later_gate_work_stays_unlocked(): void
    {
        $application = $this->individual(5_000_000, 200_000);
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['identity.face_vs_nida'] = [
            'verdict' => null,
            'at' => now()->toIso8601String(),
        ];
        $payload['screening_checklist']['by_subject']['member:3']['items']['identity.nida_vs_dob'] = [
            'verdict' => 'fail',
            'fail_reason_code' => 'nida_impossible',
            'source' => 'system',
        ];
        $payload['collateral_secure']['valuation']['status'] = 'completed';
        $application->update(['screening_payload' => $payload]);

        $snap = app(ScreeningSequenceService::class)->snapshot($application->fresh());
        $this->assertTrue($snap['grandfathered']);
        $this->assertTrue($snap['later_unlocked']);
        $this->assertFalse($snap['pending_rejection']);
    }

    public function test_group_member_fail_does_not_park_the_whole_application(): void
    {
        Setting::setMany(['loan.group_min_members' => 3, 'loan.group_max_members' => 10]);
        $product = LoanProduct::create([
            'code' => 'GL',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 200_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        $leader = $this->person('Leader', 'G', '255710000001', 5_000_000);
        $ok = $this->person('Ok', 'Member', '255710000002', 5_000_000);
        $weak = $this->person('Weak', 'Member', '255710000003', 1_000);

        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SEQ-GRP',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        app(GroupLendingService::class)->createForApplication(
            $application,
            [
                ['customer_id' => $leader->id, 'requested_amount' => 300_000, 'role' => 'leader'],
                ['customer_id' => $ok->id, 'requested_amount' => 300_000, 'role' => 'member'],
                ['customer_id' => $weak->id, 'requested_amount' => 300_000, 'role' => 'member'],
            ],
            'Seq Group',
            'Business',
        );

        $parked = app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product', 'loanGroup.members.customer']));
        $this->assertNull($parked);

        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh(['customer', 'product', 'loanGroup.members.customer']));
        $this->assertSame(CreditEligibilityPolicyService::ACTION_RESOLVE_MEMBERS, $policy['application_action']);
        $this->assertSame('replace_group_member', $policy['resolution']['resolution'] ?? null);
        $this->assertTrue($policy['resolution']['blocking'] ?? false);
    }

    public function test_borrower_fail_is_not_rescued_by_a_strong_guarantor(): void
    {
        $application = $this->individual(1_000, 2_000_000);
        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh(['customer', 'product']));
        $this->assertSame(CreditEligibilityPolicyService::ACTION_PENDING_REJECTION, $policy['application_action']);
        $this->assertStringContainsString('guarantor cannot rescue', strtolower($policy['reason'] ?? ''));
    }

    public function test_partner_earnings_catalog_covers_current_paid_types(): void
    {
        $types = collect(app(PartnerEarningsCatalogService::class)->earningPartnerTypes())->pluck('partner_type')->all();
        foreach (['valuer', 'recovery', 'affiliate', 'insurance', 'capital'] as $type) {
            $this->assertContains($type, $types);
        }
        $capital = collect(app(PartnerEarningsCatalogService::class)->earningPartnerTypes())
            ->firstWhere('partner_type', 'capital');
        $this->assertSame('capital_agreement', $capital['formula'] ?? null);
        $this->assertSame('shared', collect(app(PartnerEarningsCatalogService::class)->earningPartnerTypes())
            ->firstWhere('partner_type', 'valuer')['wallet'] ?? null);
    }

    private function individual(float $income, float $amount): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'IL-SEQ-'.random_int(100, 999),
            'name' => 'Seq Individual',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $customer = $this->person('Seq', 'Borrower', '25571'.random_int(1000000, 9999999), $income);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SEQ-'.random_int(1000, 9999),
            'requested_amount' => $amount,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }

    private function person(string $first, string $last, string $phone, float $income): Customer
    {
        return Customer::create([
            'customer_number' => 'CU-SEQ-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'monthly_income' => $income,
        ]);
    }

    private function keyStatements(LoanApplication $application, float $total): void
    {
        $payload = $application->screening_payload ?? [];
        $payload['screening_checklist']['by_subject']['borrower']['items']['activity_income.income_evidence'] = [
            'verdict' => 'fail',
            'fail_reason_code' => 'revenue_mismatch',
            'source' => 'system',
            'statement_deposits_total' => $total,
            'statement_months' => 6,
            'statement_monthly' => round($total / 6, 2),
        ];
        $application->update(['screening_payload' => $payload]);
    }
}
