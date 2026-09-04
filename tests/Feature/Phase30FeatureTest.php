<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\KycFreshnessService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase30FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P30-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Complete',
            'last_name' => 'Borrower',
            'phone' => '2557123467'.random_int(10, 99),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_guarantor_expired_loan_servicing_and_security_strings_are_available(): void
    {
        $this->assertSame(
            'Mwaliko umeisha muda',
            __('borrower.guarantor_invite.expired_title', [], 'sw')
        );
        $this->assertSame(
            'Mkopo wako',
            __('borrower.loan_servicing.summary_title', [], 'sw')
        );
        $this->assertSame(
            'Simamia PIN yako na vifaa vinavyoaminika.',
            __('borrower.security_tab.subtitle', [], 'sw')
        );
    }

    public function test_profile_security_page_uses_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.settings'))
            ->assertOk()
            ->assertSee(__('borrower.security_tab.trusted_devices'), false)
            ->assertSee(__('borrower.security_tab.pin_hint'), false);
    }

    public function test_kyc_reconfirm_page_uses_wide_layout_when_sections_are_stale(): void
    {
        $customer = $this->completeBorrower();
        $customer->update([
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => 'Sample Street',
            'activity_type' => 'trader',
            'income_range' => '500k_1m',
            'activity_details' => ['trade_type' => 'food'],
            'profile_section_confirmed_at' => [
                'activity' => now()->subDays(120)->toIso8601String(),
                'residence' => now()->subDays(120)->toIso8601String(),
            ],
        ]);

        $this->assertNotEmpty(app(KycFreshnessService::class)->sectionsDueForRefresh($customer->fresh()));

        $this->actingAs($customer->user)
            ->get(route('site.borrower.kyc-reconfirm'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.kyc.reconfirm_title'), false);
    }

    public function test_expired_guarantor_invitation_page_uses_translated_copy(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code' => 'IL-P30-G',
            'name' => 'Guarantor Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P30-G',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'expired-token-p30',
            'short_code' => 'P30EXP',
            'contact' => '+255712345670',
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('site.guarantor.show', 'expired-token-p30'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_invite.expired_title'), false)
            ->assertSee(__('borrower.guarantor_invite.expired_message'), false);
    }

    public function test_public_guarantor_invitation_page_shows_translated_heading(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code' => 'IL-P30-P',
            'name' => 'Public Guarantor Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P30-P',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'pending-token-p30',
            'short_code' => 'P30PND',
            'contact' => '+255712345671',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('site.guarantor.show', 'pending-token-p30'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_invite.heading'), false);
    }

    public function test_loan_show_page_displays_servicing_summary_labels(): void
    {
        $customer = $this->completeBorrower();
        $product = LoanProduct::create([
            'code' => 'IL-P30-L',
            'name' => 'Servicing Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-P30-'.random_int(1000, 9999),
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 450_000,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'active',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans.show', $loan))
            ->assertOk()
            ->assertSee(__('borrower.loan_servicing.summary_title'), false)
            ->assertSee(__('borrower.loan_servicing.repayment_title'), false);
    }
}
