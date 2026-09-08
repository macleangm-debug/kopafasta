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

class MicroPassC12ContinuationAuthorityFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C12-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C12',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
        ]);
    }

    private function paidDraft(Customer $customer, LoanProduct $product, string $draftRef, array $form, string $stepKey = 'guarantor'): CustomerPayment
    {
        LoanApplicationDraft::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'loan_product_id' => $product->id],
            [
                'phase' => 'application',
                'step' => 1,
                'draft_reference' => $draftRef,
                'payload' => [
                    'form' => $form,
                    'step_key' => $stepKey,
                    // Intentionally omit top-level draft_reference + application_fee
                    // to prove gates resolve from the draft column / verified payment.
                ],
                'saved_at' => now(),
            ]
        );

        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C12-'.$draftRef,
            'paid_at' => now(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => $draftRef,
                    'next_step_key' => $stepKey,
                ],
            ],
        ]);
    }

    public function test_partial_payload_still_recognizes_verified_current_draft_fee(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C12',
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
        $this->paidDraft($customer, $product, 'APP-C12-AUTH', $form);

        $fees = app(ApplicationFeePaymentService::class);

        // Empty / stale client payload must not override verified DB payment.
        $this->assertTrue($fees->isSatisfiedFor($customer, $product, [
            'application_fee' => ['status' => 'due'],
        ]));
        $this->assertTrue($fees->isSatisfiedFor($customer, $product, null));
        $this->assertSame('paid', $fees->obligation($customer, $product, [])['status']);
    }

    public function test_guarantor_action_allows_progress_after_verified_fee_without_draft_fee_blob(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C12G',
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
        $this->paidDraft($customer, $product, 'APP-C12-GUAR', $form, 'guarantor');

        $response = $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.guarantor-invite'), [
                'loan_product_id' => $product->id,
                'external_first_name' => 'Asha',
                'external_last_name' => 'Guest',
                'external_phone' => '255712345678',
                'external_relationship' => 'friend',
                'external_region' => 'Dar es Salaam',
                'external_district' => 'Kinondoni',
                'external_channel' => 'whatsapp',
            ]);

        $response->assertOk();
        $this->assertTrue((bool) ($response->json('ok') ?? $response->json('share')));
        $this->assertNull(data_get($response->json(), 'errors.application_fee'));
        $this->assertStringNotContainsString(
            'application fee',
            strtolower((string) $response->getContent())
        );
    }

    public function test_stale_unpaid_citation_does_not_hide_verified_draft_payment(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'IL-C12S',
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

        $draftRef = 'APP-C12-STALE';
        $form = [
            'loan_product_id' => $product->id,
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'purpose' => 'business_expansion',
        ];

        $failed = CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'failed',
            'reference' => 'PAY-C12-FAILED',
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => $draftRef,
                ],
            ],
        ]);

        $this->paidDraft($customer, $product, $draftRef, $form);

        $fees = app(ApplicationFeePaymentService::class);
        $this->assertTrue($fees->isSatisfiedFor($customer, $product, [
            'draft_reference' => $draftRef,
            'application_fee' => [
                'status' => 'failed',
                'payment_id' => $failed->id,
                'reference' => $failed->reference,
            ],
        ]));
    }

    public function test_resume_target_not_rewound_after_verified_fee_for_product_matrix(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $fees = app(ApplicationFeePaymentService::class);
        $drafts = app(LoanApplicationDraftService::class);

        $cases = [
            'IL' => 'guarantor',
            'WL' => 'guarantor',
            'EL' => 'education_details',
        ];

        foreach ($cases as $code => $expected) {
            $product = LoanProduct::query()->where('code', $code)->where('is_active', true)->first();
            $this->assertNotNull($product, $code);

            $draftRef = 'APP-C12-'.$code;
            $form = [
                'loan_product_id' => $product->id,
                'requested_amount' => max(500_000, (int) $product->min_amount),
                'requested_tenure_months' => max(1, (int) $product->tenure_min_months),
                'purpose' => $product->fixedPurposeKey() ?: 'business_expansion',
            ];

            LoanApplicationDraft::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'loan_product_id' => $product->id],
                [
                    'phase' => 'application',
                    'step' => 0,
                    'draft_reference' => $draftRef,
                    'payload' => [
                        'form' => $form,
                        'step_key' => 'quote',
                    ],
                    'saved_at' => now(),
                ]
            );

            CustomerPayment::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'payment_type' => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount' => max(1, (int) $product->application_fee_amount),
                'currency' => 'TZS',
                'status' => 'paid',
                'reference' => 'PAY-C12-'.$code,
                'paid_at' => now(),
                'provider_meta' => [
                    'apply_context' => [
                        'loan_product_id' => $product->id,
                        'draft_reference' => $draftRef,
                        'next_step_key' => $expected,
                    ],
                ],
            ]);

            $synced = $fees->syncDraftFromVerifiedPayment($customer, $product);
            $this->assertNotNull($synced, $code);

            $formatted = $drafts->payloadForWizard($customer, $product->id);
            $this->assertSame($expected, $formatted['resume_target']['step_key'] ?? null, $code);
            $this->assertSame($expected, $formatted['step_key'] ?? null, $code);
            $this->assertSame($draftRef, $formatted['draft_reference'] ?? null, $code);
        }
    }

    public function test_locked_purpose_persisted_on_draft_save_without_client_purpose(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'EL-C12',
            'name' => 'Education Loan',
            'category' => 'education',
            'is_active' => true,
            'purpose_mode' => 'fixed',
            'fixed_purpose' => 'education',
            'interest_rate' => 0.16,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 1_000,
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
    }

    public function test_group_existing_phone_conflict_is_inline_not_name_based(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $leader = $this->borrower();
        $member = $this->borrower();
        $member->update([
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'first_name' => 'Registered',
            'last_name' => 'Member',
        ]);
        $product = LoanProduct::query()->where('code', 'GL')->where('is_active', true)->first();
        $this->assertNotNull($product);

        $response = $this->actingAs($leader->user)
            ->postJson(route('site.borrower.apply.group-member-invite'), [
                'loan_product_id' => $product->id,
                'first_name' => 'Typed',
                'last_name' => 'Name',
                'phone' => $member->phone,
                'group' => [
                    'name' => 'C12 Group',
                    'purpose' => 'business_expansion',
                    'amount_per_member' => 200_000,
                    'target_member_count' => 5,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'already_member');

        $payload = $response->json();
        $this->assertArrayNotHasKey('name', $payload);
        $message = (string) ($payload['message'] ?? '');
        $this->assertTrue(
            str_contains(strtolower($message), 'phone number')
            || str_contains(strtolower($message), 'nambari hii ya simu'),
            'Error must identify the phone-number conflict'
        );
        $this->assertStringNotContainsString('Registered', $message);
        $this->assertStringNotContainsString('Typed', $message);
    }

    public function test_asset_lending_start_does_not_flash_started_modal_copy(): void
    {
        $this->assertStringContainsString(
            "->route('site.borrower.apply'",
            file_get_contents(app_path('Http/Controllers/Site/AssetMarketplaceController.php'))
        );
        $controller = file_get_contents(app_path('Http/Controllers/Site/AssetMarketplaceController.php'));
        $this->assertStringNotContainsString("borrower.marketplace.started", $controller);
    }
}
