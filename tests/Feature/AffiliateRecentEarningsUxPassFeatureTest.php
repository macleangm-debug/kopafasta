<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliatePortalPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateRecentEarningsUxPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_hides_borrower_outcome_and_shows_commercial_status(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-earn-1',
            'name' => 'Earnings Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712399001',
            'affiliate_code' => 'EARN01',
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-earn1',
            'member_no' => 'MBR-UAT-UZ16',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Private',
            'last_name' => 'Borrower',
            'phone' => '+255700111222',
            'country_code' => 'TZ',
            'affiliate_vendor_id' => $affiliate->id,
        ]);
        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'commission_application_fee',
            'customer_id' => $customer->id,
            'commission_amount' => 90,
            'landing_page' => 'https://example.test/?promo=EARN01',
        ]);

        $presenter = app(AffiliatePortalPresenter::class);
        $pipeline = $presenter->performance($affiliate)['pipeline'];
        $this->assertCount(1, $pipeline);
        $this->assertSame('MBR-UAT-UZ16', $pipeline->first()['member_no']);
        $this->assertSame(90.0, $pipeline->first()['commission_amount']);
        $this->assertSame(__('site.affiliate_portal.commission_status_earned'), $pipeline->first()['stage']);

        // Lending-outcome labels must never appear in the Affiliate overview catalogue/render path.
        $this->assertStringNotContainsString(
            'Application declined',
            file_get_contents(app_path('Services/AffiliatePortalPresenter.php'))
        );
        $this->assertStringNotContainsString(
            'stage_declined',
            file_get_contents(app_path('Services/AffiliatePortalPresenter.php'))
        );

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.performance', ['tab' => 'overview']))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.recent_earnings'), false)
            ->assertSee('MBR-UAT-UZ16', false)
            ->assertSee(format_money(90), false)
            ->assertSee(__('site.affiliate_portal.commission_status_earned'), false)
            ->assertDontSee('Application declined', false)
            ->assertDontSee('Application approved', false)
            ->assertDontSee('Private Borrower', false);
    }

    public function test_overview_earnings_list_is_bounded_to_five(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-earn-2',
            'name' => 'Bounded Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712399002',
            'affiliate_code' => 'BOUND1',
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
        ]);

        for ($i = 1; $i <= 8; $i++) {
            $customer = Customer::create([
                'user_id' => User::factory()->create(['role' => 'borrower'])->id,
                'customer_number' => 'C-b'.$i,
                'member_no' => 'MBR-B'.$i,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Member',
                'last_name' => 'B'.$i,
                'phone' => '+25570022'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'country_code' => 'TZ',
                'affiliate_vendor_id' => $affiliate->id,
            ]);
            AffiliateEvent::create([
                'vendor_id' => $affiliate->id,
                'event_type' => 'commission_application_fee',
                'customer_id' => $customer->id,
                'commission_amount' => 10 + $i,
            ]);
        }

        $pipeline = app(AffiliatePortalPresenter::class)->performance($affiliate)['pipeline'];
        $this->assertLessThanOrEqual(5, $pipeline->count());
    }
}
