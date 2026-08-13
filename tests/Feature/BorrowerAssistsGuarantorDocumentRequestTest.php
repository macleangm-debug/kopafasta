<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BorrowerAssistsGuarantorDocumentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_can_see_and_fulfill_guarantor_document_request(): void
    {
        Storage::fake('public');

        $product = LoanProduct::create([
            'code' => 'IL-G',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor' => true,
        ]);

        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-BRW-G',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'Owner',
            'phone' => '255712340011',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $guarantor = Customer::create([
            'customer_number' => 'CU-GTR-G',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => '255712340022',
        ]);

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GTR-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create(
            $application,
            $admin,
            'Guarantor residence letter',
            subjectKind: 'guarantor',
            subjectCustomerId: $guarantor->id,
        );

        $this->assertTrue($service->customerCanFulfillRequest($borrower->fresh(), $request->fresh()));
        $this->assertTrue($service->borrowerIsAssisting($borrower->fresh(), $request->fresh()));
        $this->assertFalse($service->isProfileGuidedForCustomer($borrower->fresh(), $request->fresh()));
        $this->assertStringContainsString(
            '#request-'.$request->id,
            $service->borrowerActionUrl($request->fresh(), $borrower->fresh())
        );

        $open = $service->openRequestsForCustomer($borrower->fresh());
        $this->assertTrue($open->contains('id', $request->id));

        $service->recordUploads(
            $request->fresh(),
            $borrower->fresh(),
            [UploadedFile::fake()->image('residence.jpg')],
            $guarantor->fresh()
        );

        $this->assertSame('uploaded', $request->fresh()->status);
        $this->assertSame($borrower->id, $request->fresh()->uploaded_by_customer_id);
        $this->assertDatabaseHas('customer_documents', [
            'customer_id' => $guarantor->id,
            'loan_application_document_request_id' => $request->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_borrower_http_upload_for_guarantor_request_is_allowed(): void
    {
        Storage::fake('public');

        $product = LoanProduct::create([
            'code' => 'IL-G2',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor' => true,
        ]);

        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        app(\App\Services\PinRecoveryChallengeService::class)->enroll($borrowerUser, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-BRW-G2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'Owner',
            'phone' => '255712340033',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $guarantor = Customer::create([
            'customer_number' => 'CU-GTR-G2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => '255712340044',
        ]);

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GTR-002',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create(
            $application,
            $admin,
            'Guarantor residence letter',
            subjectKind: 'guarantor',
            subjectCustomerId: $guarantor->id,
        );

        $response = $this->actingAs($borrowerUser)
            ->post(route('site.borrower.application.document-requests.store', [$application, $request]), [
                'file' => UploadedFile::fake()->image('residence.jpg'),
            ]);

        $response->assertRedirect(route('site.borrower.application', $application->id));
        $this->assertSame('uploaded', $request->fresh()->status);
    }
}
