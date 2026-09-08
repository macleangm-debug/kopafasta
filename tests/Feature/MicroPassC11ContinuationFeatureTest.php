<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\LoanApplicationDraftService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroPassC11ContinuationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C11-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C11',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
        ]);
    }

    public function test_verified_fee_resume_target_matches_next_step_without_url_step_key(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C11',
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
        ]);

        $draftRef = 'APP-C11-CONT';
        $payload = [
            'draft_reference' => $draftRef,
            'form' => [
                'loan_product_id' => $product->id,
                'requested_amount' => 500_000,
                'requested_tenure_months' => 6,
                'purpose' => 'business_expansion',
            ],
            'step_key' => 'quote',
        ];

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => $draftRef,
            'payload' => $payload,
            'saved_at' => now(),
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C11-CONT',
            'paid_at' => now(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => $draftRef,
                    'next_step_key' => 'guarantor',
                ],
            ],
        ]);

        $fees = app(ApplicationFeePaymentService::class);
        $synced = $fees->syncDraftFromVerifiedPayment($customer, $product);
        $this->assertNotNull($synced);

        $formatted = app(LoanApplicationDraftService::class)->payloadForWizard($customer, $product->id);
        $this->assertSame('guarantor', $formatted['resume_target']['step_key'] ?? null);
        $this->assertSame('guarantor', $formatted['step_key'] ?? null);
        $this->assertSame(
            $formatted['resume_target']['step'] ?? null,
            $formatted['step'] ?? null
        );

        // Autosave without top-level draft_reference must not clamp back to Quote.
        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'step' => 1,
                'step_key' => 'guarantor',
                'loan_product_id' => $product->id,
                'form' => $payload['form'],
                'application_fee' => [
                    'status' => 'paid',
                    'payment_id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => 1000,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('step_key', 'guarantor');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $product->id,
                'resume' => 1,
            ]))
            ->assertOk()
            ->assertViewHas('savedDraft', function ($saved) {
                return ($saved['resume_target']['step_key'] ?? null) === 'guarantor'
                    && ($saved['step_key'] ?? null) === 'guarantor';
            });
    }

    public function test_active_product_continuation_matrix_after_verified_fee(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $fees = app(ApplicationFeePaymentService::class);
        $drafts = app(LoanApplicationDraftService::class);

        $expectations = [
            'IL' => 'guarantor',
            'WL' => 'guarantor',
            'EL' => 'education_details',
            'KB' => null,
            'AB' => null,
            'AL' => null,
            'GL' => null,
        ];

        foreach ($expectations as $code => $expectedNext) {
            $product = LoanProduct::query()->where('code', $code)->where('is_active', true)->first();
            if (! $product) {
                continue;
            }

            $draftRef = 'APP-C11-'.$code;
            $form = [
                'loan_product_id' => $product->id,
                'requested_amount' => max(500_000, (int) $product->min_amount),
                'requested_tenure_months' => max(1, (int) $product->tenure_min_months),
                'purpose' => $product->fixedPurposeKey() ?: 'business_expansion',
            ];

            $next = $fees->nextStepAfterApplicationFee($customer, $product, [
                'form' => $form,
                'draft_reference' => $draftRef,
            ]);
            $this->assertNotSame('quote', $next, $code.' next step must leave Quote');
            if ($expectedNext) {
                $this->assertSame($expectedNext, $next, $code);
            }

            // Full resume handshake for IL spine products with complete quote fields.
            if (! in_array($code, ['IL', 'WL', 'EL', 'KB'], true)) {
                continue;
            }

            LoanApplicationDraft::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'loan_product_id' => $product->id],
                [
                    'phase' => 'application',
                    'step' => 0,
                    'draft_reference' => $draftRef,
                    'payload' => [
                        'draft_reference' => $draftRef,
                        'form' => $form,
                        'step_key' => 'quote',
                    ],
                    'saved_at' => now(),
                ]
            );

            $payment = CustomerPayment::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'payment_type' => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount' => max(1, (int) quoted_application_fee($customer, $product)),
                'currency' => 'TZS',
                'status' => 'paid',
                'reference' => 'PAY-C11-'.$code,
                'paid_at' => now(),
                'provider_meta' => [
                    'apply_context' => [
                        'loan_product_id' => $product->id,
                        'draft_reference' => $draftRef,
                        'next_step_key' => $next,
                    ],
                ],
            ]);

            $drafts->saveApplicationFee($customer, $product->id, [
                'status' => 'paid',
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => (int) $payment->amount,
                'paid_at' => now()->toIso8601String(),
            ]);

            $fees->syncDraftFromVerifiedPayment($customer, $product);
            $formatted = $drafts->payloadForWizard($customer, $product->id);
            $this->assertSame($next, $formatted['resume_target']['step_key'] ?? null, $code.' resume_target');
            $this->assertSame($next, $formatted['step_key'] ?? null, $code.' step_key');
            $this->assertSame(
                $formatted['resume_target']['step'] ?? null,
                $formatted['step'] ?? null,
                $code.' progress/body index agreement'
            );
        }
    }

    public function test_switch_to_bank_and_proof_upload_do_not_flash_got_it_modals(): void
    {
        $customer = $this->borrower();
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'reference' => 'PAY-C11-BANK',
        ]);

        $this->actingAs($customer->user)
            ->post(route('site.borrower.payments.switch-bank', $payment))
            ->assertRedirect(route('site.borrower.payments.show', $payment))
            ->assertSessionMissing('status');
    }
}
