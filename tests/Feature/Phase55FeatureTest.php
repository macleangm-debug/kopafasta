<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\KycFreshnessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase55FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_kyc_section_freshness_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.kyc.save'), [
                'require_nida'             => '1',
                'min_age'                  => '18',
                'max_age'                  => '75',
                'crb_freshness_days'       => '90',
                'freshness_section_days'   => [
                    'residence' => '120',
                    'activity'  => '60',
                    'documents' => '90',
                    'kin'       => '365',
                    'face'      => 'never',
                    'nida'      => 'never',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $stored = Setting::group('kyc')['freshness_section_days'] ?? [];

        $this->assertSame(120, $stored['residence']);
        $this->assertSame(60, $stored['activity']);
        $this->assertSame('never', $stored['face']);
        $this->assertSame(60, app(KycFreshnessService::class)->sectionFreshnessDaysFor('activity'));
    }

    public function test_final_loan_contract_pdf_renders_guarantor_and_company_signature_blocks(): void
    {
        $product = LoanProduct::create([
            'code'              => 'PL',
            'name'              => 'Personal Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P55-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Borrower',
            'last_name'       => 'P55',
            'phone'           => '255712345896',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P55-001',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'disbursed',
        ]);

        $loan = new Loan([
            'loan_number'      => 'LN-P55-001',
            'principal_amount' => 500_000,
            'disbursement_date'=> now(),
        ]);
        $loan->id = 1;

        $agreement = new LoanAgreement([
            'document_type' => 'final_loan_contract',
            'reference'   => 'FLC-P55TEST',
            'status'      => 'signed',
        ]);
        $agreement->id = 1;

        $html = view('pdf.final-loan-contract', [
            'application'    => $application,
            'agreement'      => $agreement,
            'loan'           => $loan,
            'signedContract' => null,
            'snapshot'       => [
                'customer_name'           => 'Borrower P55',
                'guarantor_name'          => 'Guarantor P55',
                'principal'               => 500_000,
                'displayed_monthly_rate'  => 0.15,
                'tenure_months'           => 12,
                'estimated_emi'           => 50_000,
                'installment_label'       => 'Monthly instalment',
                'disbursement_date'       => now()->toDateString(),
                'first_due_date'          => now()->addMonth()->toDateString(),
                'last_due_date'           => now()->addMonths(12)->toDateString(),
                'legal_clauses'           => [],
                'repayment_schedule'      => [],
                'borrower_signature'      => (object) [
                    'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
                    'signer_name'    => 'Borrower P55',
                ],
                'guarantor_signature'     => (object) [
                    'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
                    'signer_name'    => 'Guarantor P55',
                ],
                'company_signatory_name'  => 'Managing Director',
                'company_signatory_title' => 'CEO',
                'company_signature_path'  => 'data:image/png;base64,iVBORw0KGgo=',
                'company_stamp_path'      => 'data:image/png;base64,iVBORw0KGgo=',
            ],
        ])->render();

        $this->assertStringContainsString('Guarantor', $html);
        $this->assertStringContainsString('Guarantor P55', $html);
        $this->assertStringContainsString('Managing Director', $html);
        $this->assertStringContainsString('Company stamp', $html);
        $this->assertStringContainsString('sig-img', $html);
    }
}
