<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerLoansIaFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_loans_tabs_are_applications_active_guarantor_closed(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-UAT-UZ16',
            'requested_amount' => 40800000,
            'requested_tenure_months' => 12,
            'status' => 'rejected',
            'current_stage' => 'rejected',
            'rejection_reason' => 'This paragraph must not appear on the closed card.',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans', ['tab' => 'applications']))
            ->assertOk()
            ->assertSee(__('borrower.loans_page.tab_applications'), false)
            ->assertSee(__('borrower.loans_page.tab_active'), false)
            ->assertSee(__('borrower.loans_page.tab_closed'), false)
            ->assertDontSee('APP-UAT-UZ16', false)
            ->assertDontSee('A compact record of decisions', false)
            ->assertDontSee('Open View decision for the reason', false);

        $closed = $this->actingAs($customer->user)
            ->get(route('site.borrower.loans', ['tab' => 'closed']));

        $closed->assertOk()
            ->assertSee('APP-UAT-UZ16', false)
            ->assertSee(__('borrower.loan_profile.view_decision'), false)
            ->assertDontSee('This paragraph must not appear on the closed card.', false)
            ->assertDontSee('A compact record of decisions', false)
            ->assertDontSee(__('borrower.loans_page.tab_guarantor'), false);
    }

    public function test_closed_tab_keeps_loan_distinct_from_application(): void
    {
        $customer = $this->borrower();
        $product = $this->product();
        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-CL-PREV',
            'principal_amount' => 250000,
            'approved_amount' => 250000,
            'outstanding_balance' => 0,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans', ['tab' => 'closed']))
            ->assertOk()
            ->assertSee('LN-CL-PREV', false)
            ->assertSee(__('borrower.loans_page.view_details'), false);
    }

    public function test_rejection_holder_locale_and_dark_cta_tokens(): void
    {
        $this->assertSame('Download PDF', __('borrower.rejection_letter.download_pdf', [], 'en'));
        $this->assertSame('Pakua PDF', __('borrower.rejection_letter.download_pdf', [], 'sw'));

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.document-holder .text-brand', $css);
        $this->assertStringContainsString('color: var(--kf-ink) !important;', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/data-theme="dark"\] :is\([^)]*\.bg-white[^)]*\)\.text-brand/',
            $css
        );
    }

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-IA-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Ia',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'IL-IA',
            'name' => 'Individual Loan',
            'name_sw' => 'Mkopo wa Mdau',
            'is_active' => true,
            'interest_rate' => 0.19,
            'min_amount' => 100000,
            'max_amount' => 50000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 24,
        ]);
    }
}
