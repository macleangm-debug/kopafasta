<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\CollateralSecureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollateralSecureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'CS'.random_int(10, 99),
            'name' => 'Collateral Secure Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    /** @return array{0: LoanApplication, 1: Customer, 2: Customer, 3: CustomerGuarantor} */
    private function applicationWithGuarantor(User $admin): array
    {
        $product = LoanProduct::create([
            'code' => 'IL-CS-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 20_000,
            'requires_guarantor' => true,
        ]);

        LoanProduct::create([
            'code' => 'AB',
            'name' => 'Asset Backed',
            'is_active' => true,
            'interest_rate' => 0.16,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 24,
            'application_fee_amount' => 50_000,
        ]);

        $borrower = Customer::create([
            'user_id' => User::factory()->create([
                'role' => 'borrower',
                'pin_hash' => bcrypt('1234'),
            ])->id,
            'customer_number' => 'CU-CSB-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $admin->branch_id,
        ]);

        $guarantorCustomer = Customer::create([
            'user_id' => User::factory()->create([
                'role' => 'borrower',
                'pin_hash' => bcrypt('1234'),
            ])->id,
            'customer_number' => 'CU-CSG-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => '25576'.random_int(1000000, 9999999),
            'branch_id' => $admin->branch_id,
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $app = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'branch_id' => $admin->branch_id,
            'application_number' => 'APP-CS-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
            'application_fee_amount' => 20_000,
            'application_fee_status' => 'paid',
        ]);

        $contact = Guarantor::create([
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => $guarantorCustomer->phone,
            'relationship' => 'sibling',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $app->id,
            'status' => 'approved',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $product->id,
            'guarantor_customer_id' => $guarantorCustomer->id,
            'type' => 'member',
            'status' => 'accepted',
            'contact' => $guarantorCustomer->phone,
            'invitee_name' => 'Grace Guarantor',
            'token' => 'tok-cs-'.random_int(10000, 99999),
        ]);

        return [$app, $borrower, $guarantorCustomer, $link];
    }

    public function test_borrower_no_then_decline_ask_guarantor_rejects_application(): void
    {
        $admin = $this->staff();
        [$app, $borrower] = $this->applicationWithGuarantor($admin);
        $service = app(CollateralSecureService::class);

        $service->request($app, $admin);
        $service->borrowerHasCollateral($app->fresh(), $borrower, false);
        $service->borrowerAskGuarantor($app->fresh(), $borrower, false);

        $this->assertSame('rejected', $app->fresh()->status);
        $this->assertSame(
            CollateralSecureService::STATUS_REJECTED,
            data_get($app->fresh()->screening_payload, 'collateral_secure.status')
        );
    }

    public function test_borrower_yes_links_asset_and_keeps_product_code(): void
    {
        $admin = $this->staff();
        [$app, $borrower] = $this->applicationWithGuarantor($admin);
        $service = app(CollateralSecureService::class);
        $originalCode = $app->product->code;

        $asset = CustomerAsset::create([
            'customer_id' => $borrower->id,
            'asset_type' => 'land',
            'label' => 'Plot A',
            'is_active' => true,
        ]);

        $service->request($app, $admin);
        $service->borrowerHasCollateral($app->fresh(), $borrower, true);
        $state = $service->linkAsset($app->fresh(), $borrower, $asset);

        // Land skips insurance; AB fee delta should move to awaiting_fee or secured.
        $this->assertContains($state['status'], [
            CollateralSecureService::STATUS_AWAITING_FEE,
            CollateralSecureService::STATUS_SECURED,
        ]);

        if ($state['status'] === CollateralSecureService::STATUS_AWAITING_FEE) {
            $service->markFeePaid($app->fresh());
        }

        $app->refresh()->load('product');
        $this->assertSame($originalCode, $app->product->code);
        $this->assertSame(
            CollateralSecureService::STATUS_SECURED,
            data_get($app->screening_payload, 'collateral_secure.status')
        );
        $this->assertDatabaseHas('loan_application_assets', [
            'loan_application_id' => $app->id,
            'customer_asset_id' => $asset->id,
        ]);
    }

    public function test_fee_gate_runs_before_insurance_on_vehicle(): void
    {
        $admin = $this->staff();
        [$app, $borrower] = $this->applicationWithGuarantor($admin);
        $service = app(CollateralSecureService::class);

        $asset = CustomerAsset::create([
            'customer_id' => $borrower->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz',
            'registration_number' => 'T123',
            'is_active' => true,
            'metadata' => ['details' => []],
        ]);

        $service->request($app, $admin);
        $service->borrowerHasCollateral($app->fresh(), $borrower, true);
        $state = $service->linkAsset($app->fresh(), $borrower, $asset);

        $this->assertSame(CollateralSecureService::STATUS_AWAITING_FEE, $state['status']);

        $afterFee = $service->markFeePaid($app->fresh());
        $this->assertSame(CollateralSecureService::STATUS_AWAITING_INSURANCE, $afterFee['status']);
    }

    public function test_expire_waits_for_grace_days_after_due(): void
    {
        $admin = $this->staff();
        [$app, $borrower] = $this->applicationWithGuarantor($admin);
        $service = app(CollateralSecureService::class);

        $service->request($app, $admin);
        $payload = $app->fresh()->screening_payload;
        $payload['collateral_secure']['due_at'] = now()->subDay()->toIso8601String();
        $app->update(['screening_payload' => $payload]);

        // Still within default 3-day grace — should stay open.
        $state = $service->expireIfNeeded($app->fresh());
        $this->assertSame(CollateralSecureService::STATUS_AWAITING_BORROWER, $state['status'] ?? null);
        $this->assertNotSame('rejected', $app->fresh()->status);

        $payload = $app->fresh()->screening_payload;
        $payload['collateral_secure']['due_at'] = now()->subDays(4)->toIso8601String();
        $app->update(['screening_payload' => $payload]);

        $state = $service->expireIfNeeded($app->fresh());
        $this->assertSame(CollateralSecureService::STATUS_REJECTED, $state['status'] ?? null);
        $this->assertSame('rejected', $app->fresh()->status);
    }

    public function test_insure_it_redirects_to_payment_gate(): void
    {
        $admin = $this->staff();
        [$app, $borrower] = $this->applicationWithGuarantor($admin);
        $service = app(CollateralSecureService::class);

        $asset = CustomerAsset::create([
            'customer_id' => $borrower->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz Insure',
            'registration_number' => 'T999INS',
            'is_active' => true,
            'metadata' => ['details' => []],
        ]);

        $service->request($app, $admin);
        $service->borrowerHasCollateral($app->fresh(), $borrower, true);
        $service->linkAsset($app->fresh(), $borrower, $asset);
        $service->markFeePaid($app->fresh());

        $this->assertSame(
            CollateralSecureService::STATUS_AWAITING_INSURANCE,
            data_get($app->fresh()->screening_payload, 'collateral_secure.status')
        );

        $payIn = \Mockery::mock(\App\Services\PayInService::class)->makePartial();
        $payIn->shouldReceive('isConfigured')->andReturn(true);
        $payIn->shouldReceive('isLiveCollectionEnabled')->andReturn(true);
        $payIn->shouldReceive('normalizePhone')->andReturnUsing(fn ($p) => (string) $p);
        $payIn->shouldReceive('collect')->once()->andReturn([
            'ok' => true,
            'request_ref' => 'PAYREF-INS-1',
            'status' => 'processing',
            'operator' => 'mpesa',
            'message' => 'Confirm on phone',
            'raw' => [],
        ]);
        $this->app->instance(\App\Services\PayInService::class, $payIn);

        $user = $borrower->user;
        $response = $this->actingAs($user)
            ->from(route('site.borrower.application', $app))
            ->post(route('site.borrower.collateral-secure.buy-insurance', $app), [
                'insured_value' => '1,000,000',
            ]);

        $payment = \App\Models\CustomerPayment::query()
            ->where('customer_id', $borrower->id)
            ->where('payment_type', 'insurance_premium')
            ->latest('id')
            ->first();

        $this->assertNotNull($payment);
        $response->assertRedirect(route('site.borrower.payments.show', $payment));
        $this->assertSame('awaiting_payment', $payment->status);
        $this->assertSame('payment_pending', data_get($app->fresh()->screening_payload, 'collateral_secure.insurance_purchase.status'));
        $this->assertSame($payment->id, (int) data_get($app->fresh()->screening_payload, 'collateral_secure.insurance_purchase.payment_id'));

        $this->actingAs($user)
            ->post(route('site.borrower.payments.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => $borrower->phone,
                'mobile_number_local' => preg_replace('/^\+?255/', '', (string) $borrower->phone),
            ])
            ->assertRedirect(route('site.borrower.payments.show', $payment));

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame('payin', $payment->fresh()->provider);
        $this->assertSame(
            preg_replace('/\D+/', '', (string) $borrower->phone),
            preg_replace('/\D+/', '', (string) $payment->fresh()->mobile_number)
        );
    }

    public function test_insure_it_resumes_pending_payment_gate(): void
    {
        $admin = $this->staff();
        [$app, $borrower] = $this->applicationWithGuarantor($admin);
        $service = app(CollateralSecureService::class);

        $asset = CustomerAsset::create([
            'customer_id' => $borrower->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz Resume',
            'registration_number' => 'T888INS',
            'is_active' => true,
            'metadata' => ['details' => []],
        ]);

        $service->request($app, $admin);
        $service->borrowerHasCollateral($app->fresh(), $borrower, true);
        $service->linkAsset($app->fresh(), $borrower, $asset);
        $service->markFeePaid($app->fresh());

        $payment = \App\Models\CustomerPayment::create([
            'reference' => 'INS-RESUME-1',
            'customer_id' => $borrower->id,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => 35000,
            'currency' => 'TZS',
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => 'PAYREF-RESUME',
            'mobile_number' => $borrower->phone,
        ]);

        $service->recordInsurancePurchase($app->fresh(), [
            'insured_value' => 1_000_000,
            'premium' => 35000,
            'rate_percent' => 3.5,
            'markup_percent' => 0,
            'payment_id' => $payment->id,
            'payment_reference' => $payment->reference,
            'status' => 'payment_pending',
        ]);

        $response = $this->actingAs($borrower->user)
            ->post(route('site.borrower.collateral-secure.buy-insurance', $app), [
                'insured_value' => '1,000,000',
            ]);

        $response->assertRedirect(route('site.borrower.payments.show', $payment));
        $this->assertSame(1, \App\Models\CustomerPayment::query()
            ->where('customer_id', $borrower->id)
            ->where('payment_type', 'insurance_premium')
            ->count());
    }
}
