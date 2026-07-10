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
        $admin = User::factory()->create(['role' => 'admin']);
        app(GuarantorSupplementService::class)->request($application, $admin, 'Need another guarantor');

        $this->actingAs($customer->user)
            ->get(app(GuarantorSupplementService::class)->borrowerWizardUrl($application))
            ->assertOk()
            ->assertSee('supplementMode', false)
            ->assertSee(__('borrower.apply.steps.guarantor'), false);
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
}
