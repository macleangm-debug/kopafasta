<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\LoanApplicationDraftService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroPassCPaymentGateInvariantFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P0-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gate',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
        ]);
    }

    private function product(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'code' => 'IL-P0-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.19,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 10_000,
        ], $overrides));
    }

    private function quotePayload(LoanProduct $product): array
    {
        return [
            'form' => [
                'loan_product_id' => $product->id,
                'requested_amount' => 500_000,
                'requested_tenure_months' => 6,
                'purpose' => 'business_expansion',
            ],
            'step_key' => 'quote',
        ];
    }

    public function test_draft_paid_flag_alone_does_not_satisfy_fee_gate(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $payload = array_merge($this->quotePayload($product), [
            'application_fee' => [
                'status' => 'paid',
                'amount' => 10_000,
                'reference' => 'FAKE-PAID',
            ],
        ]);

        $fees = app(ApplicationFeePaymentService::class);
        $this->assertSame('due', $fees->obligation($customer, $product, $payload)['status']);
        $this->assertFalse($fees->isSatisfiedFor($customer, $product, $payload));
        $this->assertTrue($fees->blocksWizardStep('guarantor'));
    }

    public function test_cancel_resume_cannot_advance_to_guarantor_without_verified_payment(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $fees = app(ApplicationFeePaymentService::class);
        $drafts = app(LoanApplicationDraftService::class);

        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'APP-P0-CANCEL',
            'payload' => $this->quotePayload($product),
            'saved_at' => now(),
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-P0-OPEN',
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => 'APP-P0-CANCEL',
                    'back_url' => route('site.borrower.apply', [
                        'product' => $product->id,
                        'resume' => 1,
                        'step_key' => 'quote',
                    ]),
                ],
            ],
        ]);

        $drafts->saveApplicationFee($customer, $product->id, [
            'status' => 'processing',
            'reference' => $payment->reference,
            'payment_id' => $payment->id,
            'amount' => 10_000,
        ]);

        // Poisoned client draft must not open guarantor.
        $poisoned = array_merge($draft->fresh()->payload ?? [], [
            'draft_reference' => 'APP-P0-CANCEL',
            'application_fee' => ['status' => 'paid', 'payment_id' => $payment->id, 'amount' => 10_000],
            'step_key' => 'guarantor',
        ]);
        $this->assertFalse($fees->isSatisfiedFor($customer, $product, $poisoned));
        $this->assertTrue($drafts->shouldClampToFeeGate($customer, $product, $poisoned));

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $product->id,
                'resume' => 1,
                'step_key' => 'guarantor',
            ]))
            ->assertOk()
            ->assertViewHas('savedDraft', function ($saved) {
                $key = is_array($saved) ? ($saved['resume_target']['step_key'] ?? $saved['step_key'] ?? null) : null;

                return $key !== 'guarantor';
            });
    }

    public function test_verified_fee_opens_configured_next_step_for_malkia_like_il(): void
    {
        $customer = $this->borrower();
        $product = $this->product([
            'code' => 'WL',
            'name' => 'Women Loan',
            'requires_guarantor' => true,
        ]);

        $payload = array_merge($this->quotePayload($product), [
            'draft_reference' => 'APP-WL-FEE',
        ]);
        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'APP-WL-FEE',
            'payload' => $payload,
            'saved_at' => now(),
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-WL-PAID',
            'paid_at' => now(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => 'APP-WL-FEE',
                ],
            ],
        ]);

        app(LoanApplicationDraftService::class)->saveApplicationFee($customer, $product->id, [
            'status' => 'paid',
            'reference' => $payment->reference,
            'payment_id' => $payment->id,
            'amount' => 10_000,
            'paid_at' => now()->toIso8601String(),
        ]);

        $fees = app(ApplicationFeePaymentService::class);
        $withRef = array_merge($payload, [
            'application_fee' => [
                'status' => 'paid',
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => 10_000,
            ],
        ]);
        $this->assertTrue($fees->isSatisfiedFor($customer, $product, $withRef));
        $this->assertSame('guarantor', $fees->nextStepAfterApplicationFee($customer, $product, $withRef));

        $plan = collect(app(SmartLoanApplicationWizardService::class)
            ->borrowerStepPlan($customer, $product, 500_000))
            ->pluck('key')
            ->all();
        $this->assertSame(['quote', 'guarantor', 'review', 'submit'], $plan);
    }

    public function test_education_and_kilimo_fixed_purposes(): void
    {
        $el = LoanProduct::create([
            'code' => 'EL',
            'name' => 'Education Loan',
            'category' => 'education',
            'purpose_mode' => 'fixed',
            'fixed_purpose' => 'education',
            'is_active' => true,
            'interest_rate' => 0.16,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);
        $this->assertTrue($el->hasFixedPurpose());
        $this->assertSame('education', $el->fixedPurposeKey());
        $this->assertSame('Education', loan_purpose_label('education'));
        $this->assertSame('Elimu', __('activity.loan_purposes.education', [], 'sw'));
    }

    public function test_pending_failed_and_foreign_payments_do_not_satisfy_gate(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $fees = app(ApplicationFeePaymentService::class);
        $base = array_merge($this->quotePayload($product), ['draft_reference' => 'APP-P0-STAT']);

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'APP-P0-STAT',
            'payload' => $base,
            'saved_at' => now(),
        ]);

        foreach (['awaiting_payment', 'processing', 'pending_verification', 'failed', 'cancelled', 'expired'] as $status) {
            $payment = CustomerPayment::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'payment_type' => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount' => 10_000,
                'currency' => 'TZS',
                'status' => $status,
                'reference' => 'PAY-P0-'.$status,
                'provider_meta' => [
                    'apply_context' => [
                        'loan_product_id' => $product->id,
                        'draft_reference' => 'APP-P0-STAT',
                    ],
                ],
            ]);
            $payload = array_merge($base, [
                'application_fee' => [
                    'status' => $status === 'pending_verification' ? 'pending' : $status,
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => 10_000,
                ],
            ]);
            $this->assertFalse($fees->isSatisfiedFor($customer, $product, $payload), $status.' must not satisfy');
            $payment->delete();
        }

        $foreign = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-P0-OTHER-APP',
            'paid_at' => now(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => 'APP-OTHER-DRAFT',
                ],
            ],
        ]);
        $poisoned = array_merge($base, [
            'application_fee' => [
                'status' => 'paid',
                'payment_id' => $foreign->id,
                'reference' => $foreign->reference,
                'amount' => 10_000,
            ],
        ]);
        $this->assertFalse($fees->isSatisfiedFor($customer, $product, $poisoned));
    }
}
