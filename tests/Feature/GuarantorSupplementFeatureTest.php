<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\GuarantorSupplementService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorSupplementFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-GS-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Supp',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123480'.random_int(10, 99),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'face_verification_status' => 'pending',
            'nida_verification_status' => 'verified',
            'national_id'              => '19800101123456789012',
            'date_of_birth'            => now()->subYears(30)->toDateString(),
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'street'                   => 'Samora',
            'activity_type'            => 'business',
            'income_range'             => '500k_1m',
        ]);
    }

    private function applicationFor(Customer $customer): LoanApplication
    {
        $product = LoanProduct::create([
            'code'               => 'IL-GS-'.random_int(100, 999),
            'name'               => 'Supplement Product',
            'is_active'          => true,
            'interest_rate'      => 0.15,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 1,
            'tenure_max_months'  => 12,
            'requires_guarantor' => true,
        ]);

        return LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GS-'.random_int(1000, 9999),
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'purpose'                 => 'business',
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
            'submitted_at'            => now(),
            'screening_payload'       => [],
        ]);
    }

    public function test_admin_can_request_guarantor_supplement(): void
    {
        $customer = $this->borrower();
        $application = $this->applicationFor($customer);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.request-guarantor-supplement', $application), [
                'notes' => 'Please add a second guarantor.',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertTrue(app(GuarantorSupplementService::class)->hasOpenRequest($application));
    }

    public function test_borrower_opens_guarantor_only_wizard_when_supplement_requested(): void
    {
        $customer = $this->borrower();
        $application = $this->applicationFor($customer);
        $application->forceFill([
            'application_fee_status' => 'paid',
            'application_fee_amount' => 15_000,
            'application_fee_reference' => 'FEE-GS-PAID',
            'application_fee_paid_at' => now(),
        ])->save();
        $admin = User::factory()->create(['role' => 'admin']);
        app(GuarantorSupplementService::class)->request($application, $admin, 'Need another guarantor');

        $response = $this->actingAs($customer->user)
            ->get(app(GuarantorSupplementService::class)->borrowerWizardUrl($application))
            ->assertOk()
            ->assertSee('supplementMode:', false)
            ->assertSee(__('borrower.apply.steps.guarantor'), false);

        $html = $response->getContent();
        $this->assertTrue(
            str_contains($html, 'supplementMode: true') || str_contains($html, 'supplementMode:true'),
            'Supplement mode should be enabled in the wizard payload'
        );
        $this->assertTrue(
            str_contains($html, 'FEE-GS-PAID') || str_contains($html, 'supplement-fee:'.$application->id),
            'Application fee must remain marked paid/waived with a reference'
        );

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('paid', $application->application_fee_status);
        $this->assertDatabaseMissing('loan_application_drafts', [
            'customer_id' => $customer->id,
            'loan_product_id' => $application->loan_product_id,
        ]);
    }

    public function test_stale_supplement_link_does_not_open_payment_wizard(): void
    {
        $customer = $this->borrower();
        $application = $this->applicationFor($customer);
        $application->forceFill([
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'FEE-GS-PAID-2',
        ])->save();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $application->loan_product_id,
                'guarantor_supplement' => 1,
                'application' => $application->id,
                'resume' => 1,
                'step_key' => 'guarantor',
            ]))
            ->assertRedirect(route('site.borrower.application', $application));

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('paid', $application->application_fee_status);
    }

    public function test_profile_gate_still_blocks_incomplete_submit(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = Customer::create([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-GS-GATE-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Gate',
            'last_name'                => 'Test',
            'phone'                    => '2557123470'.random_int(10, 99),
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'face_verification_status' => 'pending',
            'nida_verification_status' => 'verified',
            'national_id'              => '19800101123456789099',
            'date_of_birth'            => now()->subYears(30)->toDateString(),
        ]);

        $product = LoanProduct::create([
            'code'              => 'IL-GATE2-'.random_int(100, 999),
            'name'              => 'Gate Product 2',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $this->actingAs($customer->user)
            ->post(route('site.borrower.apply.submit'), [
                'loan_product_id'         => $product->id,
                'requested_amount'        => 100_000,
                'requested_tenure_months' => 3,
                'purpose'                 => 'business',
                'signer_name'             => 'Gate Test',
                'signature_data'          => 'data:image/png;base64,'.base64_encode('fake'),
                'consent'                 => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_edit_guarantor_wizard_loads_current_and_save_label(): void
    {
        $customer = $this->borrower();
        $application = $this->applicationFor($customer);
        $application->forceFill([
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'FEE-GS-EDIT',
        ])->save();

        $guarantor = \App\Models\Guarantor::create([
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '+255780000342',
            'relationship' => 'friend',
        ]);
        $link = \App\Models\CustomerGuarantor::create([
            'customer_id' => $customer->id,
            'guarantor_id' => $guarantor->id,
            'loan_application_id' => $application->id,
            'status' => 'pending',
        ]);
        \App\Models\GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'customer_guarantor_id' => $link->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'gs-edit-token',
            'short_code' => 'GSEDIT',
            'contact' => '+255780000342',
            'invitee_name' => 'Vase Vase',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $html = $this->actingAs($customer->user)
            ->get(app(\App\Services\GuarantorSupplementService::class)->borrowerEditGuarantorUrl($application))
            ->assertOk()
            ->getContent();

        $this->assertTrue(
            str_contains($html, 'guarantorEditMode: true') || str_contains($html, 'guarantorEditMode:true'),
            'Edit mode must be enabled'
        );
        $this->assertStringContainsString(__('borrower.loan_profile.actions.edit_guarantor_save'), $html);
        $this->assertStringContainsString('Vase', $html);
        $this->assertStringContainsString('780000342', $html);
    }

    public function test_edit_guarantor_save_updates_and_returns_to_application_view(): void
    {
        $customer = $this->borrower();
        $application = $this->applicationFor($customer);
        $application->forceFill([
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ])->save();

        $guarantor = \App\Models\Guarantor::create([
            'first_name' => 'ROGGIE',
            'last_name' => 'Old',
            'phone' => '+255780000111',
            'relationship' => 'friend',
        ]);
        $link = \App\Models\CustomerGuarantor::create([
            'customer_id' => $customer->id,
            'guarantor_id' => $guarantor->id,
            'loan_application_id' => $application->id,
            'status' => 'pending',
        ]);
        $invite = \App\Models\GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'customer_guarantor_id' => $link->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'gs-edit-save-token',
            'short_code' => 'GSSAVE',
            'contact' => '+255780000111',
            'invitee_name' => 'ROGGIE Old',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);
        $oldToken = $invite->token;

        $this->actingAs($customer->user)
            ->post(route('site.borrower.application.edit-guarantor', $application), [
                'invitation_id' => $invite->id,
                'first_name' => 'Vase',
                'last_name' => 'Vase',
                'phone' => '0780000342',
                'relationship' => 'sibling',
            ])
            ->assertRedirect(route('site.borrower.application', $application))
            ->assertSessionHas('status');

        $invite->refresh();
        $guarantor->refresh();
        $this->assertSame('Vase Vase', $invite->invitee_name);
        $this->assertSame('+255780000342', $invite->contact);
        $this->assertNotSame($oldToken, $invite->token);
        $this->assertSame('pending', $invite->status);
        $this->assertSame('Vase', $guarantor->first_name);
        $this->assertSame('sibling', $guarantor->relationship);
        $this->assertDatabaseCount('guarantor_invitations', 1);
    }

    public function test_edit_rejected_invitation_redirects_to_application_view(): void
    {
        $customer = $this->borrower();
        $application = $this->applicationFor($customer);
        $application->forceFill([
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ])->save();

        \App\Models\GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'gs-dead-token',
            'short_code' => 'GSDEAD',
            'contact' => '+255780000999',
            'invitee_name' => 'Rejected Person',
            'status' => 'rejected',
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($customer->user)
            ->get(app(\App\Services\GuarantorSupplementService::class)->borrowerEditGuarantorUrl($application))
            ->assertRedirect(route('site.borrower.application', $application));
    }
}
