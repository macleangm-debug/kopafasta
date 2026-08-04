<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\GuarantorInvitationService;
use App\Services\PinService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanWizardSpineFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-WZ-'.random_int(1000, 9999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Upendo',
            'last_name'             => 'Ketto',
            'phone'                 => '2557139900'.random_int(10, 99),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
            'member_no'             => 'KPF-TZ-UP'.random_int(1000, 9999),
        ]);
    }

    public function test_artisans_and_individual_share_four_step_spine_without_loan_details_step(): void
    {
        $customer = $this->borrower();

        $individual = LoanProduct::create([
            'code'              => 'IL',
            'name'              => 'Individual Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor'=> true,
        ]);

        $artisans = LoanProduct::create([
            'code'              => 'FC',
            'name'              => 'Artisans',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor'=> true,
        ]);

        $wizard = app(SmartLoanApplicationWizardService::class);
        $ilKeys = collect($wizard->borrowerStepPlan($customer, $individual, 500_000))->pluck('key')->all();
        $fcKeys = collect($wizard->borrowerStepPlan($customer, $artisans, 500_000))->pluck('key')->all();

        $this->assertSame(['quote', 'guarantor', 'review', 'submit'], $ilKeys);
        $this->assertSame($ilKeys, $fcKeys);
        $this->assertNotContains('product_questions', $fcKeys);
    }

    public function test_previous_guarantors_dedupe_same_person_across_links(): void
    {
        $borrower = $this->borrower();
        $person = Guarantor::create([
            'first_name' => 'Jack',
            'last_name'  => 'Jimmy',
            'phone'      => '255714111222',
            'email'      => 'jack.jimmy@example.com',
        ]);

        $first = CustomerGuarantor::create([
            'customer_id'  => $borrower->id,
            'guarantor_id' => $person->id,
            'status'       => 'accepted',
        ]);
        $second = CustomerGuarantor::create([
            'customer_id'  => $borrower->id,
            'guarantor_id' => $person->id,
            'status'       => 'accepted',
        ]);

        GuarantorInvitation::create([
            'customer_id'            => $borrower->id,
            'customer_guarantor_id'  => $first->id,
            'type'                   => 'external',
            'token'                  => 'tok-a-'.random_int(1000, 9999),
            'invitee_name'           => 'Jack Jimmy',
            'contact'                => '255714111222',
            'status'                 => 'accepted',
            'expires_at'             => now()->addDays(7),
        ]);
        GuarantorInvitation::create([
            'customer_id'            => $borrower->id,
            'customer_guarantor_id'  => $second->id,
            'type'                   => 'external',
            'token'                  => 'tok-b-'.random_int(1000, 9999),
            'invitee_name'           => 'Jack Jimmy',
            'contact'                => '0714111222',
            'status'                 => 'accepted',
            'expires_at'             => now()->addDays(7),
        ]);

        $items = app(GuarantorInvitationService::class)->previousGuarantorsForBorrower($borrower);
        $jack = collect($items)->filter(fn ($i) => str_contains($i['label'], 'Jack'));

        $this->assertCount(1, $jack);
    }

    public function test_internal_guarantor_lookup_does_not_require_name(): void
    {
        $borrower = $this->borrower();
        $member = Customer::create([
            'customer_number'       => 'CU-GZ-'.random_int(1000, 9999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Valid',
            'last_name'             => 'Member',
            'phone'                 => '255715333444',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
            'member_no'             => 'KPF-TZ-VM'.random_int(1000, 9999),
        ]);

        $result = app(GuarantorInvitationService::class)->verifyInternalMember(
            $borrower,
            $member->member_no,
            '0715333444',
            '',
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('Valid Member', $result['name']);
    }
}
