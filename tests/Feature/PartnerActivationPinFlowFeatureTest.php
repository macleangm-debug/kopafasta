<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PinService;
use App\Support\Celebration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerActivationPinFlowFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_activates_without_pin_then_creates_pin_with_celebration(): void
    {
        Vendor::create([
            'name' => 'Test Valuer',
            'category' => 'valuer',
            'status' => 'inactive',
            'partner_number' => 'PT-VL-TZ-TEST',
            'phone' => '+255710000111',
            'email' => 'valuer-test@kopafasta.local',
            'user_id' => null,
            'activated_at' => null,
        ]);

        $this->get(route('site.partner.start'))
            ->assertOk()
            ->assertDontSee('name="pin"', false);

        $response = $this->from(route('site.partner.start'))->post(route('site.partner.start.lookup'), [
            'partner_code' => 'PT-VL-TZ-TEST',
            'phone' => '255710000111',
        ]);

        if ($response->status() !== 302) {
            $this->fail('Activation did not redirect. Session errors: '.json_encode(session('errors')?->all() ?? []).' Status: '.$response->status());
        }

        $response->assertRedirect(route('site.partner.setup-pin'));

        $vendor = Vendor::query()->where('partner_number', 'PT-VL-TZ-TEST')->firstOrFail();
        $this->assertNotNull($vendor->activated_at);
        $this->assertNotNull($vendor->user_id);

        $user = User::query()->findOrFail($vendor->user_id);
        $this->assertFalse(app(PinService::class)->hasPin($user));

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertRedirect(route('site.partner.setup-pin'));

        $this->actingAs($user)
            ->post(route('site.partner.setup-pin.post'), [
                'pin' => '1234',
                'pin_confirmation' => '1234',
            ])
            ->assertRedirect(route('site.partner.dashboard'))
            ->assertSessionHas(Celebration::SESSION_KEY);

        $this->assertTrue(app(PinService::class)->hasPin($user->fresh()));
    }

    public function test_activation_matches_local_only_stored_phone_with_full_msisdn(): void
    {
        Vendor::create([
            'name' => 'Local Phone Valuer',
            'category' => 'valuer',
            'status' => 'inactive',
            'partner_number' => 'PT-VL-TZ-7ZHF',
            'phone' => '784275297',
            'email' => 'local-phone@kopafasta.local',
            'user_id' => null,
            'activated_at' => null,
        ]);

        $this->from(route('site.partner.start'))->post(route('site.partner.start.lookup'), [
            'partner_code' => 'PT-VL-TZ-7ZHF',
            'phone' => '255784275297',
            'phone_local' => '784275297',
        ])->assertRedirect(route('site.partner.setup-pin'));

        $vendor = Vendor::query()->where('partner_number', 'PT-VL-TZ-7ZHF')->firstOrFail();
        $this->assertNotNull($vendor->activated_at);
        $this->assertNotNull($vendor->user_id);
    }

    public function test_track_status_finds_admin_created_partner_by_phone(): void
    {
        Vendor::create([
            'name' => 'Tracked Valuer',
            'category' => 'valuer',
            'status' => 'inactive',
            'partner_number' => 'PT-VL-TZ-TRCK',
            'phone' => '255784275297',
            'email' => 'tracked@kopafasta.local',
            'user_id' => null,
            'activated_at' => null,
        ]);

        $this->get(route('site.partners.apply.tracking', [
            'phone' => '255784275297',
            'phone_local' => '784275297',
        ]))
            ->assertOk()
            ->assertSee('PT-VL-TZ-TRCK', false)
            ->assertSee('Tracked Valuer', false)
            ->assertSee(__('site.partner_apply.track_activate_cta'), false);
    }

    public function test_valuer_nav_puts_profile_above_support_and_hides_documents(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'name' => 'Nav Valuer',
            'category' => 'valuer',
            'user_id' => $user->id,
            'activated_at' => now(),
            'status' => 'active',
            'partner_number' => 'PT-VL-TZ-NAV1',
            'phone' => '+255710000112',
            'email' => 'valuer-nav@kopafasta.local',
        ]);
        app(PinService::class)->setPin($user, '1234');

        $nav = app(\App\Services\PartnerPortalNavService::class)->serviceNav($vendor);
        $keys = array_column($nav, 'key');

        $this->assertNotContains('documents', $keys);
        $this->assertSame('support', end($keys));
        $profileIdx = array_search('profile', $keys, true);
        $supportIdx = array_search('support', $keys, true);
        $this->assertNotFalse($profileIdx);
        $this->assertLessThan($supportIdx, $profileIdx);
    }
}
