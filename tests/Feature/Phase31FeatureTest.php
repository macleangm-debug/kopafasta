<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase31FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P31-'.random_int(100, 999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123468'.random_int(10, 99),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_application_loan_actions_and_profile_shell_strings_are_available(): void
    {
        $this->assertSame(
            'Ombi APP-001',
            __('borrower.application.page_title', ['number' => 'APP-001'], 'sw')
        );
        $this->assertSame(
            'Salio TZS 100,000',
            __('borrower.loan_actions.outstanding_balance', ['amount' => 'TZS 100,000'], 'sw')
        );
        $this->assertSame(
            'Ukamilishaji wa wasifu',
            __('borrower.profile.completion_summary_title', [], 'sw')
        );
        $this->assertSame(
            'Dhamana zangu',
            __('borrower.profile.my_assets', [], 'sw')
        );
    }

    public function test_loan_profile_page_uses_wide_layout_and_translated_labels(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code'              => 'IL-P31-A',
            'name'              => 'Application Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P31-A',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
            'submitted_at'            => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->assertSee('max-w-3xl', false)
            ->assertSee(__('borrower.loan_profile.summary_title'), false)
            ->assertSee(__('borrower.loan_profile.label'), false)
            ->assertDontSee(__('borrower.loan_profile.standing_title'), false)
            ->assertDontSee(__('borrower.loan_profile.standing_grade'), false);
    }

    public function test_loan_restructure_and_top_up_pages_use_wide_layout(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code'              => 'IL-P31-L',
            'name'              => 'Loan Actions Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-P31-'.random_int(1000, 9999),
            'principal_amount'    => 500_000,
            'approved_amount'     => 500_000,
            'outstanding_balance' => 450_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'active',
            'disbursement_date'   => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans.restructure', $loan))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.loan_actions.restructure_title'), false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans.top-up', $loan))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.loan_actions.outstanding_balance', ['amount' => format_money($loan->outstanding_balance)]), false);
    }

    public function test_profile_assets_page_uses_wide_layout_without_inner_narrow_wrapper(): void
    {
        $customer = $this->completeBorrower();

        $response = $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'assets']))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.profile.my_assets'), false)
            ->assertSee(__('borrower.profile.add_asset'), false);

        $this->assertStringNotContainsString('max-w-3xl', $response->getContent());
    }

    public function test_internal_member_guarantor_invitation_shows_member_login_page(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code'              => 'IL-P31-G',
            'name'              => 'Member Guarantor Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P31-G',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
        ]);

        GuarantorInvitation::create([
            'customer_id'         => $customer->id,
            'loan_application_id' => $application->id,
            'type'                => 'internal',
            'channel'             => 'in_app',
            'token'               => 'internal-token-p31',
            'short_code'          => 'P31INT',
            'contact'             => '+255712345672',
            'status'              => 'pending',
            'expires_at'          => now()->addDays(7),
        ]);

        $this->get(route('site.guarantor.show', 'internal-token-p31'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_invite.member_login_title'), false)
            ->assertSee(__('borrower.guarantor_invite.member_login_button'), false);
    }

    public function test_profile_shell_completion_summary_renders_on_hub(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile'))
            ->assertOk()
            ->assertSee(__('borrower.profile.completion_hub_title'), false)
            ->assertSee(__('borrower.profile.personal'), false);
    }
}
