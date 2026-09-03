<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\GroupLendingService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationFeeGateAuditTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-FEE-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Fee',
            'last_name' => 'Borrower',
            'phone' => '2557123490'.random_int(10, 99),
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'national_id' => '19900101123456789012',
            'nida_verification_status' => 'verified',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
            'face_verification_status' => 'verified',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => 'Samora',
            'activity_type' => 'employed',
            'income_range' => '500k_1m',
        ]);
    }

    private function product(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'code' => 'IL-FEE-'.random_int(100, 999),
            'name' => 'Individual Loan',
            'name_sw' => 'Mkopo wa Mdau',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 10_000,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function quotePayload(LoanProduct $product): array
    {
        return [
            'application_started' => true,
            'step_key' => 'quote',
            'form' => [
                'loan_product_id' => $product->id,
                'requested_amount' => 500_000,
                'requested_tenure_months' => 6,
                'purpose' => 'business',
            ],
        ];
    }

    public function test_active_products_derive_fee_from_settings_not_route_conditionals(): void
    {
        (new PublicLoanProductsSeeder)->run();

        LoanProduct::query()->where('is_active', true)->each(function (LoanProduct $product): void {
            if ((int) ($product->application_fee_amount ?? 0) <= 0) {
                $product->update(['application_fee_amount' => 10_000]);
            }
        });

        $customer = $this->borrower();
        $fees = app(ApplicationFeePaymentService::class);
        $groups = app(GroupLendingService::class);
        $matrix = [];

        foreach (LoanProduct::query()->where('is_active', true)->orderBy('code')->get() as $product) {
            $memberCount = $groups->isGroupProduct($product) ? 3 : null;
            $payload = ['form' => ['requested_amount' => (int) $product->min_amount, 'purpose' => 'business', 'requested_tenure_months' => (int) $product->tenure_min_months]];
            if ($memberCount) {
                $payload['group'] = [
                    'name' => 'Audit Group',
                    'target_member_count' => $memberCount,
                    'members' => [['role' => 'leader'], ['role' => 'member'], ['role' => 'member']],
                ];
            }

            $obligation = $fees->obligation($customer, $product, $payload);
            $matrix[$product->code] = $obligation;

            $this->assertContains($obligation['status'], ['not_applicable', 'due']);
            if ($obligation['required']) {
                $this->assertGreaterThan(0, $obligation['amount']);
                $this->assertSame($obligation['amount'], $fees->requiredAmount($customer, $product, $payload));
            }
            if ($groups->isGroupProduct($product)) {
                $this->assertSame('per_member', $obligation['basis']);
                $this->assertSame(
                    $groups->quotedApplicationFee($customer, $product, 3),
                    $obligation['amount'],
                );
            }
        }

        $this->assertArrayHasKey('IL', $matrix);
        $this->assertArrayHasKey('GL', $matrix);
        $this->assertArrayHasKey('AB', $matrix);
        $this->assertTrue($matrix['IL']['required']);
        $this->assertSame(10_000, $matrix['IL']['amount']);
        $this->assertTrue($matrix['GL']['required']);
        $this->assertSame(30_000, $matrix['GL']['amount']);
        $this->assertGreaterThanOrEqual(8, count($matrix), 'Every active catalogue product must be audited.');
    }

    public function test_individual_quote_shows_fee_and_pay_cta_in_both_locales(): void
    {
        $customer = $this->borrower();
        $product = $this->product(['code' => 'IL']);

        $html = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.apply', ['product' => $product->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('borrower.apply.product_summary.application_fee', [], 'en'), $html);
        $this->assertStringContainsString(__('borrower.apply.application_fee.pay_cta', [], 'en'), $html);
        $this->assertStringContainsString('applicationFeePayUrl', $html);
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('site.borrower.apply.application-fee.pay'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('site.borrower.payments.show'));

        $htmlSw = $this->actingAs($customer->user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.apply', ['product' => $product->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('borrower.apply.application_fee.pay_cta', [], 'sw'), $htmlSw);
        $this->assertStringContainsString(__('borrower.apply.product_summary.application_fee', [], 'sw'), $htmlSw);
    }

    public function test_save_draft_cannot_advance_past_unpaid_individual_fee(): void
    {
        $customer = $this->borrower();
        $product = $this->product();

        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'step' => 1,
                'step_key' => 'guarantor',
                'loan_product_id' => $product->id,
                'form' => $this->quotePayload($product)['form'],
            ])
            ->assertOk()
            ->assertJsonPath('step_key', 'quote');

        $draft = LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $product->id)
            ->first();

        $this->assertSame('quote', $draft?->payload['step_key'] ?? null);
        $this->assertFalse(app(ApplicationFeePaymentService::class)->isSatisfiedFor($customer, $product, $draft?->payload));
    }

    public function test_direct_resume_url_cannot_open_guarantor_while_fee_is_due(): void
    {
        $customer = $this->borrower();
        $product = $this->product();

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'APP-IL-50XV',
            'saved_at' => now(),
            'payload' => array_merge($this->quotePayload($product), ['step_key' => 'guarantor']),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $product->id,
                'resume' => 1,
                'step_key' => 'guarantor',
            ]))
            ->assertOk()
            ->assertViewHas('savedDraft', function ($draft) {
                return ($draft['step_key'] ?? null) === 'quote'
                    && ($draft['resume_target']['step_key'] ?? null) === 'quote';
            });
    }

    public function test_guarantor_lookup_is_blocked_until_fee_is_paid(): void
    {
        $customer = $this->borrower();
        $product = $this->product();

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.guarantor-lookup'), [
                'membership_no' => 'M-1',
                'phone' => '255712340000',
                'loan_product_id' => $product->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_fee');
    }

    public function test_zero_fee_product_does_not_require_payment_gate(): void
    {
        $customer = $this->borrower();
        $product = $this->product(['application_fee_amount' => 0, 'code' => 'IL0']);

        $obligation = app(ApplicationFeePaymentService::class)->obligation($customer, $product, $this->quotePayload($product));

        $this->assertSame('not_applicable', $obligation['status']);
        $this->assertTrue(app(ApplicationFeePaymentService::class)->isSatisfiedFor($customer, $product, $this->quotePayload($product)));

        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'step' => 1,
                'step_key' => 'guarantor',
                'loan_product_id' => $product->id,
                'form' => $this->quotePayload($product)['form'],
            ])
            ->assertOk()
            ->assertJsonPath('step_key', 'guarantor');
    }

    public function test_verified_payment_is_preserved_and_not_duplicated(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        $fees = app(ApplicationFeePaymentService::class);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-APP-FEE-1',
            'paid_at' => now(),
        ]);

        $payload = array_merge($this->quotePayload($product), [
            'application_fee' => ['status' => 'paid', 'reference' => $payment->reference, 'amount' => 10_000],
        ]);
        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'payload' => $payload,
            'saved_at' => now(),
        ]);

        $this->assertSame('paid', $fees->obligation($customer, $product, $payload)['status']);

        $first = $fees->openSharedGate($customer, $product, 'PAY-APP-FEE-2');
        $second = $fees->openSharedGate($customer, $product, 'PAY-APP-FEE-3');

        $this->assertSame('paid', $first['status']);
        $this->assertSame('paid', $second['status']);
        $this->assertSame(1, CustomerPayment::query()->where('customer_id', $customer->id)->where('payment_type', 'application_fee')->count());
    }

    public function test_failed_payment_cannot_bypass_the_gate(): void
    {
        $customer = $this->borrower();
        $product = $this->product();

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'failed',
            'reference' => 'PAY-APP-FEE-FAIL',
        ]);

        $obligation = app(ApplicationFeePaymentService::class)->obligation($customer, $product, $this->quotePayload($product));
        $this->assertSame('failed', $obligation['status']);
        $this->assertFalse(app(ApplicationFeePaymentService::class)->isSatisfiedFor($customer, $product, $this->quotePayload($product)));
    }

    public function test_group_fee_uses_settings_amount_times_roster(): void
    {
        $customer = $this->borrower();
        $product = $this->product([
            'code' => 'GL',
            'category' => 'group',
            'requires_guarantor' => false,
            'application_fee_amount' => 10_000,
        ]);

        $payload = [
            'group' => [
                'name' => 'Kikundi',
                'target_member_count' => 4,
                'members' => [[], [], [], []],
            ],
        ];

        $this->assertSame(40_000, app(GroupLendingService::class)->quotedApplicationFee($customer, $product, 4));
        $this->assertSame(40_000, app(ApplicationFeePaymentService::class)->requiredAmount($customer, $product, $payload));
        $this->assertSame('due', app(ApplicationFeePaymentService::class)->obligation($customer, $product, $payload)['status']);
    }

    public function test_post_approval_fee_routes_are_unchanged(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('site.borrower.application.post-approval-fees.pay'));
        $this->assertStringContainsString('payPostApprovalFees', file_get_contents(app_path('Http/Controllers/Site/BorrowerController.php')));
    }

    public function test_pay_cta_translations_exist(): void
    {
        $this->assertSame('Pay application fee', __('borrower.apply.application_fee.pay_cta', [], 'en'));
        $this->assertSame('Lipa ada ya maombi', __('borrower.apply.application_fee.pay_cta', [], 'sw'));
        $this->assertSame('Application fee paid ✓', __('borrower.apply.application_fee.paid_badge', [], 'en'));
        $this->assertSame('Ada ya maombi imelipwa ✓', __('borrower.apply.application_fee.paid_badge', [], 'sw'));
    }
}
