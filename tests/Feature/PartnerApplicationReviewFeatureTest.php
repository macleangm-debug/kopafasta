<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use App\Models\User;
use App\Services\PartnerApplicationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerApplicationReviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(array $overrides = []): PartnerApplication
    {
        return PartnerApplication::create(array_merge([
            'type'                => 'service',
            'partner_category'    => 'debt_collector',
            'applicant_category'  => 'company',
            'full_name'           => 'Amina Collector',
            'email'               => 'amina@example.com',
            'phone'               => '255712000111',
            'business_name'       => 'Amina Recovery Ltd',
            'legal_name'          => 'Amina Recovery Limited',
            'registration_number' => 'BRELA-12345',
            'tin'                 => '100-200-300',
            'region'              => 'Dar es Salaam',
            'coverage_regions'    => ['Dar es Salaam', 'Pwani'],
            'message'             => 'We cover DSM and Coast.',
            'status'              => 'pending',
        ], $overrides));
    }

    public function test_dossier_flags_missing_required_documents(): void
    {
        $application = $this->makeApplication();

        $review = app(PartnerApplicationReviewService::class)->dossier($application);

        $this->assertSame('Amina Collector', $review['applicant']['full_name']);
        $this->assertSame('Collection partner', $review['applicant']['category_label']);
        $this->assertSame('Amina Recovery Ltd', $review['business']['trading_name']);
        $this->assertSame(5, $review['required_docs']);
        $this->assertSame(0, $review['satisfied_docs']);
        $this->assertSame(0, $review['checklist_progress']);
        $this->assertEmpty($review['documents']);
        $this->assertNull($review['identity']['national_id_front']);
        $this->assertArrayHasKey('incomplete_docs', $review['rejection_reason_codes']);
    }

    public function test_dossier_marks_documents_present_and_computes_progress(): void
    {
        Storage::fake('public');

        $application = $this->makeApplication();

        foreach (['brela', 'tin_certificate', 'business_licence', 'national_id_front', 'national_id_back'] as $docType) {
            PartnerApplicationDocument::create([
                'partner_application_id' => $application->id,
                'doc_type'               => $docType,
                'file_path'              => "partner-applications/{$application->id}/{$docType}.jpg",
                'original_name'          => "{$docType}.jpg",
                'mime'                   => 'image/jpeg',
                'size_bytes'             => 1024,
            ]);
        }

        $review = app(PartnerApplicationReviewService::class)->dossier($application->fresh());

        $this->assertSame(5, $review['required_docs']);
        $this->assertSame(5, $review['satisfied_docs']);
        $this->assertSame(100, $review['checklist_progress']);
        $this->assertCount(5, $review['documents']);
        $this->assertNotNull($review['identity']['national_id_front']);
        $this->assertTrue($review['identity']['national_id_front']['is_image']);
    }

    public function test_individual_applicant_only_requires_national_id(): void
    {
        $application = $this->makeApplication(['applicant_category' => 'individual']);

        $review = app(PartnerApplicationReviewService::class)->dossier($application);

        $this->assertSame(2, $review['required_docs']);
        $this->assertSame(['national_id_front', 'national_id_back'], array_column($review['checklist'], 'key'));
    }

    public function test_admin_can_view_rebuilt_dossier_page(): void
    {
        $application = $this->makeApplication();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertSee('Partner 360', false)
            ->assertSee('Amina Collector', false)
            ->assertSee('National ID', false)
            ->assertSee('Review decision', false)
            ->assertSee('Request information', false);
    }

    public function test_admin_can_request_structured_information(): void
    {
        $application = $this->makeApplication();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'needs_info',
                'request_kind' => 'document',
                'request_type' => 'proof_of_address',
                'request_explanation' => 'Clear utility bill please.',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('needs_info', $application->status);
        $this->assertSame('Clear utility bill please.', $application->admin_notes);
        $requests = $application->payload['info_requests'] ?? [];
        $this->assertCount(1, $requests);
        $this->assertSame('proof_of_address', $requests[0]['type']);
        $this->assertSame('requested', $requests[0]['status']);
        $this->assertNull($application->partner_id);
    }

    public function test_applicant_can_fulfill_document_request_on_tracking_card(): void
    {
        Storage::fake('public');

        $application = $this->makeApplication([
            'status' => 'needs_info',
            'payload' => [
                'info_requests' => [[
                    'id' => 'req-address-1',
                    'kind' => 'document',
                    'type' => 'proof_of_address',
                    'label' => 'Proof of address',
                    'explanation' => null,
                    'status' => 'requested',
                    'requested_at' => now()->toIso8601String(),
                    'document_ids' => [],
                ]],
            ],
        ]);

        $this->get(route('site.partners.apply.tracking', ['phone' => $application->phone]))
            ->assertOk()
            ->assertSee('Proof of address', false)
            ->assertSee(__('site.partner_apply.track_needs_info_title'), false)
            ->assertSee(__('site.partner_apply.track_check_another'), false)
            ->assertDontSee(__('site.partner_apply.track_submit'), false);

        $this->post(route('site.partners.apply.fulfill', $application), [
            'phone' => $application->phone,
            'request_id' => 'req-address-1',
            'document' => UploadedFile::fake()->image('address.jpg'),
        ])->assertRedirect(route('site.partners.apply.tracking', ['phone' => $application->phone]));

        $application->refresh();
        $this->assertSame('pending', $application->status);
        $this->assertSame('submitted', $application->payload['info_requests'][0]['status']);
        $this->assertTrue($application->documents()->where('doc_type', 'proof_of_address')->exists());
    }

    public function test_fulfill_accepts_document_holder_page_array_payload(): void
    {
        Storage::fake('public');

        $application = $this->makeApplication([
            'status' => 'needs_info',
            'payload' => [
                'info_requests' => [[
                    'id' => 'req-address-2',
                    'kind' => 'document',
                    'type' => 'proof_of_address',
                    'label' => 'Proof of address',
                    'status' => 'requested',
                    'requested_at' => now()->toIso8601String(),
                    'document_ids' => [],
                ]],
            ],
        ]);

        $this->post(route('site.partners.apply.fulfill', $application), [
            'phone' => $application->phone,
            'request_id' => 'req-address-2',
            'document' => [UploadedFile::fake()->image('address-page.jpg')],
        ])->assertRedirect(route('site.partners.apply.tracking', ['phone' => $application->phone]));

        $application->refresh();
        $this->assertSame('submitted', $application->payload['info_requests'][0]['status']);
        $this->assertTrue($application->documents()->where('doc_type', 'proof_of_address')->exists());
    }

    public function test_decline_stores_public_description_without_internal_prefix_leak(): void
    {
        $application = $this->makeApplication();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'rejected',
                'admin_notes' => 'Docs look forged.',
                'rejection_reason' => 'invalid_id',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('Docs look forged.', $application->admin_notes);
        $this->assertStringNotContainsString('Invalid or unclear national ID', $application->admin_notes);
        $this->assertSame('invalid_id', $application->payload['decline']['reason_code'] ?? null);
        $this->assertNull($application->partner_id);
    }

    public function test_approve_blocked_until_match_resolved_then_links_existing_partner(): void
    {
        $existing = Partner::create([
            'vendor_number' => 'PT-EXIST-001',
            'name' => 'Existing Recovery',
            'phone' => '255712000111',
            'email' => 'existing@example.com',
            'category' => 'debt_collector',
            'roles' => ['debt_collector'],
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'phone' => '255712000111',
            'email' => 'amina@example.com',
            'full_name' => 'Existing Recovery',
            'partner_category' => 'affiliate',
            'type' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->post(route('admin.partner-applications.match-resolution', $application), [
                'decision' => 'link',
                'partner_id' => $existing->id,
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($existing->id, $application->partner_id);
        $this->assertSame(1, Partner::query()->where('phone', '255712000111')->count());
        $this->assertTrue($existing->fresh()->hasPartnerRole('affiliate'));
    }

    public function test_same_person_link_adds_affiliate_workspace_to_insurance_partner_without_new_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Aventris Insurance',
            'phone' => '255715222132',
            'email' => 'info@aventris.co.tz',
            'role' => 'vendor',
        ]);
        $existing = Partner::create([
            'vendor_number' => 'PT-IN-TZ-C9VE',
            'name' => 'Aventris Insurance',
            'phone' => '255715222132',
            'email' => 'info@aventris.co.tz',
            'category' => 'insurance',
            'roles' => ['insurance'],
            'user_id' => $user->id,
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $application = $this->makeApplication([
            'full_name' => 'Maclean Mwaijonga',
            'phone' => '255715222132',
            'email' => 'geofrey.maclean@gmail.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertSee('Link identity', false)
            ->assertSee('Phone matched', false)
            ->assertSee('Aventris Insurance', false)
            ->getContent();
        $this->assertStringContainsString('another workspace', $html);

        // Name differs from existing Partner — Owner must confirm despite conflicts (UI does this).
        $this->actingAs($admin, 'admin')
            ->post(route('admin.partner-applications.match-resolution', $application), [
                'decision' => 'link',
                'partner_id' => $existing->id,
                'confirm_despite_conflicts' => true,
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $existing->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($existing->id, $application->partner_id);
        $this->assertSame($user->id, $existing->user_id);
        $this->assertTrue($existing->hasPartnerRole('insurance'));
        $this->assertTrue($existing->hasPartnerRole('affiliate'));
        $this->assertSame(1, Partner::query()->where('phone', '255715222132')->count());
        $this->assertSame(1, User::query()->where('phone', '255715222132')->count());
        $this->assertSame($existing->id, $application->payload['identity_link']['partner_id'] ?? null);
        $this->assertSame($admin->id, $application->payload['identity_link']['linked_by'] ?? null);
        $this->assertSame('affiliate', $application->payload['identity_link']['added_role'] ?? null);

        $ws = app(\App\Services\PartnerWorkspaceService::class);
        $this->assertTrue($ws->canSwitch($existing));
        $labels = collect($ws->workspaces($existing))->pluck('label')->all();
        $this->assertContains(__('site.partner_workspace.affiliate'), $labels);
        $this->assertContains(__('site.partner_workspace.insurance'), $labels);
        $keys = collect($ws->workspaces($existing))->pluck('key')->all();
        $this->assertContains('affiliate', $keys);
        $this->assertContains('service', $keys);
    }

    public function test_convert_to_partner_refuses_duplicate_phone_without_link(): void
    {
        Partner::create([
            'vendor_number' => 'PT-IN-TZ-DUP',
            'name' => 'Existing Phone Owner',
            'phone' => '255715999888',
            'email' => 'owner@example.com',
            'category' => 'insurance',
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'phone' => '255715999888',
            'email' => 'new.applicant@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
            'status' => 'approved',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\PartnerEnrollmentService::class)->convertToPartner($application);
    }

    public function test_link_identity_refuses_partner_login_that_is_also_borrower(): void
    {
        $borrowerUser = User::factory()->create([
            'name' => 'Borrower Maclean',
            'phone' => '255715222200',
            'email' => 'borrower.link@example.com',
            'role' => 'borrower',
        ]);
        \App\Models\Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'C-LINK-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'Maclean',
            'phone' => '255715222200',
            'country_code' => 'TZ',
        ]);
        // Corrupted dual identity: Partner row points at Borrower User — must never Link.
        $partner = Partner::create([
            'vendor_number' => 'PT-BAD-BORROWER',
            'name' => 'Bad Dual Partner',
            'phone' => '255715222200',
            'email' => 'bad.dual@example.com',
            'category' => 'insurance',
            'roles' => ['insurance'],
            'user_id' => $borrowerUser->id,
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $application = $this->makeApplication([
            'full_name' => 'Affiliate Applicant',
            'phone' => '255715222200',
            'email' => 'affiliate.applicant@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->post(route('admin.partner-applications.match-resolution', $application), [
                'decision' => 'link',
                'partner_id' => $partner->id,
                'confirm_despite_conflicts' => true,
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasErrors('decision');

        $this->assertNull($application->fresh()->payload['match_resolutions'][(string) $partner->id]['decision'] ?? null);
        $this->assertFalse($partner->fresh()->hasPartnerRole('affiliate'));
    }

    public function test_approve_blocked_when_phone_belongs_to_borrower_not_partner(): void
    {
        $borrowerUser = User::factory()->create([
            'name' => 'Solo Borrower',
            'phone' => '255715333444',
            'email' => 'solo.borrower@example.com',
            'role' => 'borrower',
        ]);
        \App\Models\Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'C-SOLO-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Solo',
            'last_name' => 'Borrower',
            'phone' => '255715333444',
            'country_code' => 'TZ',
        ]);

        $application = $this->makeApplication([
            'phone' => '255715333444',
            'email' => 'new.partner.app@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertSame(0, Partner::query()->where('phone', '255715333444')->count());
        $this->assertSame('borrower', $borrowerUser->fresh()->role);
        $this->assertNull($borrowerUser->fresh()->partner);
    }

    public function test_convert_to_partner_refuses_borrower_phone(): void
    {
        $borrowerUser = User::factory()->create([
            'phone' => '255715555666',
            'email' => 'borrower.phone@example.com',
            'role' => 'borrower',
        ]);
        \App\Models\Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'C-PH-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Phone',
            'last_name' => 'Borrower',
            'phone' => '255715555666',
            'country_code' => 'TZ',
        ]);

        $application = $this->makeApplication([
            'phone' => '255715555666',
            'email' => 'partner.from.borrower.phone@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
            'status' => 'approved',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\PartnerEnrollmentService::class)->convertToPartner($application);
    }

    public function test_keep_separate_records_resolution_and_blocks_shared_email_approve(): void
    {
        $existing = Partner::create([
            'vendor_number' => 'PT-AF-TZ-DIMM',
            'name' => 'Said Mbelemba',
            'phone' => '255255255',
            'email' => 'shared@example.com',
            'category' => 'affiliate',
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'full_name' => 'Maclean Mwaijonga',
            'phone' => '255715222132',
            'email' => 'shared@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->post(route('admin.partner-applications.match-resolution', $application), [
                'decision' => 'keep_separate',
                'partner_id' => $existing->id,
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('keep_separate', $application->payload['match_resolutions'][(string) $existing->id]['decision'] ?? null);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->partner_id);
    }

    public function test_keep_separate_then_change_applicant_email_clears_uniqueness_blocker(): void
    {
        $existing = Partner::create([
            'vendor_number' => 'PT-AF-TZ-DIMM',
            'name' => 'Said Mbelemba',
            'phone' => '255255255',
            'email' => 'shared@example.com',
            'category' => 'affiliate',
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'full_name' => 'Maclean Mwaijonga',
            'phone' => '255715222132',
            'email' => 'shared@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partner-applications.match-resolution', $application), [
                'decision' => 'keep_separate',
                'partner_id' => $existing->id,
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertSee('Email needs attention', false)
            ->assertSee('Change applicant email', false)
            ->assertSee('Open Said Mbelemba', false)
            ->assertSee('shared@example.com', false)
            ->getContent();

        $this->assertStringContainsString('A unique email is required', $html);
        $this->assertStringContainsString('emailBlocked\u0022:true', $html);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->post(route('admin.partner-applications.change-applicant-email', $application), [
                'email' => 'maclean.unique@example.com',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHas('status', 'Email updated. No possible Partner matches remain.');

        $application->refresh();
        $this->assertSame('maclean.unique@example.com', $application->email);
        $this->assertSame('shared@example.com', $application->payload['submitted_email'] ?? null);
        $this->assertSame('shared@example.com', $application->payload['email_history'][0]['from'] ?? null);
        $this->assertSame('duplicate_resolution', $application->payload['email_history'][0]['context'] ?? null);

        $matches = app(\App\Services\PartnerMatchResolutionService::class)->matchesFor($application);
        $this->assertSame([], $matches);
        $blockers = app(\App\Services\PartnerMatchResolutionService::class)->emailUniquenessBlockers($application);
        $this->assertSame([], $blockers);

        // Collision cleared — Approve is no longer uniqueness-blocked. Do not Approve here.
        $show = $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertDontSee('Email needs attention', false)
            ->getContent();
        $this->assertStringContainsString('emailBlocked\u0022:false', $show);
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->partner_id);
    }

    public function test_change_applicant_email_rejects_existing_partner_email(): void
    {
        Partner::create([
            'vendor_number' => 'PT-AF-TZ-OTHER',
            'name' => 'Other Affiliate',
            'phone' => '255700000001',
            'email' => 'taken@example.com',
            'category' => 'affiliate',
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'email' => 'original@example.com',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.partner-applications.show', $application))
            ->post(route('admin.partner-applications.change-applicant-email', $application), [
                'email' => 'taken@example.com',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHasErrors('email');

        $this->assertSame('original@example.com', $application->fresh()->email);
    }

    public function test_email_change_success_message_reports_remaining_phone_match(): void
    {
        Partner::create([
            'vendor_number' => 'PT-AF-TZ-DIMM',
            'name' => 'Said Mbelemba',
            'phone' => '255255255',
            'email' => 'shared@example.com',
            'category' => 'affiliate',
            'status' => 'active',
        ]);
        Partner::create([
            'vendor_number' => 'PT-IN-TZ-C9VE',
            'name' => 'Aventris Insurance',
            'phone' => '255715222132',
            'email' => 'info@aventris.co.tz',
            'category' => 'insurance',
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'full_name' => 'Maclean Mwaijonga',
            'phone' => '255715222132',
            'email' => 'shared@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partner-applications.change-applicant-email', $application), [
                'email' => 'geofrey.maclean@gmail.com',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application))
            ->assertSessionHas('status', 'Email updated. 1 possible Partner match still needs review.');

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertSee('Possible existing Partner found', false)
            ->assertSee('Phone matched', false)
            ->assertSee('Aventris Insurance', false)
            ->assertDontSee('Said Mbelemba', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Possible existing Partner found'));
        $matches = app(\App\Services\PartnerMatchResolutionService::class)->matchesFor($application->fresh());
        $this->assertCount(1, $matches);
        $this->assertSame(['phone'], $matches[0]['matched_fields']);
        $this->assertFalse($matches[0]['resolved']);
    }

    public function test_review_match_panel_shows_applicant_vs_existing_comparison(): void
    {
        Partner::create([
            'vendor_number' => 'PT-AF-TZ-DIMM',
            'name' => 'Said Mbelemba',
            'phone' => '255255255',
            'email' => 'shared@example.com',
            'category' => 'affiliate',
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $application = $this->makeApplication([
            'full_name' => 'Maclean Mwaijonga',
            'phone' => '255715222132',
            'email' => 'shared@example.com',
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertSee('Possible existing Partner found', false)
            ->assertSee('Review match', false)
            ->assertSee('Maclean Mwaijonga', false)
            ->assertSee('Said Mbelemba', false)
            ->assertSee('Email matched', false)
            ->assertSee('Use existing Affiliate', false)
            ->assertSee('Different people → Keep separate', false)
            ->assertSee('No new Affiliate account will be created', false)
            ->assertSee('Open ', false)
            ->assertSee('Request information →', false)
            ->assertSee('partnerApplicationDecision(JSON.parse(', false)
            ->assertDontSee('Merge Partner', false)
            ->assertDontSee('Open existing Partner', false)
            ->assertDontSee('Switch workspace', false)
            ->getContent();

        // Needs Attention must not duplicate the Review Decision match warning.
        $this->assertSame(1, substr_count($html, 'Possible existing Partner found'));
        $this->assertStringContainsString('currentMatch().existing?.name', $html);

        $this->assertStringContainsString('Phone', $html);
        $this->assertStringContainsString('255715222132', $html);
        $this->assertStringContainsString('255255255', $html);
    }

    public function test_admin_can_request_document_replacement_without_declining(): void
    {
        Storage::fake('public');
        $application = $this->makeApplication();
        PartnerApplicationDocument::create([
            'partner_application_id' => $application->id,
            'doc_type' => 'national_id_front',
            'file_path' => "partner-applications/{$application->id}/front.jpg",
            'original_name' => 'front.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'needs_info',
                'request_kind' => 'document',
                'request_type' => 'national_id_front',
                'request_mode' => 'replace',
                'replace_reason' => 'not_clear',
                'request_explanation' => 'Please retake in good lighting.',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('needs_info', $application->status);
        $this->assertNull($application->partner_id);
        $req = $application->payload['info_requests'][0];
        $this->assertSame('replace', $req['mode']);
        $this->assertSame('national_id_front', $req['type']);
        $this->assertSame('not_clear', $req['replace_reason']);
        $this->assertStringContainsString('not clear', strtolower((string) $req['explanation']));
    }

    public function test_partners_list_view_redirects_to_canonical_partner_360_when_application_linked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Partner::create([
            'vendor_number' => 'AF-CANON-001',
            'name' => 'Canonical Affiliate',
            'phone' => '255712000999',
            'category' => 'affiliate',
            'status' => 'active',
        ]);
        $application = $this->makeApplication([
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
            'status' => 'approved',
            'partner_id' => $partner->id,
            'phone' => '255712000999',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertRedirect(route('admin.partner-applications.show', $application));
    }
}
