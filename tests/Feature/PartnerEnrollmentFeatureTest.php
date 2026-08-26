<?php

namespace Tests\Feature;

use App\Models\PartnerApplication;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerEnrollmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partners_page_links_to_collection_enroll(): void
    {
        $this->get(route('site.partners'))
            ->assertOk()
            ->assertSee(route('site.partners.apply', 'debt_collector'), false)
            ->assertSee(__('site.partners.cta_enroll'), false);
    }

    public function test_collection_partner_can_submit_application_with_business_docs(): void
    {
        Storage::fake('public');

        $payload = [
            'partner_category' => 'debt_collector',
            'applicant_category' => 'company',
            'full_name' => 'Amina Collector',
            'email' => 'amina.collections@example.com',
            'phone' => '255712000111',
            'business_name' => 'Amina Recovery Ltd',
            'legal_name' => 'Amina Recovery Limited',
            'registration_number' => 'BRELA-12345',
            'tin' => '100-200-300',
            'region' => 'Dar es Salaam',
            'coverage_regions' => ['Dar es Salaam', 'Pwani'],
            'requested_roles' => ['debt_collector', 'auctioneer'],
            'message' => 'We cover DSM and Coast.',
            'collection_conduct_accepted' => '1',
            'doc_brela' => UploadedFile::fake()->create('brela.pdf', 120, 'application/pdf'),
            'doc_tin_certificate' => UploadedFile::fake()->create('tin.pdf', 80, 'application/pdf'),
            'doc_business_licence' => UploadedFile::fake()->create('licence.pdf', 90, 'application/pdf'),
            'doc_national_id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => UploadedFile::fake()->image('id-back.jpg'),
        ];

        $this->post(route('site.partners.apply.post'), $payload)
            ->assertRedirect(route('site.partners.apply.tracking', ['phone' => '255712000111']));

        $application = PartnerApplication::query()->first();
        $this->assertNotNull($application);
        $this->assertSame('service', $application->type);
        $this->assertSame('debt_collector', $application->partner_category);
        $this->assertSame(['debt_collector', 'auctioneer'], $application->requested_roles);
        $this->assertSame('BRELA-12345', $application->registration_number);
        $this->assertSame('100-200-300', $application->tin);
        $this->assertCount(5, $application->documents);
    }

    public function test_collection_partner_must_select_at_least_one_capability(): void
    {
        Storage::fake('public');

        $this->from(route('site.partners.apply', 'debt_collector'))
            ->post(route('site.partners.apply.post'), [
                'partner_category' => 'debt_collector',
                'applicant_category' => 'company',
                'full_name' => 'No Caps Collector',
                'email' => 'nocaps@example.com',
                'phone' => '255712000999',
                'business_name' => 'No Caps Ltd',
                'registration_number' => 'BRELA-000',
                'tin' => '000-000-000',
                'region' => 'Dodoma',
                'requested_roles' => [],
                'collection_conduct_accepted' => '1',
                'doc_brela' => UploadedFile::fake()->create('brela.pdf', 100, 'application/pdf'),
                'doc_tin_certificate' => UploadedFile::fake()->create('tin.pdf', 100, 'application/pdf'),
                'doc_business_licence' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf'),
                'doc_national_id_front' => UploadedFile::fake()->image('id-front.jpg'),
                'doc_national_id_back' => UploadedFile::fake()->image('id-back.jpg'),
            ])
            ->assertRedirect(route('site.partners.apply', 'debt_collector'))
            ->assertSessionHasErrors('requested_roles');
    }

    public function test_admin_can_approve_and_convert_application_to_partner(): void
    {
        Storage::fake('public');

        $this->post(route('site.partners.apply.post'), [
            'partner_category' => 'debt_collector',
            'applicant_category' => 'company',
            'full_name' => 'John Recovery',
            'email' => 'john.recovery@example.com',
            'phone' => '255713000222',
            'business_name' => 'John Recovery Co',
            'legal_name' => 'John Recovery Company Limited',
            'registration_number' => 'BRELA-999',
            'tin' => '111-222-333',
            'region' => 'Arusha',
            'requested_roles' => ['debt_collector', 'auctioneer'],
            'collection_conduct_accepted' => '1',
            'doc_brela' => UploadedFile::fake()->create('brela.pdf', 100, 'application/pdf'),
            'doc_tin_certificate' => UploadedFile::fake()->create('tin.pdf', 100, 'application/pdf'),
            'doc_business_licence' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf'),
            'doc_national_id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => UploadedFile::fake()->image('id-back.jpg'),
        ])->assertRedirect();

        $application = PartnerApplication::query()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.partner-applications.update', $application), [
                'status' => 'approved',
                'admin_notes' => 'Looks good',
            ])
            ->assertRedirect(route('admin.partners.show', $application->fresh()->partner_id));

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->partner_id);

        $partner = Vendor::findOrFail($application->partner_id);
        $this->assertSame('debt_collector', $partner->category);
        $this->assertSame(['debt_collector', 'auctioneer'], $partner->roles);
        $this->assertTrue($partner->hasPartnerRole('auctioneer'));
        $this->assertSame('John Recovery Co', $partner->name);
        $this->assertSame('BRELA-999', $partner->registration_number);
        $this->assertSame('111-222-333', $partner->tin);
        $this->assertNotNull($partner->activation_token);
        $this->assertNotEmpty($partner->vendor_number);
        $this->assertGreaterThanOrEqual(1, $partner->documents()->count());
    }

    public function test_vendor_register_normalizes_legacy_collection_category_alias(): void
    {
        // Direct register still accepts canonical debt_collector
        $this->post(route('site.register.vendor.post'), [
            'name' => 'Quick Collect',
            'category' => 'debt_collector',
            'email' => 'quick.collect@example.com',
            'phone' => '255714000333',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'email' => 'quick.collect@example.com',
            'category' => 'debt_collector',
        ]);
    }
}
