<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\ProfileRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileLinkedDocumentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_updated_bank_statement_upload_marks_request_uploaded_and_survives_profile_view_sync(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'IL-DOC',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-DOC-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Upendo',
            'last_name' => 'Ketto',
            'phone' => '255712340001',
            'activity_type' => 'self_employed',
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
        ]);

        $type = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);

        $docService = app(ApplicationDocumentRequestService::class);
        $request = $docService->create($application, $admin, 'Updated Bank Statement');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertFalse(
            app(\App\Services\IncomeProofService::class)->hasPrimaryProof($customer->fresh())
        );

        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'loan_application_id' => null,
            'file_path' => 'customer/'.$customer->id.'/documents/statement.pdf',
            'status' => 'pending_review',
        ]);

        $marked = $docService->markIncomeRequestsUploadedFromProfile($customer->fresh(), ['bank_statement']);
        $this->assertSame(1, $marked);
        $this->assertSame('uploaded', $request->fresh()->status);

        app(ProfileRevisionService::class)->ensureClearedForOpenRequests($application->fresh());

        $this->assertTrue(
            app(\App\Services\IncomeProofService::class)->hasPrimaryProof($customer->fresh()),
            'Re-opening the loan profile must not wipe the new bank statement.'
        );
        $this->assertSame('uploaded', $request->fresh()->status);
    }
}
