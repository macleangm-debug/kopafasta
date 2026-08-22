<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateEvaluationService;
use App\Services\AffiliateLifecycleService;
use App\Services\PartnerEfficiencyService;
use App\Services\PartnerPerformanceReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPerformanceAutomationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_partner_performance_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.partner-performance'))
            ->assertOk()
            ->assertSee('Score bands', false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.partner-performance.save'), [
                'min_jobs_for_score' => 4,
                'strong_score' => 85,
                'watch_score' => 65,
                'force_at_risk_escalation_percent' => 35,
                'force_at_risk_fail_percent' => 35,
                'weight_completion' => 40,
                'weight_on_time' => 25,
                'weight_not_escalated' => 20,
                'weight_not_failed' => 15,
                'warnings_before_suspend' => 3,
                'nudge_cooldown_days' => 10,
                'auto_nudge' => '1',
                'auto_suspend' => '0',
            ])
            ->assertRedirect();

        $stored = Setting::get('partners.efficiency');
        $this->assertSame(85, (int) $stored['strong_score']);
        $this->assertSame(3, (int) $stored['warnings_before_suspend']);
        $this->assertFalse((bool) $stored['auto_suspend']);
    }

    public function test_partner_support_cannot_open_performance_settings(): void
    {
        $support = User::factory()->create(['role' => 'partner_support', 'is_active' => true]);

        $this->actingAs($support, 'admin')
            ->get(route('admin.settings.partner-performance'))
            ->assertForbidden();
    }

    public function test_field_partner_profile_shows_efficiency_band(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $partner = Partner::create([
            'vendor_number' => 'PT-EFF-SHOW',
            'name' => 'Profile Valuer',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        PartnerTask::create([
            'partner_id' => $partner->id,
            'task_type' => 'valuation',
            'status' => 'completed',
            'due_at' => now()->addDay(),
            'completed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Efficiency', false)
            ->assertSee('New', false);
    }

    public function test_repeated_at_risk_reviews_suspend_the_partner(): void
    {
        Setting::set('partners.efficiency', [
            'min_jobs_for_score' => 3,
            'strong_score' => 80,
            'watch_score' => 60,
            'force_at_risk_escalation_percent' => 40,
            'force_at_risk_fail_percent' => 40,
            'auto_nudge' => true,
            'auto_suspend' => true,
            'warnings_before_suspend' => 2,
            'nudge_cooldown_days' => 1,
        ]);

        $partner = Partner::create([
            'vendor_number' => 'PT-EFF-SUS',
            'name' => 'Failing Collector',
            'category' => 'debt_collector',
            'status' => 'active',
            'phone' => '255712349001',
        ]);

        foreach (range(1, 3) as $i) {
            PartnerTask::create([
                'partner_id' => $partner->id,
                'task_type' => 'collection',
                'status' => 'failed',
                'due_at' => now()->subDay(),
                'completed_at' => now(),
            ]);
        }

        $reviews = app(PartnerPerformanceReviewService::class);
        $this->assertSame('nudged', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('active', $partner->fresh()->status);

        $this->assertSame('suspended', $reviews->reviewPartner($partner->fresh()));
        $this->assertSame('suspended', $partner->fresh()->status);
    }

    public function test_affiliate_missed_volume_target_escalates_to_suspend(): void
    {
        Setting::set('affiliates.evaluation', [
            'auto_apply_actions' => true,
            'period_days' => 30,
            'min_events_for_scoring' => 3,
            'monthly_registration_target' => 10,
            'volume_min_active_days' => 0,
            'volume_misses_before_nudge' => 1,
            'volume_misses_before_watchlist' => 2,
            'volume_misses_before_suspend' => 3,
            'watchlist_risk_score' => 90,
            'watchlist_fraud_score' => 90,
            'suspend_risk_score' => 95,
            'suspend_fraud_score' => 95,
        ]);

        $affiliate = Vendor::create([
            'vendor_number' => 'AFF-VOL-01',
            'name' => 'Slow Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712349002',
            'affiliate_code' => 'SLOW01',
            'affiliate_lifecycle_status' => AffiliateLifecycleService::ACTIVE,
            'created_at' => now()->subDays(60),
        ]);
        $affiliate->created_at = now()->subDays(60);
        $affiliate->save();

        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'click',
        ]);

        $service = app(AffiliateEvaluationService::class);
        $now = now();

        $first = $service->evaluatePartner(
            $affiliate->fresh(),
            $now->copy()->subDays(90)->startOfDay(),
            $now->copy()->subDays(61)->endOfDay(),
            applyActions: true,
        );
        $this->assertSame('review', $first->recommendation);

        $second = $service->evaluatePartner(
            $affiliate->fresh(),
            $now->copy()->subDays(60)->startOfDay(),
            $now->copy()->subDays(31)->endOfDay(),
            applyActions: true,
        );
        $this->assertSame('watchlist', $second->recommendation);
        $this->assertSame(AffiliateLifecycleService::WATCHLIST, $affiliate->fresh()->affiliate_lifecycle_status);

        $third = $service->evaluatePartner(
            $affiliate->fresh(),
            $now->copy()->subDays(30)->startOfDay(),
            $now->copy()->endOfDay(),
            applyActions: true,
        );
        $this->assertSame('suspend', $third->recommendation);
        $this->assertSame(AffiliateLifecycleService::SUSPENDED, $affiliate->fresh()->affiliate_lifecycle_status);
    }

    public function test_affiliate_profile_shows_monthly_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $affiliate = Vendor::create([
            'vendor_number' => 'AFF-VOL-SHOW',
            'name' => 'Shown Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'affiliate_code' => 'SHOW01',
            'affiliate_lifecycle_status' => AffiliateLifecycleService::ACTIVE,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $affiliate))
            ->assertOk()
            ->assertSee('This period vs monthly target', false)
            ->assertSee('new users', false);
    }

    public function test_efficiency_settings_change_the_strong_band(): void
    {
        Setting::set('partners.efficiency', [
            'min_jobs_for_score' => 3,
            'strong_score' => 95,
            'watch_score' => 50,
        ]);

        $partner = Partner::create([
            'vendor_number' => 'PT-EFF-BAND',
            'name' => 'Almost Strong',
            'category' => 'valuer',
            'status' => 'active',
        ]);

        foreach (range(1, 3) as $i) {
            PartnerTask::create([
                'partner_id' => $partner->id,
                'task_type' => 'valuation',
                'status' => 'completed',
                'due_at' => now()->addDay(),
                'completed_at' => now(),
            ]);
        }
        PartnerTask::create([
            'partner_id' => $partner->id,
            'task_type' => 'valuation',
            'status' => 'failed',
            'due_at' => now()->subDay(),
            'completed_at' => now(),
        ]);

        $row = app(PartnerEfficiencyService::class)->forPartner($partner);
        $this->assertNotNull($row);
        $this->assertSame(PartnerEfficiencyService::BAND_WATCH, $row['band']);
    }
}
