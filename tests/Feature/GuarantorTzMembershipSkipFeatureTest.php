<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorTzMembershipSkipFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_tanzania_guarantor_after_pin_lands_on_dashboard_not_membership_renew(): void
    {
        $this->assertFalse(MembershipService::isRequiredForCountry('TZ'));

        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-TZ-B',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'Tz',
            'phone' => '255711000111',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-BOR1',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-TZ-M',
            'name' => 'TZ Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-TZ-M01',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ]);

        $person = Guarantor::create([
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '+255700000012',
            'relationship' => 'friend',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $person->id,
            'loan_application_id' => $application->id,
            'status' => 'pending',
        ]);
        $invite = GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => null,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'tz-memb-skip-token',
            'short_code' => 'TZMS01',
            'contact' => '+255700000012',
            'invitee_name' => 'Vase Vase',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $guarantorUser = User::factory()->create([
            'role' => 'borrower',
            'phone' => '+255700000012',
            'is_active' => true,
            'preferences' => ['account_welcome_completed_at' => now()->toIso8601String()],
        ]);
        app(PinService::class)->setPin($guarantorUser, '1234');
        app(PinRecoveryChallengeService::class)->enroll($guarantorUser, [
            'mother_first_name' => 'Amina',
            'birth_village' => 'Moshi',
            'primary_school' => 'Uhuru',
        ]);
        $guarantor = Customer::create([
            'user_id' => $guarantorUser->id,
            'customer_number' => 'CU-TZ-G',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '+255700000012',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-VAS1',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
            'onboarded_at' => now(),
        ]);

        $invite->forceFill(['guarantor_customer_id' => $guarantor->id])->save();

        $this->actingAs($guarantorUser)
            ->withSession([
                'guarantor_invite_token' => $invite->token,
                'account_welcome_done' => true,
            ])
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertDontSee('membership/renew', false);

        $this->actingAs($guarantorUser)
            ->withSession([
                'guarantor_invite_token' => $invite->token,
                'account_welcome_done' => true,
            ])
            ->get(route('site.membership.renew'))
            ->assertRedirect(route('site.borrower.dashboard'));
    }
}
