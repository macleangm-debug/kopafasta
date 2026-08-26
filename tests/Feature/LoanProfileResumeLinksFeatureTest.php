<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanApplicationDraftService;
use App\Services\LoanApplicationProfileService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanProfileResumeLinksFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-RESUME-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Resume',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123480'.random_int(10, 99),
            'date_of_birth'            => now()->subYears(28)->toDateString(),
            'national_id'              => '19850101123456789012',
            'nida_verification_status' => 'verified',
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'face_verification_status' => 'verified',
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'street'                   => 'Samora',
            'activity_type'            => 'employed',
            'income_range'             => '500k_1m',
        ]);
    }

    private function individualProduct(): LoanProduct
    {
        return LoanProduct::create([
            'code'               => 'IL-RESUME-'.random_int(100, 999),
            'name'               => 'Resume Individual Loan',
            'is_active'          => true,
            'interest_rate'      => 0.15,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 1,
            'tenure_max_months'  => 12,
            'requires_guarantor' => true,
        ]);
    }

    public function test_draft_profile_edit_links_use_clean_step_keys(): void
    {
        $customer = $this->borrower();
        $product = $this->individualProduct();

        $draft = LoanApplicationDraft::create([
            'customer_id'      => $customer->id,
            'loan_product_id'   => $product->id,
            'phase'             => 'application',
            'step'              => 2,
            'draft_reference'   => 'DR-RESUME-'.random_int(1000, 9999),
            'saved_at'          => now(),
            'payload'           => [
                'application_started' => true,
                'step_key'            => 'guarantor',
                'form'                => [
                    'loan_product_id'         => $product->id,
                    'requested_amount'        => 250_000,
                    'requested_tenure_months' => 6,
                    'purpose'                 => 'business',
                ],
                'external_guarantor' => [
                    'invitee_name'   => 'Amina Guarantor',
                    'invitee_phone'  => '255712340099',
                    'invitation_url' => 'https://example.test/g/abc',
                ],
            ],
        ]);

        $profile = app(LoanApplicationProfileService::class)->forDraft($customer, $draft);

        $this->assertNotEmpty($profile['edit_quote_url']);
        $this->assertNotEmpty($profile['edit_guarantor_url']);
        $this->assertSame(1, substr_count((string) $profile['edit_quote_url'], 'step_key='));
        $this->assertSame(1, substr_count((string) $profile['edit_guarantor_url'], 'step_key='));
        $this->assertStringContainsString('step_key=quote', (string) $profile['edit_quote_url']);
        $this->assertStringContainsString('step_key=guarantor', (string) $profile['edit_guarantor_url']);
        $this->assertStringContainsString('resume=1', (string) $profile['edit_quote_url']);
        $this->assertStringContainsString('return_to=profile', (string) $profile['edit_quote_url']);
        $this->assertStringContainsString('return_to=profile', (string) $profile['edit_guarantor_url']);
    }

    public function test_continue_edit_quote_and_edit_guarantor_resume_without_server_error(): void
    {
        $customer = $this->borrower();
        // Nested arrays in activity_details (revision metadata) must not 500 the wizard.
        $customer->forceFill([
            'activity_details' => [
                'trade_type' => 'agriculture',
                'income_proof_method' => 'bank_statement',
                'profile_revision_flags' => ['nida', 'nida_docs'],
            ],
        ])->save();
        $product = $this->individualProduct();

        LoanApplicationDraft::create([
            'customer_id'      => $customer->id,
            'loan_product_id'   => $product->id,
            'phase'             => 'application',
            'step'              => 1,
            'draft_reference'   => 'DR-RESUME-OK-'.random_int(1000, 9999),
            'saved_at'          => now(),
            'payload'           => [
                'application_started' => true,
                'step_key'            => 'quote',
                'form'                => [
                    'loan_product_id'         => $product->id,
                    'requested_amount'        => 200_000,
                    'requested_tenure_months' => 3,
                    'purpose'                 => 'business',
                ],
                'external_guarantor' => [
                    'invitee_name'   => 'Amina Guarantor',
                    'invitee_phone'  => '255712340088',
                    'invitation_url' => 'https://example.test/g/def',
                ],
            ],
        ]);

        $draft = LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $product->id)
            ->firstOrFail();

        $continueUrl = app(LoanApplicationDraftService::class)->wizardApplyUrl($draft, [
            'phase'    => 'application',
            'step_key' => 'quote',
        ]);

        $this->actingAs($customer->user)
            ->get($continueUrl)
            ->assertOk();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product'  => $product->id,
                'resume'   => 1,
                'step_key' => 'quote',
            ]))
            ->assertOk();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product'  => $product->id,
                'resume'   => 1,
                'step_key' => 'guarantor',
            ]))
            ->assertOk();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loan-profile.draft', $draft))
            ->assertOk()
            ->assertSee(__('borrower.loan_profile.actions.edit_quote'), false)
            ->assertSee(__('borrower.loan_profile.actions.edit_guarantor'), false)
            ->assertSee(__('borrower.loan_profile.actions.withdraw'), false)
            ->assertSee('step_key=quote', false)
            ->assertSee('step_key=guarantor', false);
    }

    public function test_edit_guarantor_hidden_until_guarantor_added_on_draft(): void
    {
        $customer = $this->borrower();
        $product = $this->individualProduct();

        $draft = LoanApplicationDraft::create([
            'customer_id'      => $customer->id,
            'loan_product_id'   => $product->id,
            'phase'             => 'application',
            'step'              => 2,
            'draft_reference'   => 'DR-NO-G-'.random_int(1000, 9999),
            'saved_at'          => now(),
            'payload'           => [
                'application_started' => true,
                'step_key'            => 'guarantor',
                'form'                => [
                    'loan_product_id'         => $product->id,
                    'requested_amount'        => 250_000,
                    'requested_tenure_months' => 6,
                    'purpose'                 => 'business',
                ],
            ],
        ]);

        $profile = app(LoanApplicationProfileService::class)->forDraft($customer, $draft);

        $this->assertNotEmpty($profile['edit_quote_url']);
        $this->assertNull($profile['edit_guarantor_url']);
    }

    public function test_group_asset_and_asset_backed_edit_quote_step_keys(): void
    {
        $customer = $this->borrower();

        $cases = [
            ['code' => 'GL', 'category' => 'group', 'expected' => 'quote'],
            ['code' => 'AL', 'category' => 'asset', 'expected' => 'asset_tenure'],
            ['code' => 'AB', 'category' => 'secured', 'expected' => 'asset_details'],
        ];

        foreach ($cases as $case) {
            $product = LoanProduct::create([
                'code'               => $case['code'].'-RESUME-'.random_int(100, 999),
                'name'               => 'Resume '.$case['code'],
                'category'           => $case['category'],
                'is_active'          => true,
                'interest_rate'      => 0.15,
                'min_amount'         => 100_000,
                'max_amount'         => 5_000_000,
                'tenure_min_months'  => 1,
                'tenure_max_months'  => 12,
                'requires_guarantor' => false,
            ]);

            // Marketplace helper keys off product code prefix — force known codes for AL/AB/GL helpers.
            $product->forceFill(['code' => $case['code']])->save();

            $draft = LoanApplicationDraft::create([
                'customer_id'     => $customer->id,
                'loan_product_id' => $product->id,
                'phase'           => 'application',
                'step'            => 1,
                'draft_reference' => 'DR-'.$case['code'].'-'.random_int(1000, 9999),
                'saved_at'        => now(),
                'payload'         => [
                    'application_started' => true,
                    'step_key'            => $case['expected'],
                    'form'                => [
                        'loan_product_id'         => $product->id,
                        'requested_amount'        => 300_000,
                        'requested_tenure_months' => 6,
                        'purpose'                 => 'business',
                    ],
                ],
            ]);

            $profile = app(LoanApplicationProfileService::class)->forDraft($customer, $draft);

            $this->assertNotEmpty($profile['edit_quote_url'], $case['code']);
            $this->assertSame(1, substr_count((string) $profile['edit_quote_url'], 'step_key='), $case['code']);
            $this->assertStringContainsString('step_key='.$case['expected'], (string) $profile['edit_quote_url'], $case['code']);
            $this->assertNull($profile['edit_guarantor_url'], $case['code']);

            // Resume into the product-aware quote step must not 500.
            $this->actingAs($customer->user)
                ->get((string) $profile['edit_quote_url'])
                ->assertOk();
        }
    }

    public function test_inactive_product_resume_does_not_server_error(): void
    {
        $customer = $this->borrower();
        $product = $this->individualProduct();
        $product->forceFill(['is_active' => false])->save();

        $draft = LoanApplicationDraft::create([
            'customer_id'      => $customer->id,
            'loan_product_id'   => $product->id,
            'phase'             => 'application',
            'step'              => 1,
            'draft_reference'   => 'DR-INACTIVE-'.random_int(1000, 9999),
            'saved_at'          => now(),
            'payload'           => [
                'application_started' => true,
                'step_key'            => 'quote',
                'form'                => [
                    'loan_product_id'         => $product->id,
                    'requested_amount'        => 200_000,
                    'requested_tenure_months' => 3,
                    'purpose'                 => 'business',
                ],
                'external_guarantor' => [
                    'invitee_name'   => 'Amina Guarantor',
                    'invitee_phone'  => '255712340077',
                    'invitation_url' => 'https://example.test/g/inactive',
                ],
            ],
        ]);

        $profile = app(LoanApplicationProfileService::class)->forDraft($customer, $draft);

        $this->actingAs($customer->user)
            ->get((string) $profile['wizard_url'])
            ->assertOk();

        $this->actingAs($customer->user)
            ->get((string) $profile['edit_quote_url'])
            ->assertOk();

        $this->assertNotEmpty($profile['edit_guarantor_url']);
        $this->actingAs($customer->user)
            ->get((string) $profile['edit_guarantor_url'])
            ->assertOk();
    }
}
