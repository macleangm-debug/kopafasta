<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffordabilityPolicyService;
use App\Services\AffordabilityService;
use App\Services\CapacityAutoRejectService;
use App\Services\LoanAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffordabilityPolicyDecisionLetterFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
        app(AffordabilityPolicyService::class)->persistRatioPct(33.33);
    }

    public function test_affordability_uses_settings_ratio_not_hardcoded_constant(): void
    {
        app(AffordabilityPolicyService::class)->persistRatioPct(30.0);

        $application = $this->parkCandidate(monthlyIncome: 100_000, amount: 2_000_000);
        $eval = app(AffordabilityService::class)->evaluate($application->fresh(['customer', 'product']), declaredOnly: true);

        $this->assertSame(30.0, (float) $eval['repayment_ratio_pct']);
        $this->assertSame(30.0, (float) data_get($eval, 'affordability_policy.repayment_ratio_pct'));
        $this->assertSame(30_000.0, (float) $eval['max_repayment_capacity']);
    }

    public function test_park_and_letter_use_decision_snapshot_not_live_settings(): void
    {
        app(AffordabilityPolicyService::class)->persistRatioPct(30.0);
        $application = $this->parkCandidate(monthlyIncome: 100_000, amount: 2_000_000);
        $state = app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product']));

        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, $state['status'] ?? null);
        $this->assertSame(30.0, (float) ($state['repayment_ratio_pct'] ?? 0));
        $this->assertSame(30.0, (float) data_get($state, 'affordability_policy.repayment_ratio_pct'));

        app(AffordabilityPolicyService::class)->persistRatioPct(25.0);

        $service = app(LoanAgreementService::class);
        $ref = new \ReflectionClass($service);
        $withFields = $ref->getMethod('withRejectionLetterFields');
        $withFields->setAccessible(true);
        $fromApp = $ref->getMethod('snapshotFromApplication');
        $fromApp->setAccessible(true);

        $snapshot = $withFields->invoke(
            $service,
            $application->fresh(['customer', 'product']),
            $fromApp->invoke($service, $application->fresh(['customer', 'product'])),
        );

        $this->assertSame('30%', $snapshot['affordability_ratio'] ?? null);
        $this->assertSame(30.0, (float) ($snapshot['affordability_ratio_pct'] ?? 0));
        $this->assertTrue((bool) ($snapshot['is_capacity_rejection'] ?? false));

        $snapshot['is_preview'] = true;
        $rendered = view('pdf.rejection-letter', [
            'application' => $application->fresh(['customer', 'product']),
            'snapshot' => $snapshot,
            'agreement' => new LoanAgreement([
                'reference' => 'PREVIEW-TEST',
                'document_type' => 'rejection_letter',
            ]),
        ])->render();

        $this->assertStringContainsString('30%', $rendered);
        $this->assertStringNotContainsString('25%', $rendered);
        $this->assertStringNotContainsString('muhuri wa wakili', mb_strtolower($rendered));
        $this->assertStringNotContainsString('legal advocate stamp', mb_strtolower($rendered));
        $this->assertStringContainsString('PREVIEW / NOT ISSUED', $rendered);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.rejection-letter.preview', $application))
            ->assertOk();
    }

    private function parkCandidate(float $monthlyIncome, float $amount): LoanApplication
    {
        $customer = Customer::create([
            'customer_number' => 'CU-AFF-POL-'.uniqid(),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Mwangi',
            'phone' => '255799'.random_int(100000, 999999),
            'monthly_income' => $monthlyIncome,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-AFF-'.uniqid(),
            'name' => 'Afford Policy',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-AFF-'.uniqid(),
            'requested_amount' => $amount,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }
}
