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

class GuarantorInviteQuoteClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $suffix, array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-GQC-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Gqc'.$suffix,
            'last_name'             => 'Member',
            'phone'                 => '25570077'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'membership_status'     => 'active',
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }

    private function loanProduct(): LoanProduct
    {
        return LoanProduct::create([
            'code'               => 'IL-GQC',
            'name'               => 'Guarantor Quote Closure',
            'is_active'          => true,
            'interest_rate'      => 0.15,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
            'repayment_cadence'  => 'monthly',
            'interest_method'    => 'reducing',
        ]);
    }

    public function test_public_invitation_shows_premium_header_and_canonical_quote_fields(): void
    {
        $borrower = $this->makeCustomer('01', [
            'first_name' => 'Asha',
            'last_name'  => 'Massawe',
        ]);
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GQC-01',
            'requested_amount'        => 800_000,
            'requested_tenure_months' => 12,
            'status'                  => 'awaiting_guarantor',
            'current_stage'           => 'awaiting_guarantor',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'type'                    => 'external',
            'channel'                 => 'sms',
            'token'                   => 'gqc-quote-token',
            'short_code'              => 'GQCQT1',
            'contact'                 => '+255700770002',
            'invitee_name'            => 'Invitee One',
            'requested_amount'        => 800_000,
            'requested_tenure_months' => 12,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        $response = $this->get(route('site.guarantor.show', 'gqc-quote-token'));

        $response->assertOk()
            ->assertSee('kf-premium-panel', false)
            ->assertSee('premium-gradient', false)
            ->assertSee('Asha Massawe', false)
            ->assertSee('APP-GQC-01', false)
            ->assertSee(__('borrower.guarantor_invite.frequency_label'), false)
            ->assertSee(__('borrower.guarantor_invite.reference_label'), false)
            ->assertDontSee(__('borrower.guarantor_invite.amount_tbd'), false)
            ->assertDontSee(__('borrower.guarantor_invite.duration_tbd'), false)
            ->assertDontSee(__('borrower.guarantor_invite.installment_tbd'), false);
    }

    public function test_internal_member_login_invitation_shows_premium_header_and_full_quote(): void
    {
        $borrower = $this->makeCustomer('02', [
            'first_name' => 'MacLean',
            'last_name'  => 'Mwaijonga',
        ]);
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GQC-02',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
            'status'                  => 'awaiting_guarantor',
            'current_stage'           => 'awaiting_guarantor',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'type'                    => 'internal',
            'channel'                 => 'whatsapp',
            'token'                   => 'gqc-member-token',
            'short_code'              => 'GQCM01',
            'contact'                 => '255700000042',
            'invitee_name'            => 'UAT Pending Guarantor',
            'requested_amount'        => 600_000,
            'requested_tenure_months' => 6,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        $response = $this->get(route('site.guarantor.show', 'gqc-member-token'));

        $response->assertOk()
            ->assertSee('kf-premium-panel', false)
            ->assertSee('APP-GQC-02', false)
            ->assertSee(__('borrower.guarantor_invite.duration_label'), false)
            ->assertSee(__('borrower.guarantor_invite.installment_label'), false)
            ->assertSee(__('borrower.guarantor_invite.frequency_label'), false)
            ->assertDontSee(__('borrower.guarantor_invite.amount_tbd'), false)
            ->assertDontSee(__('borrower.guarantor_invite.duration_tbd'), false)
            ->assertDontSee(__('borrower.guarantor_invite.installment_tbd'), false);
    }

    public function test_material_quote_change_supersedes_consent_and_blocks_ready(): void
    {
        $borrower = $this->makeCustomer('10');
        $guarantor = $this->makeCustomer('11', [
            'first_name' => 'Jamila',
            'last_name'  => 'Ally',
        ]);
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GQC-10',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'awaiting_guarantor',
            'current_stage'           => 'awaiting_guarantor',
        ]);

        $person = Guarantor::create([
            'first_name'   => $guarantor->first_name,
            'last_name'    => $guarantor->last_name,
            'phone'        => $guarantor->phone,
            'relationship' => 'member',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $person->id,
            'loan_application_id' => $application->id,
            'status'              => 'approved',
        ]);
        $invitation = GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $guarantor->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'gqc-reconfirm-token',
            'short_code'              => 'GQCRC1',
            'contact'                 => $guarantor->phone,
            'invitee_name'            => $guarantor->full_name,
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'accepted',
            'responded_at'            => now()->subDay(),
            'expires_at'              => now()->addDays(7),
        ]);

        $service = app(GuarantorInvitationService::class);
        $service->recordConsentSnapshot($invitation->fresh());

        $this->assertTrue($service->hasReadyGuarantor($application->fresh())
            || $service->borrowerInvitationStatus($invitation->fresh())['accepted']);

        $service->syncInvitationQuote($invitation->fresh(), 900_000, 12, $product->id);
        $invitation = $invitation->fresh();

        $this->assertTrue($invitation->needsQuoteReconfirmation());
        $this->assertNotEmpty($invitation->consent_history);
        $this->assertSame('approved', $link->fresh()->status);
        $this->assertFalse($service->hasReadyGuarantor($application->fresh()));
        $this->assertSame(
            'guarantor_quote_reconfirmation_required',
            $service->guarantorHoldBlocker($application->fresh())
        );

        $status = $service->borrowerInvitationStatus($invitation);
        $this->assertSame('pending_reconfirmation', $status['code']);
        $this->assertFalse($status['ready']);

        $service->reconfirmConsent($invitation->fresh());
        $invitation = $invitation->fresh();

        $this->assertFalse($invitation->needsQuoteReconfirmation());
        $this->assertSame(GuarantorInvitation::CONFIRMATION_CONFIRMED, $invitation->confirmation_status);
        $this->assertSame(900_000, (int) data_get($invitation->consent_snapshot, 'amount'));
    }
}
