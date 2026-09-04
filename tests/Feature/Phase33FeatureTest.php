<?php

namespace Tests\Feature;

use App\Models\BorrowerRefund;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\ApplicationOfferService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase33FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P33-'.$suffix,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Complete',
            'last_name' => 'Borrower',
            'phone' => '2557123471'.substr($suffix, -2),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function loanProduct(string $code = 'IL-P33', string $name = 'Phase 33 Product'): LoanProduct
    {
        return LoanProduct::create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
    }

    public function test_swahili_offer_letter_asset_conversion_and_guaranteed_strings_are_available(): void
    {
        $this->assertSame(
            'Mkopo ulioidhinishwa',
            __('borrower.offer_letter.pdf.facility_heading', [], 'sw')
        );
        $this->assertSame(
            'Bidhaa inayopendekezwa',
            __('borrower.offer.suggested_product', [], 'sw')
        );
        $this->assertSame(
            'Imechelewa',
            __('borrower.guaranteed.installment_statuses.overdue', [], 'sw')
        );
        $this->assertSame(
            'Rudisho',
            __('borrower.payments_page.refund.title', [], 'sw')
        );
    }

    public function test_asset_conversion_page_uses_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower('010');
        $standard = $this->loanProduct('IL-P33-STD', 'Standard loan');
        $asset = $this->loanProduct('IL-P33-AST', 'Asset-backed loan');

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $standard->id,
            'alternative_loan_product_id' => $asset->id,
            'application_number' => 'APP-P33-CONV',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'under_review',
            'recommendation_type' => ApplicationOfferService::RECOMMEND_ASSET,
            'offer_status' => 'pending_asset_conversion',
            'application_fee_amount' => 25_000,
            'application_fee_status' => 'paid',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application.asset-conversion', $application))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.offer.asset_conversion_intro'), false)
            ->assertSee(__('borrower.offer.suggested_product'), false);
    }

    public function test_guaranteed_loan_detail_uses_loan_profile_layout_and_translated_status_labels(): void
    {
        $borrower = $this->completeBorrower('020');
        $guarantor = $this->completeBorrower('021');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P33-GTD',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
            'offer_status' => 'accepted',
        ]);

        $loan = Loan::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-P33-GTD',
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 420_000,
            'interest_rate' => 0.15,
            'tenure_months' => 12,
            'status' => 'active',
            'disbursement_date' => now()->subMonth()->toDateString(),
        ]);

        RepaymentSchedule::create([
            'loan_id' => $loan->id,
            'installment_no' => 1,
            'due_date' => now()->subDays(5)->toDateString(),
            'principal_due' => 40_000,
            'interest_due' => 5_000,
            'total_due' => 45_000,
            'amount_paid' => 0,
            'status' => 'pending',
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
            'token' => 'guaranteed-token-p33',
            'short_code' => 'P33GTD',
            'contact' => $guarantor->phone,
            'status' => 'accepted',
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($guarantor->user)
            ->get(route('site.borrower.guaranteed.show', $link))
            ->assertOk()
            ->assertSee('max-w-3xl', false)
            ->assertSee('APP-P33-GTD', false)
            ->assertSee(__('borrower.loan_profile.summary_title'), false);
    }

    public function test_guarantor_request_detail_uses_loan_profile_layout(): void
    {
        $borrower = $this->completeBorrower('030');
        $guarantor = $this->completeBorrower('031');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P33-GRQ',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
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
            'status' => 'pending',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $guarantor->id,
            'type' => 'internal',
            'channel' => 'in_app',
            'token' => 'guarantor-token-p33',
            'short_code' => 'P33GRQ',
            'contact' => $guarantor->phone,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($guarantor->user)
            ->get(route('site.borrower.guarantor-requests.show', $link));

        $response->assertOk()
            ->assertSee('max-w-3xl', false)
            ->assertSee(__('borrower.guaranteed.detail_glance_title'), false)
            ->assertSee(__('borrower.guarantor.your_decision'), false);
    }

    public function test_payments_index_shows_translated_refund_entries(): void
    {
        $customer = $this->completeBorrower('040');
        $product = $this->loanProduct();

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-P33-REF',
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
            'reference' => 'REF-P33-001',
            'amount' => 75_000,
            'currency' => 'TZS',
            'status' => BorrowerRefund::STATUS_PENDING,
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.refund', $refund))
            ->assertOk()
            ->assertSee(__('borrower.payments_page.refund.title'), false)
            ->assertSee(__('borrower.payments_page.refund.statuses.pending'), false)
            ->assertSee('REF-P33-001', false);
    }

    public function test_offer_letter_pdf_template_uses_translated_labels(): void
    {
        $customer = $this->completeBorrower('050');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P33-PDF',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);

        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'offer_letter',
            'reference' => 'OL-P33TEST',
            'status' => 'sent',
            'expires_at' => now()->addDays(14),
        ]);

        $html = view('pdf.offer-letter', [
            'application' => $application->load('product'),
            'agreement' => $agreement,
            'snapshot' => [
                'customer_name' => 'Complete Borrower',
                'application_number' => $application->application_number,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'principal' => 450_000,
                'interest_rate' => 0.15,
                'displayed_monthly_rate' => 0.15,
                'tenure_months' => 12,
                'repayment_cadence' => 'monthly',
                'installment_count' => 12,
                'estimated_emi' => 45_000,
                'total_repayable' => 540_000,
            ],
        ])->render();

        $this->assertStringContainsString(__('borrower.offer_letter.pdf.facility_heading'), $html);
        $this->assertStringContainsString(__('borrower.offer_letter.pdf.application_number'), $html);
        $this->assertStringContainsString(__('borrower.offer_letter.pdf.next_steps_heading'), $html);
    }
}
