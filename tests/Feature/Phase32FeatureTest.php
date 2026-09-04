<?php

namespace Tests\Feature;

use App\Models\BorrowerRefund;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\LoanProduct;
use App\Models\LoanProductPostApprovalFee;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase32FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P32-'.$suffix,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Complete',
            'last_name' => 'Borrower',
            'phone' => '2557123470'.substr($suffix, -2),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function loanProduct(): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'IL-P32',
            'name' => 'Phase 32 Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
    }

    public function test_swahili_agreement_refunds_and_guaranteed_strings_are_available(): void
    {
        $this->assertSame(
            'Muhtasari wa ofa',
            __('borrower.agreement.offer_summary', [], 'sw')
        );
        $this->assertSame(
            'Rudisho',
            __('borrower.refunds_page.title', [], 'sw')
        );
        $this->assertSame(
            'Angalia maendeleo ya mkopo',
            __('borrower.guaranteed.view_details', [], 'sw')
        );
        $this->assertSame(
            'Hai',
            __('borrower.loans_page.loan_statuses.active', [], 'sw')
        );
    }

    public function test_offer_and_agreement_pages_use_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower('010');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P32-OFFER',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'offered_amount' => 450_000,
            'offered_tenure_months' => 10,
            'status' => 'under_review',
            'offer_status' => 'pending_borrower',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application.offer', $application))
            ->assertOk()
            ->assertSee($application->application_number, false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application.agreement', $application))
            ->assertOk()
            ->assertSee($application->application_number, false);
    }

    public function test_post_approval_fees_and_disbursement_details_use_wide_layout(): void
    {
        $customer = $this->completeBorrower('020');
        $product = $this->loanProduct();

        $template = LoanProductPostApprovalFee::create([
            'loan_product_id' => $product->id,
            'code' => 'DOC-P32',
            'name' => 'Documentation fee',
            'fee_type' => 'fixed',
            'amount' => 25_000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P32-FEES',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);

        LoanApplicationPostApprovalFee::create([
            'loan_application_id' => $application->id,
            'loan_product_post_approval_fee_id' => $template->id,
            'code' => 'DOC-P32',
            'name' => 'Documentation fee',
            'fee_type' => 'fixed',
            'configured_amount' => 25_000,
            'calculated_amount' => 25_000,
            'status' => 'pending',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application.post-approval-fees', $application))
            ->assertOk()
            ->assertSee($application->application_number, false);

        $paidApplication = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P32-DISB',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);

        LoanApplicationPostApprovalFee::create([
            'loan_application_id' => $paidApplication->id,
            'loan_product_post_approval_fee_id' => $template->id,
            'code' => 'DOC-P32',
            'name' => 'Documentation fee',
            'fee_type' => 'fixed',
            'configured_amount' => 25_000,
            'calculated_amount' => 25_000,
            'status' => 'paid',
            'amount_paid' => 25_000,
            'paid_at' => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application.disbursement-details', $paidApplication))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.disbursement_details.select_account_subtitle'), false);
    }

    public function test_refund_detail_page_shows_translated_status_label(): void
    {
        $customer = $this->completeBorrower('030');
        $product = $this->loanProduct();

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-P32-REF',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 0,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'closed',
        ]);

        $refund = BorrowerRefund::create([
            'customer_id' => $customer->id,
            'loan_id' => $loan->id,
            'reference' => 'REF-P32-001',
            'amount' => 75_000,
            'currency' => 'TZS',
            'status' => BorrowerRefund::STATUS_PENDING,
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.refund', $refund))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.refund.statuses.pending'), false)
            ->assertSee(__('borrower.payments_page.refund.payout_prompt'), false);
    }

    public function test_loans_applications_tab_shows_translated_table_headers(): void
    {
        $customer = $this->completeBorrower('040');
        $product = $this->loanProduct();

        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P32-LIST',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']))
            ->assertOk()
            ->assertSee('APP-P32-LIST', false);
    }

    public function test_guaranteed_loans_tab_shows_translated_labels_for_guarantor(): void
    {
        $borrower = $this->completeBorrower('050');
        $guarantor = $this->completeBorrower('051');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P32-GTD',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);

        $loan = Loan::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-P32-GTD',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 420_000,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'active',
            'disbursement_date' => now()->subMonth()->toDateString(),
        ]);

        $guarantorRecord = Guarantor::create([
            'first_name' => $guarantor->first_name,
            'last_name' => $guarantor->last_name,
            'phone' => $guarantor->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $guarantorRecord->id,
            'loan_application_id' => $application->id,
            'status' => 'approved',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $guarantor->id,
            'type' => 'internal',
            'channel' => 'in_app',
            'token' => 'guaranteed-token-p32',
            'short_code' => 'P32GTD',
            'contact' => $guarantor->phone,
            'status' => 'accepted',
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($guarantor->user)
            ->get(route('site.borrower.loans', ['tab' => 'guaranteed']))
            ->assertOk()
            ->assertSee('APP-P32-GTD', false)
            ->assertSee(__('borrower.loans_page.tab_guaranteed'), false);
    }
}
