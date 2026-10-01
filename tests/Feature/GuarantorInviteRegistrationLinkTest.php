<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\GuarantorOnboardingService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorInviteRegistrationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_registration_reclaims_stale_linked_account_when_phone_matches_invite(): void
    {
        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-REG-B',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'phone' => '255711000001',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $staleUser = User::factory()->create(['role' => 'borrower', 'phone' => '255711000099']);
        app(PinService::class)->setPin($staleUser, '1234');
        $stale = Customer::create([
            'user_id' => $staleUser->id,
            'customer_number' => 'CU-REG-S',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Stale',
            'last_name' => 'Link',
            'phone' => '255711000099',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-REG',
            'name' => 'Reg Product',
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
            'application_number' => 'APP-REG-01',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ]);

        $person = Guarantor::create([
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '+255717646549',
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
            'guarantor_customer_id' => $stale->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'reg-reclaim-token',
            'short_code' => 'RECL01',
            'contact' => '+255717646549',
            'invitee_name' => 'Vase Vase',
            'status' => 'accepted',
            'responded_at' => now()->subHour(),
            'expires_at' => now()->addDays(7),
        ]);

        $newUser = User::factory()->create(['role' => 'borrower', 'phone' => '+255717646549']);
        $newCustomer = Customer::create([
            'user_id' => $newUser->id,
            'customer_number' => 'CU-REG-N',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '+255717646549',
        ]);

        app(GuarantorOnboardingService::class)->linkInvitee($invite, $newCustomer, fromTrustedSession: true);

        $invite->refresh();
        $this->assertSame((int) $newCustomer->id, (int) $invite->guarantor_customer_id);
        $this->assertSame('pending', $invite->status);
    }

    public function test_link_invitee_still_blocks_true_account_collision(): void
    {
        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-REG-B2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'Two',
            'phone' => '255711000002',
        ]);

        $ownerUser = User::factory()->create(['role' => 'borrower', 'phone' => '+255717646550']);
        $owner = Customer::create([
            'user_id' => $ownerUser->id,
            'customer_number' => 'CU-REG-O',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Owner',
            'last_name' => 'Invite',
            'phone' => '+255717646550',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-REG2',
            'name' => 'Reg Product 2',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-REG-02',
            'requested_amount' => 400_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
        ]);
        $invite = GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'guarantor_customer_id' => $owner->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'reg-block-token',
            'short_code' => 'BLOK01',
            'contact' => '+255717646550',
            'invitee_name' => 'Owner Invite',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $intruder = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-REG-I',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'phone' => '+255717646550',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(GuarantorOnboardingService::class)->linkInvitee($invite, $intruder, fromTrustedSession: true);
    }
}
