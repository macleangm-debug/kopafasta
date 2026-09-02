<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\PinService;
use App\Services\ProfileRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_requesting_bank_statement_clears_bank_file_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'IL-DOC-B',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $customer = Customer::create([
            'customer_number' => 'CU-DOC-010',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Upendo',
            'last_name' => 'Ketto',
            'phone' => '255712340010',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-010',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
        ]);
        $bankType = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        $mobileType = DocumentType::create([
            'code' => 'mobile_money_statement',
            'name' => 'Mobile money statement',
            'is_active' => true,
        ]);
        $bank = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $bankType->id,
            'loan_application_id' => null,
            'file_path' => 'customer/'.$customer->id.'/documents/bank.pdf',
            'status' => 'pending_review',
        ]);
        $mobile = CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $mobileType->id,
            'loan_application_id' => null,
            'file_path' => 'customer/'.$customer->id.'/documents/mobile.pdf',
            'status' => 'pending_review',
        ]);

        app(ApplicationDocumentRequestService::class)->create($application, $admin, 'Updated Bank Statement');

        $this->assertSame('replaced', $bank->fresh()->status);
        $this->assertSame('pending_review', $mobile->fresh()->status);
    }

    public function test_joined_request_pages_store_as_one_named_document(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'IL-DOC-M',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $customer = Customer::create([
            'customer_number' => 'CU-DOC-011',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Upendo',
            'last_name' => 'Ketto',
            'phone' => '255712340011',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-011',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
        ]);
        DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create($application, $admin, 'Updated Bank Statement');
        $stored = $service->recordUploads($request->fresh(), $customer, [
            \Illuminate\Http\UploadedFile::fake()->image('page-1.jpg'),
            \Illuminate\Http\UploadedFile::fake()->image('page-2.jpg'),
        ]);

        $this->assertCount(1, $stored);
        $doc = $stored->first()->load(['documentType', 'documentRequest']);
        $this->assertNotSame('Document', $doc->displayName());
        $this->assertTrue(in_array($doc->displayName(), ['Bank statement', 'Updated Bank Statement'], true));
        $this->assertNotNull($doc->document_type_id);
        $this->assertSame($request->id, $doc->loan_application_document_request_id);
    }

    public function test_member_profile_income_upload_marks_subject_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'GL-DOC',
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
            'customer_number' => 'CU-DOC-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'One',
            'phone' => '255712340012',
        ]);
        $member = Customer::create([
            'customer_number' => 'CU-DOC-M',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asked',
            'last_name' => 'Member',
            'phone' => '255712340013',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-G',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
        ]);
        $group = \App\Models\LoanGroup::create([
            'group_number' => 'GRP-DOC-1',
            'name' => 'Doc Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        $memberRow = \App\Models\LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $member->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 450_000,
            'sort_order' => 2,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $application->update(['loan_group_id' => $group->id]);
        DocumentType::create([
            'code' => 'mobile_money_statement',
            'name' => 'Mobile money statement',
            'is_active' => true,
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create(
            $application->fresh(),
            $admin,
            'Updated Mobile Money Statement',
            subjectKind: 'member',
            loanGroupMemberId: $memberRow->id,
        );

        $marked = $service->markIncomeRequestsUploadedFromProfile($member->fresh(), ['mobile_money_statement']);
        $this->assertSame(1, $marked);
        $this->assertSame('uploaded', $request->fresh()->status);
    }

    public function test_assisting_income_request_stays_on_profile_not_loan_wizard(): void
    {
        $leaderUser = User::factory()->create(['role' => 'borrower']);
        app(\App\Services\PinService::class)->setPin($leaderUser, '1234');
        app(\App\Services\PinRecoveryChallengeService::class)->enroll($leaderUser, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'GL-AST',
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
            'user_id' => $leaderUser->id,
            'customer_number' => 'CU-AST-L',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Assist',
            'phone' => '255712340014',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $member = Customer::create([
            'customer_number' => 'CU-AST-M',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asked',
            'last_name' => 'Member',
            'phone' => '255712340015',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-AST-G',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = \App\Models\LoanGroup::create([
            'group_number' => 'GRP-AST-1',
            'name' => 'Assist Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        $memberRow = \App\Models\LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $member->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 450_000,
            'sort_order' => 2,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $application->update(['loan_group_id' => $group->id]);

        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create(
            $application->fresh(),
            $admin,
            'Updated Bank Statement',
            subjectKind: 'member',
            loanGroupMemberId: $memberRow->id,
        );

        $this->assertTrue($service->isProfileGuidedRequest($request->fresh()));
        $this->assertTrue($service->borrowerIsAssisting($leader->fresh(), $request->fresh()));

        $html = $this->actingAs($leaderUser)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->getContent();

        $this->assertTrue(
            str_contains($html, 'must update this in their profile')
            || str_contains($html, 'lazima asasishe hii kwenye wasifu wake')
        );
        $this->assertStringNotContainsString('name="files"', $html);

        $this->actingAs($leaderUser)
            ->post(route('site.borrower.application.document-requests.store', [$application, $request]), [
                'files' => [\Illuminate\Http\UploadedFile::fake()->image('statement.jpg')],
            ])
            ->assertRedirect();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_residence_and_business_requests_deep_link_to_profile_and_replace_existing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'IL-RES',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $customer = Customer::create([
            'customer_number' => 'CU-DOC-RES',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rehema',
            'last_name' => 'Letter',
            'phone' => '255712340088',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-RES',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
        ]);

        $residenceType = DocumentType::create([
            'code' => 'residence_letter',
            'name' => 'Residence letter',
            'is_active' => true,
        ]);
        $businessType = DocumentType::create([
            'code' => 'business_registration',
            'name' => 'Business registration',
            'is_active' => true,
        ]);
        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $residenceType->id,
            'file_path' => 'customer/'.$customer->id.'/documents/old-letter.pdf',
            'status' => 'pending_review',
        ]);
        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $businessType->id,
            'file_path' => 'customer/'.$customer->id.'/documents/old-reg.pdf',
            'status' => 'pending_review',
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $revision = app(ProfileRevisionService::class);
        $residence = $service->create($application, $admin, 'Guarantor residence letter');
        $business = $service->create($application, $admin, 'Business Registration Document');
        $invoice = $service->create($application, $admin, 'Supplier Invoices');

        $this->assertSame('residence', $service->borrowerActionKind($residence->fresh()));
        $this->assertSame('business', $service->borrowerActionKind($business->fresh()));
        $this->assertSame('document', $service->borrowerActionKind($invoice->fresh()));
        $this->assertTrue($service->isProfileGuidedRequest($residence->fresh()));
        $this->assertTrue($service->isProfileGuidedRequest($business->fresh()));
        $this->assertFalse($service->isProfileGuidedRequest($invoice->fresh()));

        $this->assertStringContainsString('focus=verification', $service->borrowerActionUrl($residence->fresh()));
        $this->assertStringContainsString('solo=1', $service->borrowerActionUrl($residence->fresh()));
        $this->assertStringContainsString('return=', $service->borrowerActionUrl($residence->fresh()));
        $this->assertStringContainsString('profile-residence-verification', $service->borrowerActionUrl($residence->fresh()));
        $this->assertStringContainsString('focus=additional', $service->borrowerActionUrl($business->fresh()));
        $this->assertStringContainsString('doc=business_registration', $service->borrowerActionUrl($business->fresh()));
        $this->assertStringContainsString('#request-'.$invoice->id, $service->borrowerActionUrl($invoice->fresh()));

        $this->assertSame(['residence_letter'], $revision->documentCodesForLabel('Guarantor residence letter'));
        $this->assertSame(['business_registration'], $revision->documentCodesForLabel('Business Registration Document'));

        $this->assertDatabaseMissing('customer_documents', [
            'customer_id' => $customer->id,
            'document_type_id' => $residenceType->id,
            'status' => 'pending_review',
        ]);
        $this->assertDatabaseMissing('customer_documents', [
            'customer_id' => $customer->id,
            'document_type_id' => $businessType->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_notification_opens_loan_then_profile_cta_is_the_exact_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'IL-NOTIF',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-DOC-NOTIF',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rogathe',
            'last_name' => 'Nyelle',
            'phone' => '255712340099',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-NOTIF',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create($application, $admin, 'Bank statement (last 6 months)');

        $notifyUrl = $service->borrowerNotificationUrl($request->fresh());
        $this->assertStringContainsString('/applications/'.$application->id, $notifyUrl);
        $this->assertStringContainsString('doc='.$request->id, $notifyUrl);
        $this->assertStringContainsString('#request-'.$request->id, $notifyUrl);
        $this->assertStringNotContainsString('/borrower/profile', $notifyUrl);

        $cardUrl = $service->borrowerActionUrl($request->fresh());
        $this->assertStringContainsString('focus=income', $cardUrl);
        $this->assertStringContainsString('solo=1', $cardUrl);
        $this->assertStringContainsString('profile-income-statement', $cardUrl);

        $this->assertEquals(1, \App\Models\NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'in_app')
            ->where('template', 'application_document_request')
            ->count());
        $this->assertEquals(0, \App\Models\NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'profile_revision_requested')
            ->count());
    }

    public function test_every_profile_kind_opens_loan_then_the_exact_solo_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::create([
            'code' => 'IL-SOLO',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-DOC-SOLO',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Solo',
            'last_name' => 'Card',
            'phone' => '255712340077',
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-DOC-SOLO',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
        ]);

        $service = app(ApplicationDocumentRequestService::class);

        $cases = [
            ['Updated Bank Statement', 'income', 'focus=income', 'profile-income-statement'],
            ['Updated Mobile Money Statement', 'income', 'focus=income', 'profile-income-statement'],
            ['Latest salary slip', 'income', 'focus=income', 'profile-income-statement'],
            ['New face verification photo', 'face', 'focus=face', 'profile-face'],
            ['Identity verification photo', 'face', 'focus=face', 'profile-face'],
            ['Image Not Clear', 'face', 'focus=face', 'profile-face'],
            ['Updated National ID', 'identity', 'focus=id_images', 'profile-id-images'],
            ['New National ID photo', 'identity', 'focus=id_images', 'profile-id-images'],
            ['Signature Not Visible', 'signature', 'focus=signature', 'profile-signature'],
            ['Guarantor residence letter', 'residence', 'focus=verification', 'profile-residence-verification'],
            ['Business Registration Document', 'business', 'focus=additional', 'profile-additional-documents'],
            ['Business Photos', 'business', 'focus=additional', 'profile-additional-documents'],
            ['New Asset Photo', 'collateral', '/profile/assets', 'solo=1'],
            ['New collateral photo', 'collateral', '/profile/assets', 'solo=1'],
            ['Supplier Invoices', 'document', '/applications/'.$application->id, '#request-'],
        ];

        foreach ($cases as [$label, $kind, $needle, $needle2]) {
            $request = \App\Models\LoanApplicationDocumentRequest::create([
                'loan_application_id' => $application->id,
                'requested_by' => $admin->id,
                'type' => 'document',
                'label' => $label,
                'status' => 'pending',
                'subject_kind' => 'borrower',
                'subject_customer_id' => $customer->id,
            ]);
            $request->setRelation('application', $application);

            $this->assertSame($kind, $service->borrowerActionKind($request), $label);
            $this->assertSame($kind !== 'document', $service->isProfileGuidedRequest($request), $label);

            $notifyUrl = $service->borrowerNotificationUrl($request);
            $this->assertStringContainsString('/applications/'.$application->id, $notifyUrl, $label);
            $this->assertStringContainsString('doc='.$request->id, $notifyUrl, $label);
            $this->assertStringNotContainsString('/borrower/profile', $notifyUrl, $label);

            $cardUrl = $service->borrowerActionUrl($request);
            $this->assertStringContainsString($needle, $cardUrl, $label);
            $this->assertStringContainsString($needle2, $cardUrl, $label);
            if ($kind !== 'document') {
                $this->assertStringContainsString('solo=1', $cardUrl, $label);
            }
        }
    }

    public function test_own_national_id_request_opens_profile_and_returns_after_save(): void
    {
        Storage::fake('public');
        DocumentType::create(['code' => 'national_id_front', 'name' => 'National ID front', 'is_active' => true]);
        DocumentType::create(['code' => 'national_id_back', 'name' => 'National ID back', 'is_active' => true]);

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-DOC-NIDA',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gaspari',
            'last_name' => 'Leader',
            'phone' => '255712340201',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-NIDA',
            'name' => 'Installment',
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
            'application_number' => 'APP-DOC-NIDA',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $request = $service->create($application, $admin, 'Updated National ID');

        $this->assertFalse($service->assistantUploadsOnApplication($customer->fresh(), $request->fresh()));
        $actionUrl = $service->borrowerActionUrl($request->fresh(), $customer->fresh());
        $this->assertStringContainsString('focus=id_images', $actionUrl);
        $this->assertStringContainsString('solo=1', $actionUrl);
        $this->assertStringContainsString('return=', $actionUrl);
        $this->assertStringContainsString('profile-id-images', $actionUrl);

        $html = $this->actingAs($user)
            ->get(route('site.borrower.application', ['application' => $application->id, 'doc' => $request->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('focus=id_images', $html);
        $this->assertStringContainsString('profile-id-images', $html);
        $this->assertStringContainsString('kf-request-add', $html);
        $this->assertStringNotContainsString('doc-req-front-'.$request->id, $html);

        $this->actingAs($user)
            ->post(route('site.borrower.application.document-requests.store', [$application, $request]), [
                'front' => UploadedFile::fake()->image('nida-front-own.jpg'),
                'back' => UploadedFile::fake()->image('nida-back-own.jpg'),
            ])
            ->assertRedirect($actionUrl);

        $this->assertSame('pending', $request->fresh()->status);

        $return = route('site.borrower.application', $application);
        $this->actingAs($user)
            ->put(route('site.borrower.profile.update', [
                'section' => 'personal',
                'return' => $return,
                'application' => $application->id,
                'solo' => 1,
            ]), [
                'focus' => 'id_images',
                'return' => $return,
                'national_id_front' => UploadedFile::fake()->image('nida-front.jpg'),
                'national_id_back' => UploadedFile::fake()->image('nida-back.jpg'),
            ])
            ->assertRedirect($return);

        $this->assertSame('uploaded', $request->fresh()->status);
        $this->assertEquals(2, CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->whereNull('loan_application_id')
            ->count());
    }
}
