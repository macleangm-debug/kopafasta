<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\DisbursementSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisbursementSlaFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function application(): LoanApplication
    {
        $branch = Branch::create([
            'code' => 'DSL'.random_int(10, 99),
            'name' => 'Disbursement SLA Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-DSL-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 20_000,
        ]);

        $customer = Customer::create([
            'user_id' => User::factory()->create([
                'role' => 'borrower',
                'pin_hash' => bcrypt('1234'),
            ])->id,
            'customer_number' => 'CU-DSL-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'Sla',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-DSL-'.random_int(1000, 9999),
            'status' => 'approved',
            'current_stage' => 'post_approval_fees',
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 6,
            'offer_status' => 'accepted',
        ]);
    }

    public function test_fast_track_is_off_by_default(): void
    {
        $sla = app(DisbursementSlaService::class);

        $this->assertFalse($sla->enabled());
        $this->assertSame(2, $sla->standardWorkingDays());
    }

    public function test_fast_track_opt_in_creates_fee_when_enabled(): void
    {
        Setting::set('underwriting.enable_disbursement_fast_track', true);
        Setting::set('underwriting.disbursement_fast_track_fee_amount', 25000);
        Setting::set('underwriting.disbursement_fast_track_business_hours', 12);

        $application = $this->application();
        $sla = app(DisbursementSlaService::class);

        $this->assertTrue($sla->enabled());

        $result = $sla->setOptIn($application, true);
        $this->assertTrue($result['opted_in']);
        $this->assertNotNull($result['fee']);
        $this->assertSame(DisbursementSlaService::FEE_CODE, strtoupper($result['fee']->code));
        $this->assertSame(25000.0, (float) $result['fee']->calculated_amount);

        $sla->setOptIn($application->fresh(), false);
        $this->assertNull($sla->fastTrackFee($application->fresh()));
    }

    public function test_contract_sla_uses_working_days_or_fast_track_hours(): void
    {
        Setting::set('underwriting.disbursement_sla_working_days', 2);
        Setting::set('underwriting.enable_disbursement_fast_track', true);
        Setting::set('underwriting.disbursement_fast_track_fee_amount', 10000);
        Setting::set('underwriting.disbursement_fast_track_business_hours', 12);

        $application = $this->application();
        $sla = app(DisbursementSlaService::class);
        $sla->startClockOnContractSigned($application->fresh());

        $this->assertSame('standard', data_get($application->fresh()->screening_payload, 'disbursement.sla_mode'));

        $sla->setOptIn($application->fresh(), true);
        $fee = $sla->fastTrackFee($application->fresh());
        $fee->update(['status' => 'paid', 'amount_paid' => $fee->calculated_amount, 'paid_at' => now()]);

        $sla->startClockOnContractSigned($application->fresh());
        $this->assertSame('fast_track', data_get($application->fresh()->screening_payload, 'disbursement.sla_mode'));
    }
}
