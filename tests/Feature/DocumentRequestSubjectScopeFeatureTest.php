<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\ProfileRevisionService;
use App\Services\ScreeningChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRequestSubjectScopeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_request_for_one_group_member_does_not_clear_anyone_else(): void
    {
        [$admin, $application, $leader, $asked, $other, $askedRow, $otherRow, $docs] = $this->groupFileWithStatements();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $application), [
                'type' => 'document',
                'presets' => ['Updated Bank Statement'],
                'request_subject' => 'borrower',
                'review_person' => 'member',
                'review_m' => $askedRow->id,
            ])
            ->assertRedirect();

        $request = $application->fresh()->documentRequests()->first();
        $this->assertNotNull($request);
        $this->assertSame('member', $request->subject_kind);
        $this->assertSame($asked->id, (int) $request->subject_customer_id);
        $this->assertSame($askedRow->id, (int) $request->loan_group_member_id);

        $this->assertSame('replaced', $docs['asked']->fresh()->status);
        $this->assertSame('pending_review', $docs['leader']->fresh()->status);
        $this->assertSame('pending_review', $docs['other']->fresh()->status);

        $docService = app(ApplicationDocumentRequestService::class);
        $this->assertTrue($docService->targetsReviewSubject($request->fresh(), 'member', $asked->id, $askedRow->id, $leader->id));
        $this->assertFalse($docService->targetsReviewSubject($request->fresh(), 'member', $other->id, $otherRow->id, $leader->id));
        $this->assertFalse($docService->targetsReviewSubject($request->fresh(), 'borrower', $leader->id, null, $leader->id));

        $revision = app(ProfileRevisionService::class);
        $revision->ensureClearedForOpenRequests($application->fresh());
        $this->assertTrue($revision->hasOpenRevision($asked->fresh(), 'income'));
        $this->assertFalse($revision->hasOpenRevision($leader->fresh(), 'income'));
        $this->assertFalse($revision->hasOpenRevision($other->fresh(), 'income'));

        $checklist = app(ScreeningChecklistService::class);
        $askedDesk = $checklist->viewModel($application->fresh(), $admin, 'member', null, $askedRow->id);
        $otherDesk = $checklist->viewModel($application->fresh(), $admin, 'member', null, $otherRow->id);
        $leaderDesk = $checklist->viewModel($application->fresh(), $admin, 'borrower');

        $this->assertSame('still_open', $this->checklistItem($askedDesk, 'documents.requested_docs_reviewed')['fail_reason_code'] ?? null);
        $this->assertSame('pass', $this->checklistItem($otherDesk, 'documents.requested_docs_reviewed')['verdict'] ?? null);
        $this->assertSame('pass', $this->checklistItem($leaderDesk, 'documents.requested_docs_reviewed')['verdict'] ?? null);
        $this->assertSame('statements_missing', $this->checklistItem($askedDesk, 'activity_income.income_evidence')['fail_reason_code'] ?? null);
        $this->assertNotSame('statements_missing', $this->checklistItem($otherDesk, 'activity_income.income_evidence')['fail_reason_code'] ?? null);
        $this->assertNotSame('statements_missing', $this->checklistItem($leaderDesk, 'activity_income.income_evidence')['fail_reason_code'] ?? null);

        $otherHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'review_person' => 'member',
                'review_m' => $otherRow->id,
            ]))
            ->assertOk()
            ->getContent();
        $leaderHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'review_person' => 'borrower',
            ]))
            ->assertOk()
            ->getContent();

        $askedHtml = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'review_person' => 'member',
                'review_m' => $askedRow->id,
            ]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Updated Bank Statement', $askedHtml);
        $this->assertStringContainsString('Outstanding requests', $askedHtml);
        $this->assertStringNotContainsString('No open requests for this person', $askedHtml);
        $this->assertStringNotContainsString('Outstanding requests', $otherHtml);
        $this->assertStringNotContainsString('No open requests for this person', $otherHtml);
        $this->assertStringNotContainsString('No open requests for this person', $leaderHtml);
    }

    public function test_borrower_income_request_does_not_hit_the_guarantor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $product = LoanProduct::create([
            'code' => 'IL-SUB',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_guarantor' => true,
        ]);
        $borrower = Customer::create([
            'customer_number' => 'CU-SUB-B',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'phone' => '255712348001',
        ]);
        $guarantor = Customer::create([
            'customer_number' => 'CU-SUB-G',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'phone' => '255712348002',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-SUB-G',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
        ]);
        $bankType = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        $borrowerDoc = CustomerDocument::create([
            'customer_id' => $borrower->id,
            'document_type_id' => $bankType->id,
            'loan_application_id' => null,
            'file_path' => 'customer/'.$borrower->id.'/documents/bank.pdf',
            'status' => 'pending_review',
        ]);
        $guarantorDoc = CustomerDocument::create([
            'customer_id' => $guarantor->id,
            'document_type_id' => $bankType->id,
            'loan_application_id' => null,
            'file_path' => 'customer/'.$guarantor->id.'/documents/bank.pdf',
            'status' => 'pending_review',
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create($application->fresh(), $admin, 'Updated Bank Statement');

        $this->assertSame('borrower', $request->subject_kind);
        $this->assertSame($borrower->id, (int) $request->subject_customer_id);
        $this->assertSame('replaced', $borrowerDoc->fresh()->status);
        $this->assertSame('pending_review', $guarantorDoc->fresh()->status);

        $this->assertTrue($service->isSubjectOfRequest($borrower->fresh(), $request->fresh()));
        $this->assertFalse($service->isSubjectOfRequest($guarantor->fresh(), $request->fresh()));
        $this->assertTrue($service->targetsReviewSubject($request->fresh(), 'borrower', $borrower->id, null, $borrower->id));
        $this->assertFalse($service->targetsReviewSubject($request->fresh(), 'guarantor', $guarantor->id, null, $borrower->id));

        $marked = $service->markIncomeRequestsUploadedFromProfile($guarantor->fresh(), ['bank_statement']);
        $this->assertSame(0, $marked);
        $this->assertSame('pending', $request->fresh()->status);

        $revision = app(ProfileRevisionService::class);
        $revision->ensureClearedForOpenRequests($application->fresh());
        $this->assertTrue($revision->hasOpenRevision($borrower->fresh(), 'income'));
        $this->assertFalse($revision->hasOpenRevision($guarantor->fresh(), 'income'));
    }

    /** @return array{0: mixed, 1: string, 2: ?string} */
    public function test_ask_members_fans_collateral_requests_to_non_leaders_only(): void
    {
        [$admin, $application, $leader, $asked, $other, $askedRow, $otherRow] = $this->groupFileWithStatements();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $application), [
                'type' => 'document',
                'presets' => ['Add collateral asset'],
                'review_person' => 'borrower',
                'ask_members' => '1',
            ])
            ->assertRedirect();

        $requests = $application->fresh()->documentRequests;
        $this->assertCount(2, $requests);
        $this->assertTrue($requests->every(fn ($request) => $request->subject_kind === 'member'));
        $this->assertTrue($requests->every(fn ($request) => $request->label === 'Add collateral asset'));
        $this->assertEqualsCanonicalizing(
            [$asked->id, $other->id],
            $requests->pluck('subject_customer_id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertEqualsCanonicalizing(
            [$askedRow->id, $otherRow->id],
            $requests->pluck('loan_group_member_id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertFalse($requests->contains(fn ($request) => (int) $request->subject_customer_id === (int) $leader->id));
    }

    public function test_document_request_needs_review_then_creates(): void
    {
        [$admin, $application] = $this->groupFileWithStatements();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'documents',
                'person' => 'borrower',
            ]))
            ->post(route('admin.loan-applications.document-requests.store', $application), [
                'type' => 'document',
                'intent' => 'documents',
                'presets' => ['Updated Bank Statement'],
                'instructions' => 'Please send a full 6-month statement.',
                'review_person' => 'borrower',
                'return_workspace' => 'profiles',
                'return_tab' => 'documents',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('confirmed');

        $this->assertSame(0, $application->documentRequests()->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.document-requests.store', $application), [
                'type' => 'document',
                'intent' => 'documents',
                'confirmed' => '1',
                'presets' => ['Updated Bank Statement'],
                'instructions' => 'Please send a full 6-month statement.',
                'review_person' => 'borrower',
                'return_workspace' => 'profiles',
                'return_tab' => 'documents',
            ])
            ->assertRedirect(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'profiles',
                'tab' => 'documents',
                'person' => 'borrower',
                'review_person' => 'borrower',
            ]).'#borrower-file');

        $this->assertSame(1, $application->fresh()->documentRequests()->count());
        $this->assertSame('Updated Bank Statement', $application->fresh()->documentRequests()->first()->label);
    }

    private function checklistItem(array $desk, string $key): array
    {
        foreach ($desk['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (($item['key'] ?? '') === $key) {
                    return $item;
                }
            }
        }

        return [];
    }

    /** @return array{0: User, 1: LoanApplication, 2: Customer, 3: Customer, 4: Customer, 5: LoanGroupMember, 6: LoanGroupMember, 7: array{leader: CustomerDocument, asked: CustomerDocument, other: CustomerDocument}} */
    private function groupFileWithStatements(): array
    {
        $branch = Branch::create([
            'code' => 'SC'.random_int(10, 99),
            'name' => 'Subject Scope Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'GL-SUB',
            'name' => 'Group Loan',
            'category' => 'group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $leader = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SUB-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Scope',
            'phone' => '255712347001',
            'branch_id' => $branch->id,
            'monthly_income' => 400_000,
        ]);
        $asked = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SUB-A',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rogathe',
            'last_name' => 'Nyelle',
            'phone' => '255712347002',
            'branch_id' => $branch->id,
            'monthly_income' => 350_000,
        ]);
        $other = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SUB-O',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Other',
            'last_name' => 'Member',
            'phone' => '255712347003',
            'branch_id' => $branch->id,
            'monthly_income' => 300_000,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-SUB-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-SUB-001',
            'name' => 'Subject Scope Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 3,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'requested_amount' => 300_000,
            'sort_order' => 1,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $askedRow = LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $asked->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 300_000,
            'sort_order' => 2,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $otherRow = LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $other->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 300_000,
            'sort_order' => 3,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $application->update(['loan_group_id' => $group->id]);

        $bankType = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        $docs = [];
        foreach (['leader' => $leader, 'asked' => $asked, 'other' => $other] as $key => $customer) {
            $docs[$key] = CustomerDocument::create([
                'customer_id' => $customer->id,
                'document_type_id' => $bankType->id,
                'loan_application_id' => null,
                'file_path' => 'customer/'.$customer->id.'/documents/bank.pdf',
                'status' => 'pending_review',
            ]);
        }

        return [$admin, $application->fresh(), $leader, $asked, $other, $askedRow, $otherRow, $docs];
    }
}
