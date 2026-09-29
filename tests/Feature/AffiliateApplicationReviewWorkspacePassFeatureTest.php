<?php

namespace Tests\Feature;

use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffiliateSettingsService;
use App\Services\PartnerApplicationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliateApplicationReviewWorkspacePassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_page_exposes_unemployed_dob_max_one_declaration_and_no_upload_modal(): void
    {
        $html = $this->get(route('site.affiliate.apply'))->assertOk()->getContent();
        $apply = file_get_contents(resource_path('views/site/affiliate/apply.blade.php'));

        $this->assertStringContainsString(__('site.affiliate_apply.occupations.unemployed'), $html);
        $this->assertStringContainsString('unemployed', $html);
        $this->assertStringContainsString("now()->subYears(18)->format('Y-m-d')", $apply);
        $this->assertStringNotContainsString('age_notice', $apply);
        $this->assertStringContainsString('data-no-saving', $apply);
        $this->assertStringContainsString('name="declaration_accepted"', $html);
        $this->assertStringNotContainsString('name="conduct_accepted"', $html);
        $this->assertStringContainsString('identity.full_name', $apply);
        $this->assertStringContainsString('declaration_items', $apply);
        $this->assertSame(1, substr_count($html, 'name="declaration_accepted"'));

        $sheet = file_get_contents(resource_path('views/components/site/sheet-select.blade.php'));
        $this->assertStringContainsString('otherInputDesktop', $sheet);
        $this->assertGreaterThanOrEqual(2, substr_count($sheet, 'selected !== otherValue'));
    }

    public function test_under_18_dob_is_blocked_server_side(): void
    {
        Storage::fake('public');
        Setting::set('affiliates.application_fee_required', false);

        $this->from(route('site.affiliate.apply'))
            ->post(route('site.affiliate.apply.post'), $this->completePayload([
                'date_of_birth' => now()->subYears(17)->format('Y-m-d'),
                'email' => 'under18@example.com',
            ]))
            ->assertRedirect(route('site.affiliate.apply'))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertNull(PartnerApplication::query()->where('email', 'under18@example.com')->first());
    }

    public function test_declaration_version_retained_and_unemployed_occupation_accepted(): void
    {
        Storage::fake('public');
        Setting::set('affiliates.application_fee_required', false);

        $this->post(route('site.affiliate.apply.post'), $this->completePayload([
            'email' => 'decl-v2@example.com',
            'occupation' => 'unemployed',
        ]))->assertRedirect();

        $application = PartnerApplication::query()->where('email', 'decl-v2@example.com')->first();
        $this->assertNotNull($application);
        $this->assertSame('unemployed', $application->payload['occupation'] ?? null);
        $this->assertSame('affiliate_applicant_declaration_v2', data_get($application->payload, 'declarations.applicant.version'));
        $this->assertTrue((bool) data_get($application->payload, 'declarations.applicant.accepted'));
        $this->assertNotEmpty(data_get($application->payload, 'declarations.applicant.accepted_at'));
        $this->assertArrayNotHasKey('conduct', $application->payload['declarations'] ?? []);
    }

    public function test_tracking_and_settings_expose_review_period(): void
    {
        Setting::set('affiliates.review_time_min', 3);
        Setting::set('affiliates.review_time_max', 5);
        Setting::set('affiliates.review_time_unit', 'business_days');

        $label = app(AffiliateSettingsService::class)->publicReviewPeriodLabel('en');
        $this->assertSame('3–5 business days', $label);

        $tracking = file_get_contents(resource_path('views/site/partners/tracking.blade.php'));
        $this->assertStringContainsString('track_review_period', $tracking);
        $this->assertStringContainsString('justSubmitted', $tracking);
        $this->assertStringNotContainsString('submittedOpen', $tracking);

        $settings = file_get_contents(resource_path('views/admin/settings/affiliates.blade.php'));
        $this->assertStringContainsString('review_time_min', $settings);
        $this->assertStringContainsString('review_time_max', $settings);
        $this->assertStringContainsString('Public review expectation', $settings);
    }

    public function test_admin_affiliate_application_opens_partner_360_shell(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $application = PartnerApplication::create([
            'type' => 'affiliate',
            'partner_category' => 'affiliate',
            'applicant_category' => 'individual',
            'full_name' => 'Maclean Mwaijonga',
            'email' => 'maclean-aff@example.com',
            'phone' => '255712000999',
            'business_name' => 'Maclean Mwaijonga',
            'region' => 'Dar es Salaam',
            'status' => 'pending',
            'payload' => [
                'identity' => [
                    'date_of_birth' => '1990-05-12',
                    'gender' => 'male',
                    'district' => 'Ilala',
                    'street' => 'Uhuru Street',
                ],
                'occupation' => 'unemployed',
                'channels' => ['whatsapp'],
                'monthly_reach' => '11-30',
                'how_heard' => 'Friend',
                'declarations' => [
                    'applicant' => [
                        'version' => 'affiliate_applicant_declaration_v2',
                        'accepted' => true,
                        'accepted_at' => now()->toIso8601String(),
                        'full_name' => 'Maclean Mwaijonga',
                        'date_of_birth' => '1990-05-12',
                        'gender' => 'male',
                    ],
                ],
            ],
        ]);

        PartnerApplicationDocument::create([
            'partner_application_id' => $application->id,
            'doc_type' => 'national_id_front',
            'file_path' => "partner-applications/{$application->id}/front.jpg",
            'original_name' => 'front.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);
        PartnerApplicationDocument::create([
            'partner_application_id' => $application->id,
            'doc_type' => 'national_id_back',
            'file_path' => "partner-applications/{$application->id}/back.jpg",
            'original_name' => 'back.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Partner 360', $html);
        $this->assertStringContainsString('Overview', $html);
        $this->assertStringContainsString('Application', $html);
        $this->assertStringContainsString('Identity &amp; Documents', $html);
        $this->assertStringContainsString('Commercial', $html);
        $this->assertStringContainsString('Activity', $html);
        $this->assertStringContainsString('NIDA Front', $html);
        $this->assertStringContainsString('Approve this Affiliate application?', $html);
        $this->assertStringContainsString('ordinary Affiliate', $html);

        $dossier = app(PartnerApplicationReviewService::class)->dossier($application->fresh());
        $this->assertSame('unemployed', $dossier['application_detail']['occupation']);
        $this->assertSame('affiliate_applicant_declaration_v2', $dossier['declaration']['version']);
        $this->assertNotEmpty($dossier['activity']);
    }

    public function test_affiliate_queue_route_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.index', ['type' => 'affiliate']))
            ->assertOk();
    }

    /** @param  array<string, mixed>  $overrides */
    private function completePayload(array $overrides = []): array
    {
        return array_merge([
            'applicant_category' => 'individual',
            'full_name' => 'Review Workspace Applicant',
            'email' => 'review-workspace@example.com',
            'phone' => '+255712345922',
            'date_of_birth' => '1990-05-12',
            'gender' => 'female',
            'district' => 'Ilala',
            'ward' => 'Kariakoo',
            'street' => 'Uhuru Street',
            'region' => 'Dar es Salaam',
            'occupation' => 'shop_owner',
            'sales_experience' => 'I sell airtime and assist customers daily.',
            'languages' => ['sw', 'en'],
            'previous_agent' => 'no',
            'why_affiliate' => 'I already advise customers on mobile money.',
            'acquisition_methods' => ['existing_customers', 'community'],
            'channels' => ['whatsapp'],
            'has_social_profile' => 'no',
            'monthly_reach' => '11-30',
            'how_heard' => 'friend',
            'first_10_customers' => 'I will start with my regular shop customers this month.',
            'registered_business' => 'no',
            'declaration_accepted' => '1',
            'doc_national_id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => UploadedFile::fake()->image('id-back.jpg'),
        ], $overrides);
    }
}
