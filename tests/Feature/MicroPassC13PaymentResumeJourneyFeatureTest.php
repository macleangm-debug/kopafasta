<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\ApplyFeeResumeService;
use App\Services\CustomerPaymentService;
use App\Services\LoanApplicationDraftService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroPassC13PaymentResumeJourneyFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C13-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C13',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function seedPaidDraft(Customer $customer, LoanProduct $product, string $draftRef, array $form, array $extra = []): CustomerPayment
    {
        LoanApplicationDraft::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'loan_product_id' => $product->id],
            [
                'phase' => 'application',
                'step' => 0,
                'draft_reference' => $draftRef,
                'asset_reservation_id' => $extra['asset_reservation_id'] ?? null,
                'payload' => array_merge([
                    'form' => $form,
                    'step_key' => 'quote',
                    'group' => $extra['group'] ?? null,
                    'asset_substep' => $extra['asset_substep'] ?? null,
                ], $extra['payload'] ?? []),
                'saved_at' => now(),
            ]
        );

        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $product->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C13-'.$draftRef,
            'paid_at' => now(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => $draftRef,
                    'next_step_key' => $extra['next_step_key'] ?? 'guarantor',
                    'return_url' => app(ApplyFeeResumeService::class)->resumeUrlAfterFee(
                        $customer,
                        $product,
                        ['form' => $form, 'draft_reference' => $draftRef],
                    ),
                    'back_url' => app(ApplyFeeResumeService::class)->cancelResumeUrl(
                        $customer,
                        $product,
                        ['form' => $form, 'draft_reference' => $draftRef],
                    ),
                ],
            ],
        ]);
    }

    public function test_il_verified_fee_http_resume_renders_guarantor_not_quote(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C13',
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
        $form = [
            'loan_product_id' => $product->id,
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'purpose' => 'business_expansion',
        ];
        $payment = $this->seedPaidDraft($customer, $product, 'APP-C13-IL', $form);

        app(CustomerPaymentService::class)->successRedirectUrl($payment);
        $url = app(ApplyFeeResumeService::class)->resumeUrlAfterFee($customer, $product, [
            'form' => $form,
            'draft_reference' => 'APP-C13-IL',
        ]);

        $this->assertStringContainsString('fee_return=paid', $url);
        $this->assertStringContainsString('step_key=guarantor', $url);

        $response = $this->actingAs($customer->user)->get($url);
        $response->assertOk();
        $response->assertViewHas('savedDraft', function ($draft) {
            return ($draft['resume_target']['step_key'] ?? null) === 'guarantor'
                && ($draft['step_key'] ?? null) === 'guarantor'
                && ($draft['resume_target']['step'] ?? null) === ($draft['step'] ?? null);
        });
    }

    public function test_queen_and_education_and_agro_paid_resume_matrix(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $cases = [
            'WL' => 'guarantor',
            'EL' => 'education_details',
            'KB' => null,
        ];

        foreach ($cases as $code => $expected) {
            $product = LoanProduct::query()->where('code', $code)->where('is_active', true)->first();
            $this->assertNotNull($product, $code);
            $form = [
                'loan_product_id' => $product->id,
                'requested_amount' => max(500_000, (int) $product->min_amount),
                'requested_tenure_months' => max(1, (int) $product->tenure_min_months),
                'purpose' => $product->fixedPurposeKey() ?: 'business_expansion',
            ];
            $next = app(ApplicationFeePaymentService::class)->nextStepAfterApplicationFee($customer, $product, ['form' => $form]);
            $this->assertNotSame('quote', $next, $code);
            if ($expected) {
                $this->assertSame($expected, $next, $code);
            }

            $this->seedPaidDraft($customer, $product, 'APP-C13-'.$code, $form, ['next_step_key' => $next]);
            $url = app(ApplyFeeResumeService::class)->resumeUrlAfterFee($customer, $product, [
                'form' => $form,
                'draft_reference' => 'APP-C13-'.$code,
            ]);

            $this->actingAs($customer->user)
                ->get($url)
                ->assertOk()
                ->assertViewHas('savedDraft', function ($draft) use ($next, $code) {
                    $this->assertSame($next, $draft['resume_target']['step_key'] ?? null, $code);
                    $this->assertSame($next, $draft['step_key'] ?? null, $code);
                    $this->assertSame($draft['resume_target']['step'] ?? null, $draft['step'] ?? null, $code);

                    return true;
                });
        }
    }

    public function test_cancel_fee_returns_to_origin_not_prequote_rewind(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();

        // Asset-Backed: collateral completed → cancel stays on asset_details substep 3, not empty collateral.
        $ab = LoanProduct::query()->where('code', 'AB')->where('is_active', true)->first();
        $this->assertNotNull($ab);
        $abForm = [
            'loan_product_id' => $ab->id,
            'customer_asset_ids' => [101],
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 6,
            'purpose' => 'working_capital',
            'asset_substep' => 3,
        ];
        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $ab->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C13-AB',
            'payload' => [
                'form' => $abForm,
                'step_key' => 'asset_details',
                'asset_substep' => 3,
            ],
            'saved_at' => now(),
        ]);

        $cancelUrl = app(ApplyFeeResumeService::class)->cancelResumeUrl($customer, $ab, [
            'form' => $abForm,
            'asset_substep' => 3,
            'draft_reference' => 'APP-C13-AB',
        ]);
        $this->assertStringContainsString('fee_return=cancel', $cancelUrl);
        $this->assertStringContainsString('step_key=asset_details', $cancelUrl);
        $this->assertStringContainsString('asset_substep=3', $cancelUrl);

        $this->actingAs($customer->user)
            ->get($cancelUrl)
            ->assertOk()
            ->assertViewHas('savedDraft', function ($draft) {
                return ($draft['step_key'] ?? null) === 'asset_details'
                    && (int) ($draft['asset_substep'] ?? 0) === 3
                    && in_array(101, $draft['form']['customer_asset_ids'] ?? [], false);
            });

        // Group: existing group identity survives cancel → Quote, not Group Setup.
        $gl = LoanProduct::query()->where('code', 'GL')->where('is_active', true)->first();
        $this->assertNotNull($gl);
        $group = [
            'name' => 'C13 Surviving Group',
            'purpose' => 'business_expansion',
            'target_member_count' => 3,
            'amount_per_member' => 200_000,
            'members' => [
                ['name' => 'Leader', 'role' => 'leader', 'phone' => $customer->phone],
                ['name' => 'M2', 'role' => 'member', 'phone' => '255700000002'],
                ['name' => 'M3', 'role' => 'member', 'phone' => '255700000003'],
            ],
        ];
        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $gl->id,
            'phase' => 'application',
            'step' => 2,
            'draft_reference' => 'APP-C13-GL',
            'payload' => [
                'form' => [
                    'loan_product_id' => $gl->id,
                    'requested_amount' => 600_000,
                    'requested_tenure_months' => 6,
                    'purpose' => 'business_expansion',
                ],
                'group' => $group,
                'step_key' => 'quote',
            ],
            'saved_at' => now(),
        ]);

        $glCancel = app(ApplyFeeResumeService::class)->cancelResumeUrl($customer, $gl, [
            'form' => ['requested_amount' => 600_000],
            'group' => $group,
            'draft_reference' => 'APP-C13-GL',
        ]);
        $this->assertStringContainsString('step_key=quote', $glCancel);

        $before = app(LoanApplicationDraftService::class)->find($customer, $gl->id);
        $this->actingAs($customer->user)->get($glCancel)->assertOk();
        $after = app(LoanApplicationDraftService::class)->find($customer, $gl->id);
        $this->assertSame('APP-C13-GL', $after?->draft_reference);
        $this->assertSame('C13 Surviving Group', $after?->payload['group']['name'] ?? null);
        $this->assertSame($before?->id, $after?->id);
    }

    public function test_locked_purpose_persisted_and_paid_education_lands_on_details(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();
        $this->assertNotNull($product);
        $product->update([
            'purpose_mode' => 'fixed',
            'fixed_purpose' => 'education',
            'requires_guarantor' => true,
            'application_fee_amount' => max(1000, (int) $product->application_fee_amount),
        ]);

        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'step' => 0,
                'step_key' => 'quote',
                'loan_product_id' => $product->id,
                'form' => [
                    'loan_product_id' => $product->id,
                    'requested_amount' => 500_000,
                    'requested_tenure_months' => 6,
                ],
            ])
            ->assertOk();

        $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
        $this->assertSame('education', $draft?->payload['form']['purpose'] ?? null);

        $this->seedPaidDraft($customer, $product, (string) $draft->draft_reference, [
            'loan_product_id' => $product->id,
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'purpose' => 'education',
        ], ['next_step_key' => 'education_details']);

        $url = app(ApplyFeeResumeService::class)->resumeUrlAfterFee($customer, $product, [
            'form' => $draft->fresh()->payload['form'] ?? [],
            'draft_reference' => $draft->draft_reference,
        ]);
        $this->assertStringContainsString('education_details', $url);
        $this->actingAs($customer->user)
            ->get($url)
            ->assertOk()
            ->assertViewHas('savedDraft', fn ($d) => ($d['step_key'] ?? null) === 'education_details');
    }

    public function test_back_to_quote_while_paid_does_not_auto_advance_without_fee_return_paid(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C13B',
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
        $form = [
            'loan_product_id' => $product->id,
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'purpose' => 'business_expansion',
        ];
        $this->seedPaidDraft($customer, $product, 'APP-C13-BACK', $form);
        app(LoanApplicationDraftService::class)->save($customer, [
            'phase' => 'application',
            'step' => 0,
            'step_key' => 'quote',
            'loan_product_id' => $product->id,
            'form' => $form,
            'application_fee' => ['status' => 'paid', 'amount' => 1000],
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $product->id,
                'resume' => 1,
                'step_key' => 'quote',
                'fee_return' => 'cancel',
            ]))
            ->assertOk()
            ->assertViewHas('savedDraft', fn ($d) => ($d['step_key'] ?? null) === 'quote');
    }

    public function test_state_transition_matrix_urls(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $resume = app(ApplyFeeResumeService::class);
        $fees = app(ApplicationFeePaymentService::class);

        foreach (['IL', 'WL', 'EL', 'KB', 'AB', 'AL', 'GL'] as $code) {
            $product = LoanProduct::query()->where('code', $code)->where('is_active', true)->first();
            if (! $product) {
                continue;
            }
            $form = [
                'loan_product_id' => $product->id,
                'requested_amount' => max(500_000, (int) $product->min_amount),
                'requested_tenure_months' => max(1, (int) $product->tenure_min_months),
                'purpose' => $product->fixedPurposeKey() ?: 'business_expansion',
            ];
            $paid = $resume->resumeUrlAfterFee($customer, $product, ['form' => $form]);
            $cancel = $resume->cancelResumeUrl($customer, $product, ['form' => $form]);
            $this->assertStringContainsString('fee_return=paid', $paid, $code);
            $this->assertStringContainsString('fee_return=cancel', $cancel, $code);
            $this->assertStringNotContainsString('step_key=quote', $paid, $code.' paid must leave quote');
            $next = $fees->nextStepAfterApplicationFee($customer, $product, ['form' => $form]);
            $this->assertStringContainsString('step_key='.$next, $paid, $code);
        }
    }

    public function test_guarantor_create_after_paid_http_and_unpaid_still_blocked(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C13G',
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
        $form = [
            'loan_product_id' => $product->id,
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'purpose' => 'business_expansion',
        ];

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C13-UNPAID',
            'payload' => ['form' => $form, 'step_key' => 'quote'],
            'saved_at' => now(),
        ]);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.guarantor-invite'), [
                'loan_product_id' => $product->id,
                'external_first_name' => 'Asha',
                'external_last_name' => 'Guest',
                'external_phone' => '255712345678',
                'external_relationship' => 'friend',
                'external_region' => 'Dar es Salaam',
                'external_district' => 'Kinondoni',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['application_fee']);

        $this->seedPaidDraft($customer, $product, 'APP-C13-PAID-G', $form, ['next_step_key' => 'guarantor']);
        app(ApplicationFeePaymentService::class)->syncDraftFromVerifiedPayment($customer, $product);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.guarantor-invite'), [
                'loan_product_id' => $product->id,
                'external_first_name' => 'Asha',
                'external_last_name' => 'Guest',
                'external_phone' => '255712345679',
                'external_relationship' => 'friend',
                'external_region' => 'Dar es Salaam',
                'external_district' => 'Kinondoni',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
