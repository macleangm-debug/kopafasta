<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDisbursementAccount;
use App\Models\ProfileSectionDefinition;
use App\Models\User;
use App\Services\ApplicationRequirementsService;
use App\Services\PinService;
use App\Services\ProfileSectionBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-PAY-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Payment',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123480'.random_int(10, 99),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'nida_verification_status' => 'verified',
            'face_verification_status' => 'pending',
            'date_of_birth'            => now()->subYears(28)->toDateString(),
            'national_id'              => '19900101123456789012',
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'street'                   => 'Samora',
            'activity_type'            => 'trader',
            'income_range'             => '500k_1m',
            'activity_details'         => ['trade_type' => 'food'],
            'nok_first_name'           => 'Next',
            'nok_last_name'            => 'Kin',
            'nok_name'                 => 'Next Kin',
            'nok_relationship'         => 'spouse',
            'nok_phone'                => '255712348099',
            'nok_region'               => 'Dar es Salaam',
            'nok_district'             => 'Kinondoni',
            'nok_street'               => 'Kin Street',
        ]);
    }

    public function test_payment_is_required_before_loan_by_default(): void
    {
        $this->assertTrue(app(ProfileSectionBuilderService::class)->paymentRequiredBeforeLoan());

        $customer = $this->borrower();
        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
        $payment = collect($checklist['items'])->firstWhere('key', 'payment');

        $this->assertNotNull($payment);
        $this->assertFalse($payment['complete']);
        $this->assertFalse($checklist['can_apply']);
    }

    public function test_admin_can_disable_payment_before_loan_requirement(): void
    {
        ProfileSectionDefinition::create([
            'key'                  => 'payment',
            'icon'                 => '💳',
            'name_en'              => 'Payment account',
            'name_sw'              => 'Akaunti ya malipo',
            'is_required'          => false,
            'required_before_loan' => false,
            'is_active'            => true,
            'display_order'        => 10,
            'input_type'           => 'section_link',
            'metadata'             => ['maps_to' => 'payment'],
        ]);

        $this->assertFalse(app(ProfileSectionBuilderService::class)->paymentRequiredBeforeLoan());
    }

    public function test_borrower_can_set_default_payment_account(): void
    {
        $customer = $this->borrower();
        $first = CustomerDisbursementAccount::create([
            'customer_id'     => $customer->id,
            'type'            => 'mobile_money',
            'account_name'    => 'Payment Borrower',
            'mobile_provider' => 'mpesa',
            'mobile_number'   => '255712348011',
            'is_default'      => true,
        ]);
        $second = CustomerDisbursementAccount::create([
            'customer_id'     => $customer->id,
            'type'            => 'bank',
            'account_name'    => 'Payment Borrower',
            'bank_name'       => 'CRDB',
            'account_number'  => '1234567890',
            'is_default'      => false,
        ]);

        $this->actingAs($customer->user)
            ->post(route('site.borrower.profile.payment-accounts.default', $second))
            ->assertRedirect(route('site.borrower.profile', ['section' => 'payment']));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_payment_profile_page_shows_type_first_add_flow(): void
    {
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'payment']))
            ->assertOk()
            ->assertSee(__('borrower.payment_details.choose_type_title'), false)
            ->assertSee(__('borrower.payment_details.method_mobile'), false)
            ->assertSee(__('borrower.payment_details.method_bank'), false);
    }

    public function test_borrower_can_add_mobile_money_payment_account_with_return_url(): void
    {
        $customer = $this->borrower();
        $return = route('site.borrower.apply', [
            'product'  => 6,
            'resume'   => 1,
            'step_key' => 'submit',
        ]);

        $this->actingAs($customer->user)
            ->put(route('site.borrower.profile.update', ['section' => 'payment', 'return' => $return]), [
                'type'            => 'mobile_money',
                'mobile_provider' => 'mpesa',
                'mobile_number'   => '0712345678',
                'account_name'    => 'Payment Borrower',
                'return'          => $return,
            ])
            ->assertRedirect($return)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('customer_disbursement_accounts', [
            'customer_id'     => $customer->id,
            'type'            => 'mobile_money',
            'mobile_provider' => 'mpesa',
            'mobile_number'   => '255712345678',
            'is_default'      => true,
        ]);
    }

    public function test_borrower_can_add_bank_payment_account(): void
    {
        $customer = $this->borrower();

        $this->actingAs($customer->user)
            ->put(route('site.borrower.profile.update', ['section' => 'payment']), [
                'type'           => 'bank',
                'bank_name'      => 'CRDB',
                'account_number' => '1234567890',
                'bank_branch'    => 'Kariakoo',
                'account_name'   => 'Payment Borrower',
            ])
            ->assertRedirect(route('site.borrower.profile', ['section' => 'payment']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('customer_disbursement_accounts', [
            'customer_id'    => $customer->id,
            'type'           => 'bank',
            'bank_name'      => 'CRDB',
            'account_number' => '1234567890',
            'is_default'     => true,
        ]);
    }
}
