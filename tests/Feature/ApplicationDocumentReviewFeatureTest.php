<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentReview;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationDocumentReviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'DR'.random_int(10, 99),
            'name' => 'Doc Review Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor): array
    {
        $product = LoanProduct::create([
            'code' => 'DR-'.random_int(100, 999),
            'name' => 'Doc Review Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-DR-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Doc',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $actor->branch_id,
        ]);

        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $actor->branch_id,
            'application_number' => 'APP-DR-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        $type = DocumentType::create([
            'code' => 'bank_statement_'.random_int(10, 99),
            'name' => 'Bank statement (last 6 months)',
            'category' => 'kyc',
            'is_active' => true,
        ]);

        $doc = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/demo-statement.pdf',
            'status' => 'pending_review',
        ]);

        return compact('app', 'customer', 'doc');
    }

    public function test_verify_is_application_scoped_and_leaves_profile_pending(): void
    {
        $admin = $this->staff();
        ['app' => $app, 'doc' => $doc] = $this->application($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.documents.verify', [$app, $doc]), [
                'review_person' => 'borrower',
            ])
            ->assertRedirect();

        $review = LoanApplicationDocumentReview::query()
            ->where('loan_application_id', $app->id)
            ->where('customer_document_id', $doc->id)
            ->first();

        $this->assertNotNull($review);
        $this->assertSame('verified', $review->status);
        $this->assertSame('pending_review', $doc->fresh()->status);
    }

    public function test_fail_with_reason_and_request_again_creates_replacement(): void
    {
        $admin = $this->staff();
        ['app' => $app, 'doc' => $doc] = $this->application($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.documents.reject', [$app, $doc]), [
                'review_person' => 'borrower',
                'fail_reason_code' => 'unclear_image',
                'remedy' => 'request_again',
                'request_again_label' => 'Clearer bank statement',
            ])
            ->assertRedirect();

        $review = LoanApplicationDocumentReview::query()
            ->where('loan_application_id', $app->id)
            ->where('customer_document_id', $doc->id)
            ->first();

        $this->assertSame('rejected', $review?->status);
        $this->assertSame('unclear_image', $review?->fail_reason_code);
        $this->assertSame('request_again', $review?->remedy);
        $this->assertDatabaseHas('loan_application_document_requests', [
            'loan_application_id' => $app->id,
            'label' => 'Clearer bank statement',
            'status' => 'pending',
        ]);
    }

    public function test_verifying_id_document_auto_passes_linked_checklist_item(): void
    {
        $admin = $this->staff();
        ['app' => $app, 'customer' => $customer] = $this->application($admin);

        $type = DocumentType::create([
            'code' => 'national_id_front',
            'name' => 'National ID — front',
            'category' => 'kyc',
            'is_active' => true,
        ]);

        $doc = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/id-front.jpg',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.documents.verify', [$app, $doc]), [
                'review_person' => 'borrower',
            ])
            ->assertRedirect();

        $payload = (array) ($app->fresh()->screening_payload ?? []);
        $items = (array) data_get($payload, 'screening_checklist.by_subject.borrower.items', []);

        $this->assertSame('pass', $items['identity.id_document_quality']['verdict'] ?? null);
        $this->assertSame('documents', $items['identity.id_document_quality']['source'] ?? null);
    }

    public function test_failing_id_document_auto_fails_linked_checklist_item_with_reason(): void
    {
        $admin = $this->staff();
        ['app' => $app, 'customer' => $customer] = $this->application($admin);

        $type = DocumentType::create([
            'code' => 'national_id_front',
            'name' => 'National ID — front',
            'category' => 'kyc',
            'is_active' => true,
        ]);

        $doc = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/id-front.jpg',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.documents.reject', [$app, $doc]), [
                'review_person' => 'borrower',
                'fail_reason_code' => 'altered',
                'remedy' => 'none',
            ])
            ->assertRedirect();

        $payload = (array) ($app->fresh()->screening_payload ?? []);
        $items = (array) data_get($payload, 'screening_checklist.by_subject.borrower.items', []);

        $this->assertSame('fail', $items['identity.id_document_quality']['verdict'] ?? null);
        $this->assertSame('documents', $items['identity.id_document_quality']['source'] ?? null);
        $this->assertSame('suspected_tamper', $items['identity.id_document_quality']['fail_reason_code'] ?? null);
    }

    public function test_passing_id_checklist_items_auto_verifies_pending_id_document(): void
    {
        $admin = $this->staff();
        ['app' => $app, 'customer' => $customer] = $this->application($admin);

        $type = DocumentType::create([
            'code' => 'national_id_front',
            'name' => 'National ID — front',
            'category' => 'kyc',
            'is_active' => true,
        ]);

        $doc = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/id-front.jpg',
            'status' => 'pending_review',
        ]);

        app(\App\Services\ScreeningChecklistService::class)->save($app, $admin, [
            'identity' => [
                'face_vs_nida' => ['verdict' => 'pass'],
                'id_document_quality' => ['verdict' => 'pass'],
            ],
        ], 'borrower');

        $review = LoanApplicationDocumentReview::query()
            ->where('loan_application_id', $app->id)
            ->where('customer_document_id', $doc->id)
            ->first();

        $this->assertNotNull($review);
        $this->assertSame('verified', $review->status);
        $this->assertStringContainsString('checklist', (string) ($review->notes ?? ''));
    }
}
