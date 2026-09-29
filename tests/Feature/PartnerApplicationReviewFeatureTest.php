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
            ->assertSee(__('site.partner_apply.track_needs_info_title'), false);

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

    public function test_approve_stays_on_partner_360_and_links_existing_partner(): void
    {
        $existing = Partner::create([
            'vendor_number' => 'PT-EXIST-001',
            'name' => 'Existing Recovery',
            'phone' => '255712000111',
            'email' => 'existing@example.com',
            'category' => 'debt_collector',
            'status' => 'active',
        ]);

        $application = $this->makeApplication([
            'phone' => '255712000111',
            'email' => 'amina@example.com',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($existing->id, $application->partner_id);
        $this->assertSame(1, Partner::query()->where('phone', '255712000111')->count());
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
