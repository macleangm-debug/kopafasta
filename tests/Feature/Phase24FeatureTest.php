<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase24FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-P24-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Complete',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123460'.random_int(10, 99),
            'email'                    => 'complete.p24@example.com',
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
            'nok_phone'                => '255712346099',
            'nok_relationship'         => 'spouse',
        ]);
    }

    public function test_wizard_step_plan_excludes_completed_profile_sections(): void
    {
        $customer = $this->completeBorrower();

        $product = LoanProduct::create([
            'code'              => 'IL-P24-'.random_int(100, 999),
            'name'              => 'Individual Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $stepKeys = collect(app(SmartLoanApplicationWizardService::class)
            ->borrowerStepPlan($customer, $product, 500_000))
            ->pluck('key')
            ->all();

        foreach (['personal', 'residence', 'kin', 'activity', 'income', 'nida', 'face'] as $profileKey) {
            $this->assertNotContains($profileKey, $stepKeys, "Profile step {$profileKey} should not appear in apply wizard.");
        }

        // Application fee is an in-wizard payment gate (not numbered); signature lives on profile.
        $this->assertSame(['quote', 'review', 'submit'], $stepKeys);
        $this->assertNotContains('application_fee', $stepKeys);
        $this->assertNotContains('signature', $stepKeys);
    }

    public function test_swahili_payment_and_notification_strings_are_available(): void
    {
        $this->assertSame(
            'Akaunti za malipo',
            __('borrower.payment_details.page_title', [], 'sw')
        );
        $this->assertSame(
            'Arifa',
            __('borrower.notifications.page_title', [], 'sw')
        );
        $this->assertSame(
            'Weka zote kama zimesomwa',
            __('borrower.notifications.mark_all_read', [], 'sw')
        );
    }

    public function test_swahili_nida_mismatch_warnings_include_remaining_attempts(): void
    {
        $warning = __('borrower.nida.mismatch_warning_2', ['remaining' => 1], 'sw');

        $this->assertStringContainsString('1', $warning);
        $this->assertStringContainsString('udanganyifu', $warning);
    }

    public function test_borrower_marketplace_uses_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.marketplace'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }

    public function test_borrower_notifications_use_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.notifications'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.notifications.page_title'), false);
    }

    public function test_borrower_payment_profile_uses_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'payment']))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }
}
