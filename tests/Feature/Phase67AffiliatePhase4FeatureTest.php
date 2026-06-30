<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateEvaluationService;
use App\Services\AffiliateLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase67AffiliatePhase4FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function affiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'vendor_number'              => 'AFF-P67',
            'name'                       => 'Affiliate P67',
            'category'                   => 'affiliate',
            'status'                     => 'active',
            'phone'                      => '255712346670',
            'affiliate_code'             => 'AFFP67',
            'affiliate_lifecycle_status' => AffiliateLifecycleService::ACTIVE,
            'affiliate_kyc_status'       => 'verified',
        ], $overrides));
    }

    public function test_kyc_approval_activates_lifecycle(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_lifecycle_status' => AffiliateLifecycleService::PENDING_KYC,
            'affiliate_kyc_status'       => 'submitted',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.affiliate-kyc.approve', $affiliate))
            ->assertRedirect();

        $this->assertSame(
            AffiliateLifecycleService::ACTIVE,
            $affiliate->fresh()->affiliate_lifecycle_status,
        );
    }

    public function test_suspended_affiliate_cannot_receive_referrals(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_lifecycle_status' => AffiliateLifecycleService::SUSPENDED,
        ]);

        $this->assertNull(app(\App\Services\AffiliateService::class)->findByCode('AFFP67'));
        $this->assertFalse(app(AffiliateLifecycleService::class)->canReceiveReferrals($affiliate));
    }

    public function test_evaluation_recommends_suspend_for_fraud_signals(): void
    {
        Setting::set('affiliates.evaluation', [
            'min_events_for_scoring'              => 3,
            'suspend_fraud_score'                 => 50,
            'suspend_risk_score'                  => 90,
            'duplicate_ip_registration_threshold' => 2,
            'high_click_threshold'                => 10,
            'low_conversion_threshold'            => 5,
        ]);

        $affiliate = $this->affiliate();

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            foreach (range(1, 4) as $i) {
                AffiliateEvent::create([
                    'vendor_id'  => $affiliate->id,
                    'event_type' => 'registration',
                    'ip_address' => $ip,
                ]);
            }
        }

        for ($i = 0; $i < 20; $i++) {
            AffiliateEvent::create([
                'vendor_id'  => $affiliate->id,
                'event_type' => 'click',
            ]);
        }

        $evaluation = app(AffiliateEvaluationService::class)->evaluatePartner($affiliate);

        $this->assertGreaterThanOrEqual(50, (float) $evaluation->fraud_score);
        $this->assertContains($evaluation->recommendation, ['suspend', 'watchlist']);
    }

    public function test_evaluate_command_runs_and_updates_leaderboard(): void
    {
        $first = $this->affiliate(['affiliate_code' => 'AFFP671', 'vendor_number' => 'AFF-P67-1']);
        $second = $this->affiliate(['affiliate_code' => 'AFFP672', 'vendor_number' => 'AFF-P67-2']);

        AffiliateEvent::create(['vendor_id' => $first->id, 'event_type' => 'registration']);
        AffiliateEvent::create(['vendor_id' => $first->id, 'event_type' => 'application']);
        AffiliateEvent::create(['vendor_id' => $second->id, 'event_type' => 'click']);

        $this->artisan('affiliate:evaluate --no-apply')
            ->assertSuccessful();

        $this->assertNotNull($first->fresh()->affiliate_evaluation_snapshot);
        $this->assertSame(1, $first->fresh()->affiliate_leaderboard_rank);
        $this->assertSame(2, $second->fresh()->affiliate_leaderboard_rank);
    }

    public function test_auto_apply_watchlists_high_risk_affiliate(): void
    {
        Setting::set('affiliates.evaluation', [
            'auto_apply_actions'     => true,
            'min_events_for_scoring' => 3,
            'watchlist_risk_score'   => 50,
            'watchlist_fraud_score'  => 100,
            'suspend_risk_score'     => 95,
            'suspend_fraud_score'    => 95,
            'high_click_threshold'   => 5,
            'low_conversion_threshold' => 20,
        ]);

        $affiliate = $this->affiliate();

        foreach (range(1, 10) as $i) {
            AffiliateEvent::create(['vendor_id' => $affiliate->id, 'event_type' => 'click']);
        }

        app(AffiliateEvaluationService::class)->evaluatePartner($affiliate, applyActions: true);

        $this->assertSame(
            AffiliateLifecycleService::WATCHLIST,
            $affiliate->fresh()->affiliate_lifecycle_status,
        );
    }

    public function test_admin_can_update_lifecycle_manually(): void
    {
        $affiliate = $this->affiliate();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.affiliate-lifecycle.update', $affiliate), [
                'status' => AffiliateLifecycleService::TERMINATED,
                'reason' => 'Policy violation',
            ])
            ->assertRedirect();

        $this->assertSame(AffiliateLifecycleService::TERMINATED, $affiliate->fresh()->affiliate_lifecycle_status);
        $this->assertSame('Policy violation', $affiliate->fresh()->affiliate_lifecycle_note);
    }
}
