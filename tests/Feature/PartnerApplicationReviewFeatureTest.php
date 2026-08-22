<?php

namespace Tests\Feature;

use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use App\Models\User;
use App\Services\PartnerApplicationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Documents & checklist', false)
            ->assertSee('Amina Collector', false)
            ->assertSee('Missing', false);
    }

    public function test_admin_can_set_needs_info_status_with_notes(): void
    {
        $application = $this->makeApplication();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'needs_info',
                'admin_notes' => 'Please upload a clearer BRELA certificate.',
            ])
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $application->refresh();
        $this->assertSame('needs_info', $application->status);
        $this->assertSame('Please upload a clearer BRELA certificate.', $application->admin_notes);
        $this->assertNull($application->partner_id);
    }

    public function test_rejection_reason_is_prefixed_onto_admin_notes(): void
    {
        $application = $this->makeApplication();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'rejected',
                'admin_notes' => 'Docs look forged.',
                'rejection_reason' => 'invalid_id',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertStringContainsString('Invalid or unclear national ID', $application->admin_notes);
        $this->assertStringContainsString('Docs look forged.', $application->admin_notes);
    }
}
