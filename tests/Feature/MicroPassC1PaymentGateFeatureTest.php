<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ApplicationFeePaymentService;
use App\Services\LoanApplicationDraftService;
use App\Services\SmartLoanApplicationWizardService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroPassC1PaymentGateFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-C1-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C1',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    private function product(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'code' => 'IL-C1-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.19,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 1_000,
        ], $overrides));
    }

    private function quotePayload(LoanProduct $product, string $draftRef = 'APP-C1'): array
    {
        return [
            'draft_reference' => $draftRef,
            'form' => [
                'loan_product_id' => $product->id,
                'requested_amount' => 500_000,
                'requested_tenure_months' => 6,
                'purpose' => 'business_expansion',
            ],
            'step_key' => 'quote',
        ];
    }

    public function test_attributed_affiliate_does_not_silently_shrink_fresh_fee(): void
    {
        $affiliate = Vendor::create([
            'vendor_number' => 'AFF-C1-'.random_int(100, 999),
            'name' => 'Kitonga Style',
            'category' => 'affiliate',
            'status' => 'active',
            'affiliate_code' => 'KITONGAC1',
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
        ]);

        $customer = $this->borrower(['affiliate_vendor_id' => $affiliate->id]);
        $product = $this->product();

        $this->assertSame(1000, quoted_application_fee($customer, $product));

        $quote = app(ApplicationFeePaymentService::class)->quote($customer, $product);
        $this->assertSame(0.0, (float) $quote['affiliate_discount']);
        $this->assertSame(1000.0, (float) $quote['cash_due']);
        $this->assertSame(1000.0, (float) $quote['base']);
    }

    public function test_open_payment_with_stale_silent_discount_is_restored_to_canonical(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $fees = app(ApplicationFeePaymentService::class);

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C1-STALE',
            'payload' => $this->quotePayload($product, 'APP-C1-STALE'),
            'saved_at' => now(),
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 900,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-C1-STALE',
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => 'APP-C1-STALE',
                    'gross_amount' => 900,
                ],
                'pricing' => [
                    'gross' => 900,
                    'affiliate_discount' => 100,
                    'promo_discount' => 0,
                ],
            ],
        ]);

        $state = $fees->processMobileMoney($customer, $product, 'PAY-C1-IGNORED');
        $this->assertSame('PAY-C1-STALE', $state['reference']);
        $this->assertSame(1000, (int) $state['amount']);
        $this->assertSame(1000, (int) CustomerPayment::query()->where('reference', 'PAY-C1-STALE')->value('amount'));
    }

    public function test_new_draft_does_not_inherit_prior_paid_fee(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $fees = app(ApplicationFeePaymentService::class);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C1-OLD',
            'paid_at' => now()->subDay(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => 'APP-C1-OLD',
                ],
            ],
        ]);

        $newPayload = $this->quotePayload($product, 'APP-C1-NEW');
        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C1-NEW',
            'payload' => $newPayload,
            'saved_at' => now(),
        ]);

        $this->assertSame('due', $fees->obligation($customer, $product, $newPayload)['status']);
        $this->assertFalse($fees->isSatisfiedFor($customer, $product, $newPayload));
    }

    public function test_queens_and_agro_english_names_and_continuation_plans(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();

        $wl = LoanProduct::query()->where('code', 'WL')->firstOrFail();
        $kb = LoanProduct::query()->where('code', 'KB')->firstOrFail();
        $el = LoanProduct::query()->where('code', 'EL')->firstOrFail();

        $this->assertSame("Queen's Loan", $wl->name);
        $this->assertSame('Mkopo wa Malkia', $wl->name_sw);
        $this->assertSame('Agro Loan', $kb->name);
        $this->assertSame('Mkopo wa Kilimo', $kb->name_sw);

        $fees = app(ApplicationFeePaymentService::class);
        $wizard = app(SmartLoanApplicationWizardService::class);

        $wlPlan = collect($wizard->borrowerStepPlan($customer, $wl, 500_000))->pluck('key')->all();
        $this->assertContains('quote', $wlPlan);
        $this->assertContains('guarantor', $wlPlan);
        $this->assertSame('guarantor', $fees->nextStepAfterApplicationFee($customer, $wl, [
            'form' => ['requested_amount' => 500_000],
        ]));

        $this->assertSame('education_details', $fees->nextStepAfterApplicationFee($customer, $el, [
            'form' => ['requested_amount' => 500_000],
        ]));

        $kbNext = $fees->nextStepAfterApplicationFee($customer, $kb, [
            'form' => ['requested_amount' => 500_000],
        ]);
        $this->assertNotSame('quote', $kbNext);
    }

    public function test_cancel_resume_to_quote_does_not_satisfy_gate(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $fees = app(ApplicationFeePaymentService::class);

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C1-CANCEL',
            'payload' => array_merge($this->quotePayload($product, 'APP-C1-CANCEL'), [
                'application_fee' => [
                    'status' => 'processing',
                    'reference' => 'PAY-C1-OPEN',
                    'amount' => 1000,
                ],
            ]),
            'saved_at' => now(),
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-C1-OPEN',
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => 'APP-C1-CANCEL',
                    'back_url' => route('site.borrower.apply', [
                        'product' => $product->id,
                        'resume' => 1,
                        'step_key' => 'quote',
                    ]),
                ],
            ],
        ]);

        $payload = app(LoanApplicationDraftService::class)->payloadForWizard($customer, $product->id);
        $this->assertFalse($fees->isSatisfiedFor($customer, $product, $payload ?? []));
        $this->assertSame('quote', $payload['resume_target']['step_key'] ?? null);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $product->id,
                'resume' => 1,
                'step_key' => 'quote',
            ]))
            ->assertOk()
            ->assertViewHas('savedDraft', function ($saved) {
                return ($saved['resume_target']['step_key'] ?? null) === 'quote'
                    && ($saved['step_key'] ?? null) !== 'guarantor';
            });
    }
}
