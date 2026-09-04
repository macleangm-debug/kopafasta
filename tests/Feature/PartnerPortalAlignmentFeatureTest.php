<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerProfileService;
use App\Services\PartnerWalletService;
use App\Services\PinService;
use Database\Seeders\PartnerDemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPortalAlignmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_insurance_company_profile_skips_face_and_activity(): void
    {
        $partner = Vendor::create([
            'vendor_number' => 'PT-IN-TZ-PROF',
            'name' => 'Cover Co',
            'category' => 'insurance',
            'applicant_category' => 'company',
            'roles' => ['insurance'],
            'status' => 'active',
            'phone' => '255712349010',
            'email' => 'cover@example.test',
            'legal_name' => 'Cover Co Ltd',
            'registration_number' => 'REG-1',
            'tin' => 'TIN-1',
        ]);

        $this->assertTrue($partner->isCompanyApplicant());
        $sections = app(PartnerProfileService::class)->sectionsFor($partner);
        $this->assertSame(['personal', 'company', 'residence', 'payment'], $sections);
        $this->assertNotContains('face', $sections);
        $this->assertNotContains('activity', $sections);
    }

    public function test_valuer_individual_keeps_face_not_company(): void
    {
        $partner = Vendor::create([
            'vendor_number' => 'PT-VA-TZ-IND',
            'name' => 'Valuer Person',
            'category' => 'valuer',
            'applicant_category' => 'individual',
            'roles' => ['valuer'],
            'status' => 'active',
            'phone' => '255712349011',
        ]);

        $this->assertTrue($partner->isIndividualApplicant());
        $sections = app(PartnerProfileService::class)->sectionsFor($partner);
        $this->assertSame(['personal', 'face', 'residence', 'payment'], $sections);
        $types = app(PartnerProfileService::class)->documentTypesFor($partner);
        $this->assertArrayHasKey('national_id_front', $types);
        $this->assertArrayNotHasKey('brela', $types);
    }

    public function test_company_contact_person_is_not_trading_name(): void
    {
        $partner = Vendor::create([
            'vendor_number' => 'PT-IN-TZ-CP',
            'name' => 'Aventris Insurance',
            'category' => 'insurance',
            'applicant_category' => 'company',
            'roles' => ['insurance'],
            'status' => 'active',
            'phone' => '255712349099',
            'email' => 'info@aventris.test',
            'metadata' => [
                'contact_person' => ['name' => 'Jane Doe'],
                'identity' => ['national_id' => '12345678901234567890', 'no_physical_nida_card' => true],
            ],
        ]);

        $this->assertSame('Jane Doe', $partner->contactPersonName());
        $this->assertNotSame($partner->name, $partner->contactPersonName());
        $types = app(PartnerProfileService::class)->documentTypesFor($partner);
        $this->assertArrayHasKey('brela', $types);
        $this->assertArrayHasKey('national_id_front', $types);
    }

    public function test_insurance_dashboard_shows_wallet_and_empty_jobs_copy(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);
        $user = User::query()->where('email', 'like', '%insurance%')->first()
            ?? User::factory()->create(['role' => 'vendor']);

        if (! Vendor::query()->where('user_id', $user->id)->exists()) {
            Vendor::create([
                'vendor_number' => 'PT-IN-TZ-DASH',
                'name' => 'Insure Dash',
                'category' => 'insurance',
                'applicant_category' => 'company',
                'roles' => ['insurance'],
                'status' => 'active',
                'phone' => '255712349012',
                'user_id' => $user->id,
                'activated_at' => now(),
            ]);
            app(PinService::class)->setPin($user, '1234');
        }

        $vendor = Vendor::query()->where('user_id', $user->id)->firstOrFail();
        $wallet = app(PartnerWalletService::class)->summary($vendor);
        $this->assertSame('insurance_premium', $wallet['source_type']);

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.wallet_available'), false)
            ->assertSee(__('site.partner_portal.no_assigned_tasks'), false);

        $this->actingAs($user)
            ->get(route('site.partner.tasks'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.cover_jobs_empty_title'), false)
            ->assertDontSee('<thead', false);

        $this->actingAs($user)
            ->get(route('site.partner.support'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.faq_title'), false)
            ->assertSee('Insure It', false);
    }
}
