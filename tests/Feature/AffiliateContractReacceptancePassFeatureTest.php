<?php

namespace Tests\Feature;

use App\Models\PartnerAgreementAcceptance;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateEligibilityService;
use App\Services\AffiliateTermsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateContractReacceptancePassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_v1_acceptance_requires_current_contract_reacceptance(): void
    {
        Setting::set('affiliates.terms.content_revision', 0);
        Setting::set('affiliates.terms.version', 1);
        Setting::set('affiliates.terms.body_en', 'STALE HUB BODY {{affiliate_name}}');

        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-REACC-1',
            'name' => 'Reaccept Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712388001',
            'affiliate_code' => 'REACC1',
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);

        PartnerAgreementAcceptance::query()->create([
            'partner_id' => $affiliate->id,
            'partner_type' => 'affiliate',
            'agreement_key' => AffiliateTermsService::AGREEMENT_KEY,
            'agreement_version' => 1,
            'policy_version' => 1,
            'locale' => 'en',
            'rendered_text' => 'OLD SIGNED v1 CONTRACT',
            'content_hash' => hash('sha256', 'OLD SIGNED v1 CONTRACT'),
            'settings_snapshot' => ['commission_percent' => '10%'],
            'accepted_at' => now()->subMonths(2),
        ]);

        $terms = app(AffiliateTermsService::class);
        $this->assertFalse($terms->hasAccepted($affiliate));
        $this->assertGreaterThanOrEqual(2, $terms->agreementVersion());
        $this->assertSame('', (string) Setting::get('affiliates.terms.body_en', ''));
        $this->assertStringContainsString('Affiliate Agreement', $terms->render($affiliate, 'en'));
        $this->assertStringNotContainsString('STALE HUB BODY', $terms->render($affiliate, 'en'));

        $eligibility = app(AffiliateEligibilityService::class)->for($affiliate);
        $this->assertContains('terms_unaccepted', $eligibility['reasons']);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.profile', ['section' => 'agreement']))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.agreement_status_update_required'), false)
            ->assertSee(__('affiliate_terms.accept_button'), false)
            ->assertDontSee('OLD SIGNED v1 CONTRACT', false)
            ->assertSee('Affiliate Agreement', false)
            ->getContent();

        $this->assertStringNotContainsString('STALE HUB BODY', $html);

        $this->actingAs($user)
            ->post(route('site.affiliate.terms.accept'), [
                'affiliate_terms_accepted' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($terms->hasAccepted($affiliate->fresh()));
        $latest = $terms->latestAcceptance($affiliate->fresh());
        $this->assertGreaterThanOrEqual(2, (int) $latest->agreement_version);
        $this->assertStringContainsString('Affiliate Agreement', (string) $latest->rendered_text);
        $this->assertTrue(
            PartnerAgreementAcceptance::query()
                ->where('partner_id', $affiliate->id)
                ->where('agreement_version', 1)
                ->exists()
        );

        $after = app(AffiliateEligibilityService::class)->for($affiliate->fresh());
        $this->assertNotContains('terms_unaccepted', $after['reasons']);
    }
}
