<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use App\Services\CrbCreditCheckService;
use App\Services\GuarantorOnboardingService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorHoldReleaseFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_complete_guarantor_releases_the_hold_without_a_manual_status_change(): void
    {
        $branch = Branch::create([
            'code' => 'REL'.random_int(10, 99),
            'name' => 'Release Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'REL-'.random_int(100, 999),
            'name' => 'Release Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $borrower = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-REL-B-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Steward',
            'last_name' => 'Hold',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);
        $guarantor = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-REL-G-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Kasimu',
            'last_name' => 'Hold',
            'phone' => '25576'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-REL-'.random_int(1000, 9999),
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now(),
        ]);
        $contact = Guarantor::create([
            'first_name' => 'Kasimu',
            'last_name' => 'Hold',
            'phone' => $guarantor->phone,
            'relationship' => 'sibling',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $application->id,
            'status' => 'approved',
        ]);
        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'guarantor_customer_id' => $guarantor->id,
            'type' => 'external',
            'status' => 'accepted',
            'contact' => $guarantor->phone,
            'invitee_name' => 'Kasimu Hold',
            'token' => 'tok-rel-'.random_int(10000, 99999),
        ]);

        $this->mock(ProfileCompletionService::class, function ($mock): void {
            $mock->shouldReceive('isFullyComplete')->andReturn(true);
        });
        $this->mock(GuarantorOnboardingService::class, function ($mock): void {
            $mock->shouldReceive('guarantorProfileStatus')->andReturn([
                'met' => true,
                'percent' => 100,
                'checklist' => ['can_apply' => true, 'items' => []],
                'next_url' => null,
            ]);
        });
        $this->mock(CapacityAutoRejectService::class, function ($mock): void {
            $mock->shouldReceive('evaluateAndPark')->andReturn(null);
        });
        $this->mock(CrbCreditCheckService::class, function ($mock): void {
            $mock->shouldReceive('pullAndAttachAfterCapacityPass')->andReturn(['skipped' => true]);
        });

        $guarantor->update(['district' => 'Ilala']);

        $fresh = $application->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('screening', $fresh->current_stage);
    }

    public function test_residence_letter_follows_the_kyc_setting_instead_of_a_silent_bypass(): void
    {
        \App\Models\Setting::setMany([
            'kyc.require_address_proof' => false,
            'kyc.require_residence_letter' => false,
        ]);
        $this->assertFalse(app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter());

        \App\Models\Setting::setMany([
            'kyc.require_address_proof' => true,
            'kyc.require_residence_letter' => true,
        ]);
        $this->assertTrue(app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter());
    }
}
