<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateProfileHubTest extends TestCase
{
    use RefreshDatabase;

    protected function affiliatePartner(?User $user = null): Vendor
    {
        $user ??= User::factory()->create(['role' => 'vendor']);

        return Vendor::create([
            'user_id'       => $user->id,
            'vendor_number' => 'AFF-HUB-1',
            'name'          => 'Hub Affiliate',
            'category'      => 'affiliate',
            'status'        => 'active',
            'phone'         => '255712340001',
            'applicant_category' => 'individual',
        ]);
    }

    public function test_profile_hub_loads_with_category_cards(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $this->affiliatePartner($user);

        $this->actingAs($user)
            ->get(route('site.affiliate.profile'))
            ->assertOk()
            ->assertSee(__('site.partner_account.personal_section'), false)
            ->assertSee(__('site.partner_account.face_section'), false)
            ->assertSee(__('site.partner_account.residence_section'), false)
            ->assertSee(__('site.partner_account.payment_section'), false);
    }

    public function test_unknown_section_slug_is_rejected_by_route_constraint(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $this->affiliatePartner($user);

        $this->actingAs($user)
            ->get('/affiliate-portal/profile/bogus')
            ->assertNotFound();
    }

    public function test_personal_section_saves_nida_number_and_marks_identity_complete(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = $this->affiliatePartner($user);

        $this->actingAs($user)
            ->get(route('site.affiliate.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee(__('site.partner_account.nida_number'), false);

        $this->actingAs($user)
            ->put(route('site.affiliate.profile.update', ['section' => 'personal']), [
                'focus'                  => 'identity',
                'national_id'            => '19900101123456789012',
                'no_physical_nida_card'  => '1',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $identity = $vendor->metadata['identity'] ?? [];

        $this->assertSame('19900101-12345-67890-12', $identity['national_id']);
        $this->assertTrue($identity['no_physical_nida_card']);

        // Contact details were already present on the seeded vendor, and the
        // checkbox waives the physical card requirement, so personal is complete.
        $service = app(\App\Services\PartnerProfileService::class);
        $status = $service->sectionStatus($vendor, 'personal');
        $this->assertSame('complete', $status['status']);
        $this->assertTrue($status['complete']);
    }
}
