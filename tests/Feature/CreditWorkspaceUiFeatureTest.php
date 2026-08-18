<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanFee;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\ActiveLoanServicingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreditWorkspaceUiFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        $branch = Branch::create([
            'code' => 'CW'.random_int(10, 99),
            'name' => 'CW Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => $role,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor, string $stage): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'CW-'.random_int(100, 999),
            'name' => 'CW Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-CW-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Workspace',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $actor->branch_id,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $actor->branch_id,
            'application_number' => 'APP-CW-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => $stage,
            'submitted_at' => now(),
        ]);
    }

    public function test_screening_and_committee_share_premium_workspace_with_tabs(): void
    {
        $admin = $this->staff();

        foreach (['screening', 'credit_appraisal', 'pre_approval'] as $stage) {
            $app = $this->application($admin, $stage);
            $html = $this->actingAs($admin, 'admin')
                ->get(route('admin.loan-applications.show', $app))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Facility summary', $html);
            $this->assertStringContainsString('Risk score', $html);
            $this->assertStringContainsString('Borrower CRB', $html);
            $this->assertStringContainsString('Open guarantor file', $html);
            $this->assertStringContainsString('Review checklist', $html);
            $this->assertStringContainsString('workspace=profiles', $html);
            $this->assertStringContainsString('workspace=decision', $html);

            $profiles = $this->actingAs($admin, 'admin')
                ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'profiles']))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString('Profile sections', $profiles);
            $this->assertStringContainsString('tab=face', $profiles);
            $this->assertStringContainsString('person=guarantor', $profiles);
            $this->assertStringNotContainsString('Partners available', $profiles);
            $this->assertStringNotContainsString('Partners unavailable', $profiles);
            $this->assertStringNotContainsString('>Group</a>', $profiles);
            $this->assertStringContainsString('tab=personal', $profiles);
            $this->assertStringNotContainsString('tab=affordability', $profiles);
            $this->assertStringNotContainsString('tab=crb', $profiles);
        }

        $app = $this->application($admin, 'screening');
        $screeningHome = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Screening workspace', $screeningHome);
        $this->assertStringContainsString('Other institutions', $screeningHome);
        $this->assertStringContainsString('Affordability', $screeningHome);
        $this->assertStringNotContainsString('Profile complete', $screeningHome);
        $this->assertStringNotContainsString('Record the committee decision', $screeningHome);

        $screening = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Record the screening recommendation', $screening);
        $this->assertStringContainsString('Max this income can support', $screening);
        $this->assertStringContainsString('Your decision', $screening);
        $this->assertStringContainsString('Record decision', $screening);
        $this->assertStringContainsString('Record your decision', $screening);
        $this->assertStringContainsString('Why are you approving?', $screening);
        $this->assertStringContainsString('Push to Committee', $screening);
        $this->assertStringContainsString('Review checklist → Docs', $screening);
        $this->assertStringNotContainsString('Need files? Request them on the Documents tab.', $screening);
        $this->assertStringNotContainsString('Who you are reviewing', $screening);
        $this->assertStringNotContainsString('Preferred reject reason', $screening);
        $this->assertStringNotContainsString('Return for documents', $screening);
        $this->assertStringContainsString('Advice for borrower', $screening);
        $this->assertStringContainsString('Rejection reasons', $screening);
        $this->assertStringContainsString('Open field advice', $screening);
        $this->assertStringNotContainsString('Complete screening', $screening);

        $residence = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'profiles', 'tab' => 'residence']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Residence information', $residence);
        $this->assertStringContainsString('aria-selected="true"', $residence);

        $face = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'profiles', 'tab' => 'face']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Side-by-side comparison', $face);
        $this->assertStringContainsString('Primary check', $face);
        $this->assertStringContainsString('Front face not uploaded', $face);

        $documents = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'checklist']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Personal in place', $documents);
        $this->assertStringContainsString('Capacity and evidence', $documents);
        $this->assertStringContainsString('Security and close', $documents);
        $this->assertStringContainsString('Application evidence', $documents);
        $this->assertStringContainsString('Document library', $documents);
        $this->assertStringContainsString('Request documents', $documents);
        $this->assertStringContainsString('Checklist', $documents);
        $this->assertStringContainsString('Requested', $documents);
        $this->assertStringContainsString('Library', $documents);
        $this->assertStringContainsString('Affordability', $documents);
        $this->assertStringContainsString('Credit file wrap-up', $documents);
        $this->assertStringContainsString('Hold — finish checklist', $documents);
        $this->assertStringContainsString('Gate 2', $documents);
        $this->assertStringContainsString('Match statements to profile revenue', $documents);
        $this->assertStringContainsString('Statement totals', $documents);
        $this->assertStringContainsString('Period is always 6 months', $documents);
        $this->assertStringNotContainsString('Max repayment (1/3)', $documents);
        $this->assertStringNotContainsString('Partners for this file', $documents);
        $this->assertStringNotContainsString('Return for documents', $documents);

        $this->assertStringContainsString('person=borrower', $screening);
        $this->assertStringContainsString('Open guarantor file', $screening);

        $committeeApp = $this->application($admin, 'pre_approval');
        $committee = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $committeeApp, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Committee workspace', $committee);
        $this->assertStringContainsString('Sprint critical areas on the same evidence', $committee);
        $this->assertStringContainsString('Committee sprint', $committee);
        $this->assertStringContainsString('Critical areas — not a full re-screen', $committee);
        $this->assertStringContainsString('Change with reason', $committee);
        $this->assertStringContainsString('Record the committee decision', $committee);
        $this->assertStringContainsString('Validate screening', $committee);
        $this->assertStringContainsString('Reason for approval', $committee);
        $this->assertStringContainsString('Rejection reasons', $committee);
        $this->assertStringContainsString('Borrower CRB · Guarantor · Screening', $committee);
        $this->assertStringContainsString('Sprint critical areas', $committee);

        $checklistFlags = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'checklist']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Review checklist', $checklistFlags);
    }

    public function test_management_stage_uses_premium_ops_workspace(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'approval');
        $app->forceFill([
            'offered_amount' => 400_000,
            'offer_status' => 'accepted',
            'approved_at' => now(),
        ])->save();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Credit management workspace', $html);
        $this->assertStringContainsString('Approved facility', $html);
        $this->assertStringContainsString('Release readiness', $html);
        $this->assertStringContainsString('Management spine', $html);
        $this->assertStringContainsString('Offer · Fees · Destination · Contract · Disbursement', $html);
        $this->assertStringNotContainsString('Profile sections', $html);
        $this->assertStringNotContainsString('Borrower CRB', $html);
        $this->assertStringNotContainsString('Capital partner funds', $html);
        $this->assertStringNotContainsString('Override amount', $html);
        $this->assertStringNotContainsString('Waive fee', $html);
    }

    public function test_decision_desk_points_to_docs_and_marks_unstaged_recommendation_as_draft(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'screening');

        foreach (['National ID (front)', 'Business licence', 'Proof of residence'] as $label) {
            LoanApplicationDocumentRequest::create([
                'loan_application_id' => $app->id,
                'requested_by' => $admin->id,
                'label' => $label,
                'type' => 'document',
                'status' => 'pending',
            ]);
        }

        $decision = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cannot push to committee yet', $decision);
        $this->assertStringContainsString('Review checklist → Docs', $decision);
        $this->assertStringContainsString('Cannot approve until these documents are in', $decision);
        $this->assertStringContainsString('National ID (front)', $decision);
        $this->assertStringContainsString('docs_panel=requests', $decision);
        $this->assertStringContainsString('capacity_tab=documents', $decision);
        $this->assertStringNotContainsString('Who you are reviewing', $decision);

        $app->forceFill([
            'recommendation_type' => 'approve',
            'recommended_amount' => 800_000,
            'recommended_at' => now(),
            'recommended_by' => $admin->id,
        ])->save();

        $draft = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Draft · not pushed', $draft);
        $this->assertStringContainsString('Draft on file — not pushed to committee', $draft);
        $this->assertStringNotContainsString('Recommendation on file:', $draft);
    }

    public function test_rejected_individual_application_show_page_loads(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'rejected');
        $app->update(['status' => 'rejected']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee($app->application_number, false)
            ->assertSee('Rejected file', false)
            ->assertSee('View only', false)
            ->assertSee('Decision on file', false)
            ->assertSee('Decision reason', false)
            ->assertSee('Feedback letter', false)
            ->assertDontSee('Edit application')
            ->assertDontSee('What you need to decide')
            ->assertDontSee('Review checklist')
            ->assertDontSee('Borrower CRB');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.edit', $app))
            ->assertForbidden();
    }

    public function test_withdrawn_file_shows_withdrawn_status_only(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'rejected');
        $app->update(['status' => 'withdrawn', 'current_stage' => 'rejected']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('Withdrawn', false)
            ->assertSee('View only', false)
            ->assertDontSee('Edit application');
    }

    public function test_disbursed_and_arrears_files_use_credit_management_tabs(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'disbursement');
        $app->update(['status' => 'disbursed', 'disbursed_at' => now()->subMonths(2)]);

        $loan = Loan::create([
            'customer_id' => $app->customer_id,
            'loan_product_id' => $app->loan_product_id,
            'loan_application_id' => $app->id,
            'loan_number' => 'LN-CW-ARRS',
            'principal_amount' => 50_000,
            'approved_amount' => 50_000,
            'outstanding_balance' => 79_934,
            'interest_rate' => 0.18,
            'tenure_months' => 2,
            'status' => 'arrears',
            'disbursement_date' => now()->subMonths(2),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Credit management', $html);
        $this->assertStringContainsString('Facility', $html);
        $this->assertStringContainsString('Documents', $html);
        $this->assertStringContainsString('What they owe', $html);
        $this->assertStringContainsString('Upcoming payments', $html);
        $this->assertStringContainsString('Ask for payment', $html);
        $this->assertStringContainsString('Ask borrower to pay', $html);
        $this->assertStringContainsString('Total outstanding', $html);
        $this->assertStringContainsString('Missed instalments', $html);
        $this->assertStringNotContainsString('Record repayment', $html);
        $this->assertStringContainsString('Outstanding', $html);
        $this->assertStringContainsString('Repayment health', $html);
        $this->assertStringContainsString('Signed contract', $html);
        $this->assertStringContainsString('Loan in arrears', $html);
        $this->assertStringContainsString($loan->loan_number, $html);
        $this->assertStringNotContainsString('What you need to decide', $html);
        $this->assertStringNotContainsString('Review checklist', $html);
        $this->assertStringNotContainsString('Borrower CRB', $html);
        $this->assertStringNotContainsString('>Decision</a>', $html);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertRedirect(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'facility',
            ]));
    }

    public function test_disbursed_letters_tab_shows_signed_contract_then_offer_letter(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('agreements/cw-offer.pdf', '%PDF-1.4 offer');
        Storage::disk('public')->put('agreements/cw-final.pdf', '%PDF-1.4 final');

        $admin = $this->staff();
        $app = $this->application($admin, 'disbursement');
        $app->update(['status' => 'disbursed', 'disbursed_at' => now()->subMonths(2)]);

        Loan::create([
            'customer_id' => $app->customer_id,
            'loan_product_id' => $app->loan_product_id,
            'loan_application_id' => $app->id,
            'loan_number' => 'LN-CW-SIGN',
            'principal_amount' => 50_000,
            'approved_amount' => 50_000,
            'outstanding_balance' => 40_000,
            'interest_rate' => 0.18,
            'tenure_months' => 2,
            'status' => 'active',
            'disbursement_date' => now()->subMonths(2),
        ]);

        LoanAgreement::create([
            'loan_application_id' => $app->id,
            'customer_id' => $app->customer_id,
            'document_type' => 'offer_letter',
            'reference' => 'OL-CW-SIGN',
            'status' => 'signed',
            'signed_at' => now()->subMonths(2),
            'file_path' => 'agreements/cw-offer.pdf',
        ]);
        $final = LoanAgreement::create([
            'loan_application_id' => $app->id,
            'customer_id' => $app->customer_id,
            'document_type' => 'final_loan_contract',
            'reference' => 'FLC-CW-SIGN',
            'status' => 'signed',
            'signed_at' => now()->subMonths(2),
            'file_path' => 'agreements/cw-final.pdf',
        ]);

        $home = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Signed contract', $home);
        $this->assertStringContainsString('Executed', $home);
        $this->assertStringContainsString('FLC-CW-SIGN', $home);

        $letters = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'documents',
            ]))
            ->assertOk()
            ->getContent();

        $signedPos = strpos($letters, 'Signed contract');
        $offerPos = strpos($letters, 'Offer letter');
        $this->assertNotFalse($signedPos);
        $this->assertNotFalse($offerPos);
        $this->assertLessThan($offerPos, $signedPos);
        $this->assertStringContainsString('FLC-CW-SIGN', $letters);
        $this->assertStringContainsString('OL-CW-SIGN', $letters);
        $this->assertStringContainsString(route('admin.loan-agreements.download', $final), $letters);
        $this->assertStringContainsString('Download PDF', $letters);
        $this->assertStringContainsString('document-holder', $letters);
        $this->assertSame(1, substr_count($letters, 'document-holder'));
        $this->assertStringContainsString('A4 preview', $letters);
        $this->assertStringContainsString('<iframe', $letters);
        $this->assertStringNotContainsString('Review checklist', $letters);
    }

    public function test_owed_totals_split_missed_installments_from_outstanding(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'disbursement');
        $app->update(['status' => 'disbursed', 'disbursed_at' => now()->subMonths(2)]);
        $loan = Loan::create([
            'customer_id' => $app->customer_id,
            'loan_product_id' => $app->loan_product_id,
            'loan_application_id' => $app->id,
            'loan_number' => 'LN-CW-OWE',
            'principal_amount' => 50_000,
            'approved_amount' => 50_000,
            'outstanding_balance' => 65_000,
            'interest_rate' => 0.18,
            'tenure_months' => 2,
            'status' => 'arrears',
            'disbursement_date' => now()->subMonths(2),
        ]);

        RepaymentSchedule::create([
            'loan_id' => $loan->id,
            'installment_no' => 1,
            'due_date' => now()->subDays(20)->toDateString(),
            'principal_due' => 25_000,
            'interest_due' => 5_000,
            'total_due' => 30_000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
        RepaymentSchedule::create([
            'loan_id' => $loan->id,
            'installment_no' => 2,
            'due_date' => now()->addDays(10)->toDateString(),
            'principal_due' => 25_000,
            'interest_due' => 5_000,
            'total_due' => 30_000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
        LoanFee::create([
            'loan_id' => $loan->id,
            'code' => 'LATE_FEE',
            'name' => 'Late payment fee',
            'type' => 'fixed',
            'basis' => 'overdue_balance',
            'rate_or_amount' => 5_000,
            'computed_amount' => 5_000,
            'status' => 'charged',
            'charge_when' => 'late',
        ]);

        $servicing = app(ActiveLoanServicingService::class)->forLoan($loan->fresh(['repaymentSchedules', 'fees']));

        $this->assertEquals(65_000.0, $servicing['outstanding_balance']);
        $this->assertEquals(30_000.0, $servicing['amount_in_arrears']);
        $this->assertSame(1, $servicing['overdue_installments']);
        $this->assertEquals(30_000.0, $servicing['next_due_amount']);
        $this->assertEquals(0.0, $servicing['principal_paid']);
        $this->assertEquals(0.0, $servicing['progress_pct']);
        $this->assertCount(1, $servicing['upcoming_rows']);
        $this->assertSame(2, $servicing['upcoming_rows']->first()->installment_no);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'facility',
                'section' => 'owed',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Total outstanding', $html);
        $this->assertStringContainsString('Missed instalments', $html);
        $this->assertGreaterThan(
            strpos($html, 'Total outstanding'),
            strpos($html, 'Ask borrower to pay')
        );
    }

    public function test_staff_asks_for_payment_instead_of_recording_it(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'disbursement');
        $app->update(['status' => 'disbursed', 'disbursed_at' => now()->subMonths(2)]);
        $loan = Loan::create([
            'customer_id' => $app->customer_id,
            'loan_product_id' => $app->loan_product_id,
            'loan_application_id' => $app->id,
            'loan_number' => 'LN-CW-PAY',
            'principal_amount' => 50_000,
            'approved_amount' => 50_000,
            'outstanding_balance' => 60_644,
            'interest_rate' => 0.18,
            'tenure_months' => 2,
            'status' => 'arrears',
            'disbursement_date' => now()->subMonths(2),
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'facility', 'section' => 'owed']))
            ->post(route('admin.loans.payment-requests.store', $loan), [
                'amount' => '25,000',
                'note' => 'Catch-up',
            ])
            ->assertRedirect();

        $payment = CustomerPayment::query()->where('loan_id', $loan->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('loan_repayment', $payment->payment_type);
        $this->assertSame('awaiting_payment', $payment->status);
        $this->assertEquals(25000.0, (float) $payment->amount);
        $this->assertTrue((bool) data_get($payment->provider_meta, 'staff_requested'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'facility',
                'section' => 'owed',
            ]))
            ->assertOk()
            ->assertSee($payment->reference, false)
            ->assertSee('Open payment requests', false)
            ->assertDontSee('Record repayment');
    }

    public function test_disbursed_group_application_show_page_loads(): void
    {
        $admin = $this->staff();
        $product = LoanProduct::create([
            'code' => 'GL-CW-'.random_int(100, 999),
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 50_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $leader = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GL-L-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gaspari',
            'last_name' => 'Shiliba',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $admin->branch_id,
            'monthly_income' => 400_000,
        ]);
        $app = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $admin->branch_id,
            'application_number' => 'APP-GL-C74S',
            'requested_amount' => 150_000,
            'requested_tenure_months' => 2,
            'status' => 'disbursed',
            'current_stage' => 'disbursement',
            'submitted_at' => now()->subMonths(2),
            'disbursed_at' => now()->subMonths(2),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-CW-001',
            'name' => 'Demo Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $app->id,
            'status' => 'active',
            'target_member_count' => 1,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $app->id,
            'role' => 'leader',
            'requested_amount' => 50_000,
            'sort_order' => 1,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $app->update(['loan_group_id' => $group->id]);
        Loan::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $app->id,
            'loan_number' => 'LN-GL-X6C8',
            'principal_amount' => 50_000,
            'approved_amount' => 50_000,
            'outstanding_balance' => 79_934,
            'interest_rate' => 0.18,
            'tenure_months' => 2,
            'status' => 'arrears',
            'disbursement_date' => now()->subMonths(2),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('Credit management', false)
            ->assertSee('LN-GL-X6C8', false)
            ->assertSee('Facility', false)
            ->assertSee('Documents', false)
            ->assertDontSee('Review checklist')
            ->assertDontSee('Borrower CRB');
    }

    public function test_checklist_affordability_survives_stale_payload_without_pass_key(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'screening');
        $app->update([
            'credit_appraisal_payload' => [
                'affordability' => [
                    'reason' => 'Stale stored row with no pass flag',
                    'net_income' => 300_000,
                ],
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
                'capacity_tab' => 'affordability',
            ]))
            ->assertOk()
            ->assertSee('Affordability', false)
            ->assertDontSee('Undefined array key', false);
    }

    public function test_credit_management_cannot_open_rejected_files(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'rejected');
        $app->update([
            'status' => 'rejected',
            'rejection_reason' => 'Capacity below the proposed instalment.',
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'branch_id' => $admin->branch_id,
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.rejected'))
            ->assertForbidden();
    }

    public function test_screening_can_open_rejected_file_with_reason_and_without_checklist(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin, 'rejected');
        $app->update([
            'status' => 'rejected',
            'rejection_reason' => 'Capacity below the proposed instalment.',
        ]);

        $analyst = User::factory()->create([
            'role' => 'credit_analyst',
            'branch_id' => $admin->branch_id,
            'is_active' => true,
        ]);

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('Capacity below the proposed instalment.', false)
            ->assertSee('Feedback letter', false)
            ->assertDontSee('Review checklist');

        $this->actingAs($analyst, 'admin')
            ->get(route('admin.loan-applications.rejected'))
            ->assertOk()
            ->assertSee('Rejected applications', false);
    }
}
