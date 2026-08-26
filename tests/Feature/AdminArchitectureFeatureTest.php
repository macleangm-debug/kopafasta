<?php

namespace Tests\Feature;

use App\Exceptions\DemoOperationBlockedException;
use App\Models\Customer;
use App\Models\MarketingDemoSession;
use App\Models\PlusOffer;
use App\Models\Role;
use App\Models\User;
use App\Services\ConsoleNavService;
use App\Services\Marketing\DemoContext;
use App\Services\Marketing\DemoGuard;
use App\Services\Marketing\MarketingAudienceService;
use App\Services\Marketing\MarketingDemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArchitectureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_nav_uses_compact_primary_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $labels = collect(app(ConsoleNavService::class)->visibleSections($admin))
            ->pluck('label')
            ->all();

        $this->assertSame(
            ['Home', 'Customers', 'Lending', 'Money', 'Partners', 'Growth', 'Communications', 'Reports', 'More', 'Settings'],
            $labels
        );
    }

    public function test_marketer_can_use_growth_without_settings_manage(): void
    {
        $user = $this->marketer();

        $this->assertFalse($user->hasPermission('settings.manage'));
        $this->assertTrue($user->hasPermission('marketing.view'));

        $labels = collect(app(ConsoleNavService::class)->visibleSections($user))->pluck('label')->all();
        $this->assertSame(['Home', 'Growth', 'Communications', 'Reports'], $labels);

        $this->actingAs($user, 'admin')
            ->get(route('admin.growth.index'))
            ->assertOk()
            ->assertSee('Reach the right customers', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.settings.plus'))
            ->assertForbidden();

        $this->actingAs($user, 'admin')
            ->post(route('admin.growth.offers.store'), [
                'title' => 'Gold weekend',
                'body' => 'A Plus offer',
                'tier' => 'gold',
                'plus_only' => '1',
                'active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plus_offers', ['title' => 'Gold weekend']);
    }

    public function test_officer_cannot_open_growth(): void
    {
        $officer = User::factory()->create(['role' => 'officer', 'is_active' => true]);

        $labels = collect(app(ConsoleNavService::class)->visibleSections($officer))->pluck('label')->all();
        $this->assertNotContains('Growth', $labels);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.growth.index'))
            ->assertForbidden();

        $this->actingAs($officer, 'admin')
            ->get(route('admin.promotions.index'))
            ->assertForbidden();
    }

    public function test_grade_watch_lives_under_customers_not_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.grades.watch'))
            ->assertRedirect(route('admin.customers.grade-watch'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.grade-watch'))
            ->assertOk()
            ->assertSee('Grade Watch', false)
            ->assertDontSee('Settings hub', false);
    }

    public function test_audience_estimate_counts_real_customers_only(): void
    {
        $user = $this->marketer();
        $borrower = User::factory()->create(['role' => 'borrower']);
        Customer::query()->create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-AUD-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '255712001111',
            'country_code' => 'TZ',
            'grade' => 'gold',
        ]);

        $count = app(MarketingAudienceService::class)->estimate([
            'country_code' => 'TZ',
            'status' => 'active',
            'grades' => ['gold'],
            'plus' => 'not_subscribed',
        ]);
        $this->assertSame(1, $count);

        $this->actingAs($user, 'admin')
            ->post(route('admin.growth.audiences.store'), [
                'name' => 'Gold without Plus',
                'country_code' => 'TZ',
                'status' => 'active',
                'grades' => ['gold'],
                'plus' => 'not_subscribed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('marketing_audiences', ['name' => 'Gold without Plus']);
    }

    public function test_demo_is_isolated_from_real_customers_and_money(): void
    {
        $user = $this->marketer();
        $beforeCustomers = Customer::query()->count();
        $beforeOffers = PlusOffer::query()->count();

        $this->actingAs($user, 'admin')
            ->post(route('admin.growth.demos.store'), [
                'who' => 'borrower',
                'persona_key' => 'gold_plus_member',
                'scenario_key' => 'loan_received',
                'display_name' => 'Asha Demo',
                'duration' => '15',
            ])
            ->assertRedirect();

        $this->assertSame($beforeCustomers, Customer::query()->count());
        $this->assertSame($beforeOffers, PlusOffer::query()->count());
        $session = MarketingDemoSession::query()->where('display_name', 'Asha Demo')->first();
        $this->assertNotNull($session);
        $this->assertFalse($session->payload['can_move_money'] ?? true);

        app(DemoContext::class)->activate($session);
        try {
            $this->expectException(DemoOperationBlockedException::class);
            app(DemoGuard::class)->assertCanMoveMoney('create a customer payment');
        } finally {
            app(DemoContext::class)->clear();
        }
    }

    public function test_search_is_permission_aware(): void
    {
        $marketer = $this->marketer();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($marketer, 'admin')
            ->get(route('admin.search', ['q' => 'campaign']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Create campaign']);

        $this->actingAs($marketer, 'admin')
            ->get(route('admin.search', ['q' => 'grade']))
            ->assertOk()
            ->assertJsonMissing(['title' => 'Open Grade Watch']);

        $this->actingAs($marketer, 'admin')
            ->get(route('admin.search', ['q' => 'affiliate commission']))
            ->assertOk();

        $adminJson = $this->actingAs($admin, 'admin')
            ->get(route('admin.search', ['q' => 'grades']))
            ->assertOk()
            ->json('groups');

        $settingsHits = collect($adminJson)->firstWhere('group', 'Settings');
        $this->assertNotEmpty($settingsHits['items'] ?? []);
    }

    public function test_plus_settings_no_longer_hosts_offer_forms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.plus'))
            ->assertOk()
            ->assertSee('Billing period', false)
            ->assertSee('Offers are managed from Growth', false)
            ->assertSee('Campaigns are managed from Growth', false)
            ->assertDontSee('name="eligible_grades[]"', false);
    }

    public function test_campaign_wizard_launches_around_existing_promotion_engine(): void
    {
        $user = $this->marketer();
        $borrower = User::factory()->create(['role' => 'borrower']);
        Customer::query()->create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-CAMP-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Kileo',
            'phone' => '255712009999',
            'country_code' => 'TZ',
            'grade' => 'gold',
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.promotions.create'))
            ->assertOk()
            ->assertSee('What do you want to achieve?', false)
            ->assertSee('data-step-label="Goal"', false)
            ->assertSee('data-step-label="Audience"', false)
            ->assertSee('data-step-label="Channels"', false)
            ->assertSee('data-step-label="Timing"', false)
            ->assertSee('data-step-label="Preview"', false)
            ->assertSee('data-step-label="Launch"', false)
            ->assertDontSee('data-step-label="Message"', false)
            ->assertSee('Estimated audience', false)
            ->assertSee('Launch campaign', false);

        $this->actingAs($user, 'admin')
            ->post(route('admin.promotions.store'), [
                'name' => 'Plus weekend',
                'intent' => 'encourage_plus',
                'audience_mode' => 'everyone',
                'audience_status' => 'active',
                'country_code' => 'TZ',
                'grades' => ['gold'],
                'plus' => 'not_subscribed',
                'message_en' => 'Join Plus this weekend.',
                'message_sw' => 'Jiunge na Plus wikiendi hii.',
                'channels' => ['in_app'],
                'send_mode' => 'now',
                'cta_url' => '/borrower/plus',
            ])
            ->assertRedirect();

        $campaign = \App\Models\Promotion::query()->where('name', 'Plus weekend')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('seasonal', $campaign->type);
        $this->assertSame('active', $campaign->status);
        $this->assertSame('encourage_plus', $campaign->metadata['intent'] ?? null);
        $this->assertContains('in_app', $campaign->metadata['channels'] ?? []);
        $this->assertSame(1, (int) ($campaign->metadata['results']['reach'] ?? 0));
        $this->assertSame(1, (int) ($campaign->metadata['results']['delivered'] ?? 0));
        $this->assertTrue((bool) ($campaign->metadata['results']['in_app_published'] ?? false));
        $this->assertFalse((bool) ($campaign->metadata['results']['sms_queued'] ?? true));
    }

    public function test_marketer_cannot_open_settings_hub(): void
    {
        $user = $this->marketer();

        $this->actingAs($user, 'admin')
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_shortcuts_persist_reorder_remove_and_drop_when_permission_is_gone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(\App\Services\AdminShortcutService::class);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.nav.shortcuts.store'), [
                'route' => 'admin.promotions.index',
                'label' => 'Campaigns',
            ])
            ->assertRedirect();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.nav.shortcuts.store'), [
                'route' => 'admin.growth.demos.index',
                'label' => 'Demo Accounts',
            ])
            ->assertRedirect();

        $admin->refresh();
        $this->assertSame(
            ['admin.promotions.index', 'admin.growth.demos.index'],
            collect($service->list($admin))->pluck('route')->all()
        );

        $this->actingAs($admin, 'admin')
            ->put(route('admin.nav.shortcuts.reorder'), [
                'routes' => ['admin.growth.demos.index', 'admin.promotions.index'],
            ])
            ->assertRedirect();
        $admin->refresh();
        $this->assertSame(
            ['admin.growth.demos.index', 'admin.promotions.index'],
            collect($service->list($admin))->pluck('route')->all()
        );

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.nav.shortcuts.destroy'), ['route' => 'admin.growth.demos.index'])
            ->assertRedirect();
        $admin->refresh();
        $this->assertSame(['admin.promotions.index'], collect($service->list($admin))->pluck('route')->all());

        $prefs = $admin->preferences ?? [];
        $prefs['admin_shortcuts'] = array_merge($prefs['admin_shortcuts'] ?? [], [[
            'route' => 'admin.settings.grades',
            'label' => 'Grades',
        ]]);
        $admin->preferences = $prefs;
        $admin->save();

        $marketer = $this->marketer();
        $marketer->preferences = $admin->fresh()->preferences;
        $marketer->save();
        $this->assertNotContains(
            'admin.settings.grades',
            collect($service->list($marketer->fresh()))->pluck('route')->all()
        );
        $this->assertContains(
            'admin.promotions.index',
            collect($service->list($marketer->fresh()))->pluck('route')->all()
        );

        $admin = User::factory()->create(['role' => 'admin']);
        $pinable = [
            'admin.promotions.index',
            'admin.growth.demos.index',
            'admin.growth.audiences.index',
            'admin.growth.offers.index',
            'admin.growth.affiliates',
            'admin.communications.index',
        ];
        foreach ($pinable as $route) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.nav.shortcuts.store'), [
                    'route' => $route,
                    'label' => $route,
                ])
                ->assertRedirect();
        }
        $this->actingAs($admin, 'admin')
            ->post(route('admin.nav.shortcuts.store'), [
                'route' => 'admin.growth.performance',
                'label' => 'Performance',
            ])
            ->assertStatus(422);
        $this->assertCount(6, $service->list($admin->fresh()));
    }

    public function test_search_hides_settings_and_unauthorized_records(): void
    {
        $marketer = $this->marketer();
        $officer = User::factory()->create(['role' => 'officer', 'is_active' => true]);

        \App\Models\Promotion::query()->create([
            'code' => 'KF-HIDE1',
            'name' => 'Secret Gold Push',
            'type' => 'seasonal',
            'status' => 'active',
        ]);

        $groups = $this->actingAs($marketer, 'admin')
            ->get(route('admin.search', ['q' => 'company']))
            ->assertOk()
            ->json('groups');
        $this->assertNull(collect($groups)->firstWhere('group', 'Settings'));

        $campaignHits = $this->actingAs($marketer, 'admin')
            ->get(route('admin.search', ['q' => 'Secret Gold Push']))
            ->assertOk()
            ->json('groups');
        $this->assertNotEmpty(collect($campaignHits)->firstWhere('group', 'Campaigns')['items'] ?? []);

        $officerHits = $this->actingAs($officer, 'admin')
            ->get(route('admin.search', ['q' => 'Secret Gold Push']))
            ->assertOk()
            ->json('groups');
        $this->assertNull(collect($officerHits)->firstWhere('group', 'Campaigns'));
        $this->assertNull(collect($officerHits)->firstWhere('group', 'Settings'));
    }

    public function test_profiles_workspace_uses_existing_completion_source_of_truth(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::query()->create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-PROF-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '255712000222',
            'country_code' => 'TZ',
            'grade' => 'bronze',
        ]);
        $percent = (int) (app(\App\Services\ProfileCompletionService::class)->completionSummary($customer)['percent'] ?? 0);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.profiles'))
            ->assertOk()
            ->assertSee('Asha Mushi', false)
            ->assertSee($percent.'%', false)
            ->assertSee('Work this profile', false)
            ->assertSee('Needs attention', false)
            ->assertSee('hidden md:block', false)
            ->assertSee('md:hidden', false)
            ->assertDontSee('admin.profile-sections', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.profiles', ['focus' => $customer->id]))
            ->assertOk()
            ->assertSee('Profile queue', false)
            ->assertSee('Open customer file', false);
    }

    public function test_marketer_can_edit_templates_without_settings_manage(): void
    {
        $user = $this->marketer();
        $this->assertFalse($user->hasPermission('settings.manage'));

        $template = \App\Models\NotificationTemplate::create([
            'name' => 'Payment received',
            'code' => 'payment_received',
            'locale' => 'en',
            'channel' => 'sms',
            'subject' => 'Paid',
            'body' => 'EN original',
            'is_active' => true,
        ]);
        \App\Models\NotificationTemplate::create([
            'name' => 'Payment received',
            'code' => 'payment_received',
            'locale' => 'sw',
            'channel' => 'sms',
            'subject' => 'Malipo',
            'body' => 'SW original',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.notification-templates.edit', $template))
            ->assertOk()
            ->assertSee('EN original', false);

        $this->actingAs($user, 'admin')
            ->put(route('admin.notification-templates.update', $template), [
                'name' => 'Payment received',
                'code' => 'payment_received',
                'channel' => 'sms',
                'is_active' => '1',
                'translations' => [
                    'en' => ['locale' => 'en', 'subject' => 'Paid', 'body' => 'EN updated {{ amount }}'],
                    'sw' => ['locale' => 'sw', 'subject' => 'Malipo', 'body' => 'SW updated {{ amount }}'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_templates', [
            'code' => 'payment_received',
            'locale' => 'en',
            'body' => 'EN updated {{ amount }}',
        ]);
    }

    public function test_canonical_settings_operational_routes_redirect(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.chatbot'))
            ->assertRedirect(route('admin.communications.chatbot'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.plus.offers.save'), [
                'title' => 'Should not persist',
                'tier' => 'gold',
            ])
            ->assertRedirect(route('admin.growth.offers.index'));

        $this->assertDatabaseMissing('plus_offers', ['title' => 'Should not persist']);
    }

    public function test_growth_and_communications_overviews_render_operational_kpis(): void
    {
        $user = $this->marketer();

        $this->actingAs($user, 'admin')
            ->get(route('admin.growth.index'))
            ->assertOk()
            ->assertSee('Active campaigns', false)
            ->assertSee('+ Campaign', false)
            ->assertSee('Needs attention', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.communications.index'))
            ->assertOk()
            ->assertSee('Sent today', false)
            ->assertSee('Templates', false);
    }

    public function test_demo_presentation_and_customize_stay_isolated(): void
    {
        $user = $this->marketer();
        $this->actingAs($user, 'admin')
            ->post(route('admin.growth.demos.store'), [
                'who' => 'borrower',
                'persona_key' => 'gold_plus_member',
                'scenario_key' => 'loan_completed',
                'display_name' => 'Asha Present',
                'duration' => '15',
            ])
            ->assertRedirect();

        $demo = \App\Models\MarketingDemoSession::query()->where('display_name', 'Asha Present')->first();
        $this->assertNotNull($demo);
        $before = Customer::query()->count();

        $this->actingAs($user, 'admin')
            ->post(route('admin.growth.demos.customize', $demo), [
                'display_name' => 'Asha Present',
                'amount' => '2500000',
                'grade' => 'gold',
                'trust' => 80,
            ])
            ->assertRedirect();

        $this->assertSame($before, Customer::query()->count());
        $demo->refresh();
        $this->assertFalse($demo->payload['can_move_money'] ?? true);
        $this->assertSame(80, (int) $demo->payload['trust']);

        $playUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.growth.demos.play',
            now()->addHour(),
            ['demo' => $demo]
        );
        $this->actingAs($user, 'admin')
            ->get($playUrl)
            ->assertOk()
            ->assertSee('Loan completed', false)
            ->assertSee('Isolated identity', false);
    }

    public function test_operational_pages_expose_desktop_tables_and_mobile_cards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marketer = $this->marketer();

        $this->actingAs($admin, 'admin')->get(route('admin.customers.grade-watch'))
            ->assertOk()->assertSee('Grade Watch', false);
        $this->actingAs($admin, 'admin')->get(route('admin.customers.profiles'))
            ->assertOk()->assertSee('Needs attention', false)->assertSee('hidden md:block', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.promotions.index'))
            ->assertOk()->assertSee('hidden md:block', false)->assertSee('md:hidden', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.audiences.index'))
            ->assertOk()
            ->assertSee('Estimated audience', false)
            ->assertSee('Borrowing relationship', false)
            ->assertSee('hidden md:block', false)
            ->assertSee('md:hidden', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.personas.index'))
            ->assertOk()
            ->assertSee('Personas never alter real customers', false)
            ->assertSee('hidden md:block', false)
            ->assertSee('md:hidden', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.demos.create'))
            ->assertOk()->assertSee('Under a minute', false)->assertSee('Amount (TZS)', false)->assertSee('Custom end', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.offers.index'))
            ->assertOk()->assertSee('New offer', false)->assertSee('hidden md:block', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.affiliates'))
            ->assertOk()->assertSee('Affiliate partners', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.performance'))
            ->assertOk()->assertSee('Active campaigns', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.index'))
            ->assertOk()->assertSee('Reach the right customers', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.growth.demos.index'))
            ->assertOk()->assertSee('hidden md:block', false)->assertSee('md:hidden', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.communications.index'))
            ->assertOk()->assertSee('Sent today', false);
        $this->actingAs($admin, 'admin')->get(route('admin.content.plus-learning'))
            ->assertOk()->assertSee('Kopafasta Plus Learning', false);
        $this->actingAs($marketer, 'admin')->get(route('admin.communications.chatbot'))
            ->assertOk();
        $this->actingAs($marketer, 'admin')->get(route('admin.notification-templates.index'))
            ->assertOk()->assertSee('Templates', false);
        $this->actingAs($admin, 'admin')->get(route('admin.settings.plus'))
            ->assertOk()
            ->assertSee('Plus Learning is managed from Content', false)
            ->assertSee('Open Campaigns', false);
    }

    private function marketer(): User
    {
        Role::query()->updateOrCreate(
            ['code' => 'marketer'],
            [
                'name' => 'Marketer',
                'permissions' => config('permissions.defaults.marketer'),
                'is_system' => false,
            ]
        );

        return User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);
    }
}
