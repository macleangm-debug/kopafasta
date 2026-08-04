<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\GuarantorInvitationService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerGuarantorStatusFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $suffix, array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-BGS-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Bgs'.$suffix,
            'last_name'             => 'Member',
            'phone'                 => '25570099'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'membership_status'     => 'active',
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }

    public function test_borrower_status_moves_from_pending_acceptance_to_pending_profile_after_accept(): void
    {
        $borrower = $this->makeCustomer('01');
        $guarantor = $this->makeCustomer('02');
        $product = LoanProduct::create([
            'code'              => 'IL-BGS',
            'name'              => 'Status Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor'=> true,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-BGS-01',
            'requested_amount'        => 300_000,
            'requested_tenure_months' => 6,
            'status'                  => 'awaiting_guarantor',
            'current_stage'           => 'awaiting_guarantor',
        ]);

        $record = Guarantor::create([
            'first_name'   => $guarantor->first_name,
            'last_name'    => $guarantor->last_name,
            'phone'        => $guarantor->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $record->id,
            'loan_application_id' => $application->id,
            'status'              => 'pending',
        ]);

        $invitation = GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $guarantor->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'bgs-token-01',
            'short_code'              => 'BGS001',
            'contact'                 => $guarantor->phone,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        $service = app(GuarantorInvitationService::class);

        $before = $service->borrowerInvitationStatus($invitation);
        $this->assertSame('pending_acceptance', $before['code']);
        $this->assertFalse($before['accepted']);
        $this->assertFalse($before['ready']);

        $service->approve($link->fresh());

        $after = $service->borrowerInvitationStatus($invitation->fresh(['customerGuarantor']));
        $this->assertSame('pending_profile', $after['code']);
        $this->assertTrue($after['accepted']);
        $this->assertFalse($after['ready']);
        $this->assertSame(__('borrower.apply.guarantor_status.pending_profile'), $after['label']);

        $this->actingAs($borrower->user)
            ->get(route('site.borrower.apply.guarantor-status', ['invitation_id' => $invitation->id]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('share.borrower_status_code', 'pending_profile')
            ->assertJsonPath('share.accepted', true)
            ->assertJsonPath('share.ready', false);
    }

    public function test_external_invitation_sent_status_is_invitation_sent(): void
    {
        $borrower = $this->makeCustomer('10');
        $product = LoanProduct::create([
            'code'              => 'IL-BGS-X',
            'name'              => 'External Status Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor'=> true,
        ]);

        $record = Guarantor::create([
            'first_name'   => 'Ext',
            'last_name'    => 'Guest',
            'phone'        => '255711000010',
            'relationship' => 'friend',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'  => $borrower->id,
            'guarantor_id' => $record->id,
            'status'       => 'pending',
        ]);

        $invitation = GuarantorInvitation::create([
            'customer_id'           => $borrower->id,
            'loan_product_id'       => $product->id,
            'customer_guarantor_id' => $link->id,
            'type'                  => 'external',
            'channel'               => 'link',
            'token'                 => 'bgs-ext-01',
            'short_code'            => 'BGSEXT',
            'contact'               => '255711000010',
            'invitee_name'          => 'Ext Guest',
            'status'                => 'pending',
            'expires_at'            => now()->addDays(7),
        ]);

        $status = app(GuarantorInvitationService::class)->borrowerInvitationStatus($invitation);
        $this->assertSame('invitation_sent', $status['code']);
        $this->assertFalse($status['accepted']);
        $this->assertFalse($status['ready']);
    }
}
