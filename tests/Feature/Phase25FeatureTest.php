<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\DisplayedRateService;
use App\Services\PinService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase25FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-P25-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Complete',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123461'.random_int(10, 99),
            'email'                    => 'complete.p25@example.com',
            'date_of_birth'            => '1990-05-15',
            'gender'                   => 'female',
            'national_id'              => '19900515-12345-67890-12',
            'nida_verification_status' => 'verified',
            'nida_verified_at'         => now(),
            'identity_locked'          => true,
            'face_verification_status' => 'verified',
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'activity_type'            => 'employed',
            'income_range'             => '500k_1m',
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'street'                   => 'Mikocheni A',
            'nok_name'                 => 'Jane Doe',
            'nok_phone'                => '255712346199',
            'nok_relationship'         => 'spouse',
        ]);
    }

    public function test_borrower_disclosure_lines_split_bot_and_internal_fees(): void
    {
        $product = LoanProduct::create([
            'code'                    => 'IL-P25-'.random_int(100, 999),
            'name'                    => 'BOT Disclosure Product',
            'is_active'               => true,
            'interest_rate'           => 0.045,
            'bot_regulated_rate'      => 0.035,
            'processing_fee_rate'     => 0.005,
            'service_fee_rate'        => 0.003,
            'administration_fee_rate' => 0.002,
            'min_amount'              => 100_000,
            'max_amount'              => 5_000_000,
            'tenure_min_months'       => 3,
            'tenure_max_months'       => 24,
        ]);

        $lines = app(DisplayedRateService::class)->borrowerDisclosureLines($product);

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('BOT', $lines[0]);
        $this->assertStringContainsString('Internal fees', $lines[1]);
        $this->assertStringContainsString('Monthly rate to borrower', $lines[2]);
    }

    public function test_wizard_payload_includes_rate_disclosure(): void
    {
        $product = LoanProduct::create([
            'code'               => 'IL-P25-PAYLOAD',
            'name'               => 'Payload Product',
            'is_active'          => true,
            'bot_regulated_rate' => 0.035,
            'interest_rate'      => 0.045,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        $payload = loan_product_wizard_payload($product);

        $this->assertIsArray($payload['rate_disclosure'] ?? null);
        $this->assertNotEmpty($payload['rate_disclosure']);
    }

    public function test_marketplace_wizard_step_plan_uses_asset_tenure(): void
    {
        $customer = $this->completeBorrower();

        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.12,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $stepKeys = collect(app(SmartLoanApplicationWizardService::class)
            ->borrowerStepPlan($customer, $product))
            ->pluck('key')
            ->all();

        $this->assertSame(
            ['asset_tenure', 'application_fee', 'review', 'signature', 'submit'],
            $stepKeys
        );
    }

    public function test_asset_backed_wizard_step_plan_uses_asset_details(): void
    {
        $customer = $this->completeBorrower();

        $product = LoanProduct::create([
            'code'              => 'AB',
            'name'              => 'Asset Backed Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $stepKeys = collect(app(SmartLoanApplicationWizardService::class)
            ->borrowerStepPlan($customer, $product))
            ->pluck('key')
            ->all();

        $this->assertSame(
            ['asset_details', 'application_fee', 'review', 'signature', 'submit'],
            $stepKeys
        );
    }

    public function test_swahili_disbursement_loan_servicing_and_policy_strings_are_available(): void
    {
        $this->assertSame(
            'Thibitisha akaunti ya malipo',
            __('borrower.disbursement_details.page_title', [], 'sw')
        );
        $this->assertSame(
            'Muhtasari wa mkopo',
            __('borrower.loan_servicing.summary_title', [], 'sw')
        );
        $this->assertStringContainsString(
            'mikopo',
            __('borrower.policy.max_active_loans', ['max' => 1], 'sw')
        );
        $this->assertStringContainsString(
            'BOT',
            __('borrower.rate_disclosure.bot_regulated', ['rate' => '3.5%', 'max' => '3.5%'], 'sw')
        );
    }

    public function test_apply_wizard_shows_rate_disclosure_panel(): void
    {
        $customer = $this->completeBorrower();

        LoanProduct::create([
            'code'               => 'IL-P25-APPLY',
            'name'               => 'Apply Disclosure Product',
            'is_active'          => true,
            'bot_regulated_rate' => 0.035,
            'processing_fee_rate'=> 0.01,
            'interest_rate'      => 0.045,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply'))
            ->assertOk()
            ->assertSee(__('borrower.rate_disclosure.title'), false)
            ->assertSee(__('borrower.rate_disclosure.footnote'), false);
    }

    public function test_referrals_and_membership_use_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->followingRedirects()
            ->get(route('site.borrower.referrals'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'membership']))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }
}
