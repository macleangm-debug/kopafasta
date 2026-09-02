<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use App\Services\ScreeningNextActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentRequestExactJourneyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_can_open_member_national_id_request_and_upload_front_back_in_english_and_swahili(): void
    {
        Storage::fake('public');
        DocumentType::create(['code' => 'national_id_front', 'name' => 'National ID front', 'is_active' => true]);
        DocumentType::create(['code' => 'national_id_back', 'name' => 'National ID back', 'is_active' => true]);

        [$leaderUser, $application, $request, $member] = $this->groupNationalIdRequest();
        $service = app(ApplicationDocumentRequestService::class);

        $notifyUrl = $service->borrowerNotificationUrl($request->fresh(), $application->customer);
        $this->assertStringContainsString('doc='.$request->id, $notifyUrl);
        $this->assertStringContainsString('#request-'.$request->id, $notifyUrl);

        $en = $this->actingAs($leaderUser)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.application', ['application' => $application->id, 'doc' => $request->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('For Rogathe Nyelle', $en);
        $this->assertStringContainsString('You can add this for Rogathe Nyelle', $en);
        $this->assertStringContainsString('Rogathe Nyelle', $en);
        $this->assertStringContainsString('Front', $en);
        $this->assertStringContainsString('Back', $en);
        $this->assertStringContainsString('Take ID photos', $en);
        $this->assertStringContainsString('Take the FRONT of the national ID', $en);
        $this->assertStringContainsString('Take the BACK of the national ID', $en);
        $this->assertStringContainsString('Landscape', $en);
        $this->assertStringContainsString('Portrait', $en);
        $this->assertStringContainsString('valuationCamera', $en);
        $this->assertStringContainsString('subjectName', $en);
        $this->assertStringContainsString('kf-cam-guide', $en);
        $this->assertStringContainsString(__('site.partner_portal.valuation_camera_close'), $en);
        $this->assertStringNotContainsString('is-rotated', $en);
        $this->assertStringNotContainsString('needsPreviewRotate', $en);
        $this->assertStringContainsString('doc-req-front-'.$request->id, $en);
        $this->assertStringContainsString('doc-req-back-'.$request->id, $en);
        $this->assertStringNotContainsString('must update this in their profile', $en);
        $this->assertStringNotContainsString('Waiting for group leader', $en);

        $sw = $this->actingAs($leaderUser)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.application', ['application' => $application->id, 'doc' => $request->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Kwa Rogathe Nyelle', $sw);
        $this->assertStringContainsString('Unaweza kuongeza hii kwa Rogathe Nyelle', $sw);
        $this->assertStringContainsString('Mbele', $sw);
        $this->assertStringContainsString('Nyuma', $sw);
        $this->assertStringContainsString('Piga picha za kitambulisho', $sw);
        $this->assertStringContainsString('Mlalo', $sw);
        $this->assertStringContainsString('Wima', $sw);
        $this->assertStringContainsString('Funga kamera', $sw);

        $this->actingAs($leaderUser)
            ->post(route('site.borrower.application.document-requests.store', [$application, $request]), [
                'front' => UploadedFile::fake()->image('nida-front-test.jpg'),
                'back' => UploadedFile::fake()->image('nida-back-test.jpg'),
            ])
            ->assertRedirect();

        $this->assertSame('uploaded', $request->fresh()->status);
        $this->assertEquals(2, CustomerDocument::query()
            ->where('customer_id', $member->id)
            ->where('loan_application_document_request_id', $request->id)
            ->count());

        $receipt = $this->actingAs($leaderUser)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.application', ['application' => $application->id, 'doc' => $request->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Submitted', $receipt);
        $this->assertStringContainsString('Kopafasta will review this document', $receipt);
    }

    public function test_group_peer_cannot_upload_another_members_national_id(): void
    {
        Storage::fake('public');
        DocumentType::create(['code' => 'national_id_front', 'name' => 'National ID front', 'is_active' => true]);
        DocumentType::create(['code' => 'national_id_back', 'name' => 'National ID back', 'is_active' => true]);

        [, $application, $request, $member] = $this->groupNationalIdRequest();
        $peerUser = User::factory()->create(['role' => 'borrower']);
        $peer = Customer::create([
            'user_id' => $peerUser->id,
            'customer_number' => 'CU-PEER-NID',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Peer',
            'phone' => '255712340103',
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $application->loan_group_id,
            'customer_id' => $peer->id,
            'loan_application_id' => $application->id,
            'role' => 'member',
            'requested_amount' => 450_000,
            'sort_order' => 3,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);

        $service = app(ApplicationDocumentRequestService::class);
        $this->assertFalse($service->customerCanFulfillRequest($peer->fresh(), $request->fresh()));
        $this->assertFalse($service->borrowerIsAssisting($peer->fresh(), $request->fresh()));
        $this->assertFalse($service->borrowerIsAssisting($member->fresh(), $request->fresh()));

        $this->actingAs($peerUser)
            ->post(route('site.borrower.application.document-requests.store', [$application, $request]), [
                'front' => UploadedFile::fake()->image('nida-front-peer.jpg'),
                'back' => UploadedFile::fake()->image('nida-back-peer.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_collateral_asset_card_include_does_not_require_a_component_slot(): void
    {
        $html = view('site.borrower.loan-profile._collateral_asset_card', [
            'selected' => null,
            'typeIcons' => CustomerAsset::typeIcons(),
            'showInsured' => false,
            'sourceLabel' => null,
        ])->render();

        $this->assertIsString($html);
    }

    public function test_upload_moves_screening_from_waiting_to_do_now_and_resumes_the_national_id_item(): void
    {
        Storage::fake('public');
        DocumentType::create(['code' => 'national_id_front', 'name' => 'National ID front', 'is_active' => true]);
        DocumentType::create(['code' => 'national_id_back', 'name' => 'National ID back', 'is_active' => true]);

        [$leaderUser, $application, $request, $member] = $this->groupNationalIdRequest();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $application->update(['assigned_analyst_id' => $admin->id]);
        app(ScreeningNextActionService::class)->markStarted($application->fresh());

        $waiting = app(ScreeningNextActionService::class)->forApplication($application->fresh(), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_WAITING, $waiting['bucket']);

        $this->actingAs($leaderUser)
            ->post(route('site.borrower.application.document-requests.store', [$application, $request]), [
                'front' => UploadedFile::fake()->image('nida-front-test.jpg'),
                'back' => UploadedFile::fake()->image('nida-back-test.jpg'),
            ])
            ->assertRedirect();

        $after = app(ScreeningNextActionService::class)->forApplication($application->fresh(['documentRequests']), $admin);
        $this->assertSame(ScreeningNextActionService::BUCKET_DO_NOW, $after['bucket']);
        $this->assertSame('continue', $after['cta_kind']);
        $this->assertSame('Continue Reviewing', $after['cta']);
        $this->assertSame('identity.id_document_quality', $after['step']['item_key'] ?? null);
        $this->assertSame('member', $after['step']['participant']['person'] ?? null);
        $this->assertSame((int) $request->loan_group_member_id, (int) ($after['step']['participant']['m'] ?? 0));
        $this->assertStringContainsString('at_item=identity.id_document_quality', $after['review_href']);
        $this->assertSame($member->id, (int) CustomerDocument::query()
            ->where('loan_application_document_request_id', $request->id)
            ->value('customer_id'));
    }

    /** @return array{0: User, 1: LoanApplication, 2: LoanApplicationDocumentRequest, 3: Customer} */
    private function groupNationalIdRequest(): array
    {
        $leaderUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($leaderUser, '1234');
        app(PinRecoveryChallengeService::class)->enroll($leaderUser, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);
        $leader = Customer::create([
            'user_id' => $leaderUser->id,
            'customer_number' => 'CU-LDR-NID',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gaspari',
            'last_name' => 'Leader',
            'phone' => '255712340101',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $member = Customer::create([
            'customer_number' => 'CU-MEM-NID',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Rogathe',
            'last_name' => 'Nyelle',
            'phone' => '255712340102',
        ]);
        $product = LoanProduct::create([
            'code' => 'GL-NID',
            'name' => 'Group Loan',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $leader->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-NID',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 900_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $group = LoanGroup::create([
            'group_number' => 'GRP-NID-1',
            'name' => 'NID Group',
            'leader_customer_id' => $leader->id,
            'primary_application_id' => $application->id,
            'status' => 'active',
            'target_member_count' => 2,
        ]);
        LoanGroupMember::create([
            'loan_group_id' => $group->id,
            'customer_id' => $leader->id,
            'loan_application_id' => $application->id,
            'role' => 'leader',
            'requested_amount' => 450_000,
            'sort_order' => 1,
            'onboarding_status' => 'complete',
            'underwriting_status' => 'pending',
        ]);
        $memberRow = LoanGroupMember::create([
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

        $admin = User::factory()->create(['role' => 'admin']);
        $request = app(ApplicationDocumentRequestService::class)->create(
            $application->fresh(),
            $admin,
            'Updated National ID',
            subjectKind: 'member',
            loanGroupMemberId: $memberRow->id,
        );
        $request->update([
            'checklist_item' => 'identity.id_document_quality',
            'gate' => 'identity',
            'request_reason' => 'National ID is not on this member\'s profile.',
        ]);

        return [$leaderUser, $application->fresh(), $request->fresh(), $member];
    }
}
