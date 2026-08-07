<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_save_membership_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.membership'))
            ->assertOk()
            ->assertSee('Membership Settings', false)
            ->assertSee('Renewal fee', false)
            ->assertSee('Membership fee (first-time)', false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.membership.save'), [
                'duration_days' => 365,
                'registration_fee' => 2500,
                'renewal_fee' => 2000,
                'grace_period_days' => 14,
                'max_expiry_years' => 1,
                'currency' => 'TZS',
                'reminder_channels' => ['sms', 'email'],
            ])
            ->assertRedirect();

        $this->assertEquals(2500, (float) Setting::get('membership.registration_fee'));
        $this->assertEquals(2000, (float) Setting::get('membership.renewal_fee'));
        $this->assertSame(['sms', 'email'], Setting::get('membership.reminder_channels'));
    }
}
