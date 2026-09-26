<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\CardVerificationService;
use App\Services\PartnerCodeService;
use App\Services\PartnerProfileService;
use App\Support\Celebration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateProfileCorrectnessPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_street_keeps_profile_below_100_percent(): void
    {
        $affiliate = $this->affiliateWithAlmostCompleteProfile();
        $profile = app(PartnerProfileService::class);

        $this->assertFalse($profile->isComplete($affiliate));
        $this->assertLessThan(100, $profile->completionPercent($affiliate));
        $this->assertGreaterThan(0, $profile->remainingItemCount($affiliate));
        $this->assertContains('street', array_column($profile->sectionGaps($affiliate, 'residence'), 'key'));
    }

    public function test_filling_street_reaches_100_and_celebrates_once(): void
    {
        $affiliate = $this->affiliateWithAlmostCompleteProfile();
        $profile = app(PartnerProfileService::class);

        $request = Request::create('/affiliate-portal/profile/residence', 'PUT', [
            'residence_region' => 'Dar es Salaam',
            'residence_district' => 'Ilala',
            'residence_street' => 'Mtaa kamili 12',
        ]);
        $first = $profile->updateSection($affiliate, 'residence', $request);
        $affiliate->refresh();

        $this->assertTrue($first['celebrate']);
        $this->assertTrue($profile->isComplete($affiliate));
        $this->assertSame(100, $profile->completionPercent($affiliate));
        $this->assertContains('profile_complete', Celebration::reasons());

        $again = $profile->updateSection($affiliate->fresh(), 'residence', $request);
        $this->assertFalse($again['celebrate']);
    }

    public function test_maproso_resolves_immediately_and_attributes_borrower(): void
    {
        $affiliate = $this->affiliateWithAlmostCompleteProfile();
        $affiliate->update([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'affiliate_lifecycle_status' => 'active',
        ]);
        app(\App\Services\AffiliateTermsService::class)->accept($affiliate->fresh(), Request::create('/terms', 'POST'));
        $affiliates = app(AffiliateService::class);
        $affiliates->updateCode($affiliate->fresh(), 'MAPROSO');
        $affiliate->refresh();

        $this->assertSame('MAPROSO', $affiliate->affiliate_code);
        $this->assertSame($affiliate->id, $affiliates->resolveByPublicCode('MAPROSO')?->id);

        $token = $affiliates->ensureReferralToken($affiliate->fresh());
        $this->get('/aff/MAPROSO')
            ->assertRedirect(route('site.register.borrower', ['aff' => $token]));

        $user = User::factory()->create(['role' => 'customer']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-AFF-MAP',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Map',
            'last_name' => 'Roso',
            'phone' => '255700000099',
        ]);

        $affiliates->attachAffiliate($customer, 'MAPROSO');
        $customer->refresh();

        $this->assertSame($affiliate->id, (int) $customer->affiliate_vendor_id);
    }

    public function test_partner_card_prefix_uses_shared_type_codes(): void
    {
        $codes = app(PartnerCodeService::class);
        $types = app(CardVerificationService::class)->types();

        $this->assertSame($codes->prefixFor('affiliate'), $types['affiliate']['prefix']);
        $this->assertSame($codes->prefixFor('supplier'), $types['supplier']['prefix']);
        $this->assertSame($codes->prefixFor('insurance'), $types['insurance']['prefix']);
        $this->assertStringStartsWith('PT-AF-', $types['affiliate']['prefix']);
    }

    public function test_affiliate_card_verifies_with_canonical_prefix(): void
    {
        $affiliate = $this->affiliateWithAlmostCompleteProfile();
        $affiliate->update(['partner_number' => 'PT-AF-TZ-MAP1']);
        $this->completeResidence($affiliate);

        $result = app(CardVerificationService::class)->lookup('affiliate', 'MAP1');

        $this->assertTrue($result['found']);
        $this->assertSame('PT-AF-TZ-MAP1', $result['id_display']);
    }

    public function test_partner_surfaces_do_not_use_borrower_apply_copy(): void
    {
        $confetti = file_get_contents(resource_path('views/components/site/celebration-confetti.blade.php'));
        $share = file_get_contents(resource_path('views/site/affiliate/share.blade.php'));
        $tabs = file_get_contents(resource_path('views/site/partner-account/_tabs.blade.php'));
        $borrowerTabs = file_get_contents(resource_path('views/site/borrower/profile/_tabs.blade.php'));
        $hero = file_get_contents(resource_path('views/site/partner-account/_shell.blade.php'));

        $this->assertStringContainsString('profile_complete_cta', $confetti);
        $this->assertStringNotContainsString("celebration.cta_apply", $confetti);
        $this->assertStringContainsString('data-kf-share-hero', $share);
        $this->assertStringNotContainsString('window.location.reload()', $share);
        $this->assertStringContainsString('sheetOpen', $tabs);
        $this->assertStringContainsString('menuOpen', $tabs);
        $this->assertStringContainsString('sheetOpen', $borrowerTabs);
        $this->assertStringContainsString('menuOpen', $borrowerTabs);
        $this->assertStringContainsString(':cta-url="$cardUrl"', $hero);
        $this->assertStringContainsString('hero_completion_cta', $hero);
    }

    public function test_tanzania_identity_defaults_to_nida_only(): void
    {
        $affiliate = $this->affiliateWithAlmostCompleteProfile();
        $types = app(PartnerProfileService::class)->allowedIdentityTypes($affiliate);

        $this->assertSame(['nida'], array_keys($types));
    }

    private function affiliateWithAlmostCompleteProfile(): Vendor
    {
        return Vendor::create([
            'name' => 'UAT Standard',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255700000011',
            'affiliate_code' => 'STDCODE',
            'partner_number' => 'AFF-UAT-STD',
            'applicant_category' => 'individual',
            'affiliate_kyc_status' => 'verified',
            'metadata' => [
                'identity' => [
                    'national_id' => '19880101123456789012',
                    'no_physical_nida_card' => true,
                ],
                'face_captures' => [
                    'front' => 'faces/front.jpg',
                    'left' => 'faces/left.jpg',
                    'right' => 'faces/right.jpg',
                ],
                'residence' => [
                    'region' => 'Dar es Salaam',
                    'district' => 'Ilala',
                ],
                'payout_account' => [
                    'type' => 'mobile_money',
                    'mobile_number' => '255700000011',
                ],
                'reference_contact' => [
                    'name' => 'Jane Doe',
                    'relationship' => 'sister',
                    'phone' => '255700000022',
                ],
            ],
        ]);
    }

    private function completeResidence(Vendor $affiliate): void
    {
        $meta = $affiliate->metadata ?? [];
        $meta['residence']['street'] = 'Mtaa kamili 12';
        $affiliate->update(['metadata' => $meta]);
    }
}
