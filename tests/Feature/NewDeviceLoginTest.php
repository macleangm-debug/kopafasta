<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\NewLoginNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewDeviceLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_does_not_trigger_new_device_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'nd@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk();

        Notification::assertNothingSent();
        $this->assertFalse(AuditLog::where('event', 'auth.new_device_login')->exists());
    }

    public function test_login_from_known_device_does_not_notify(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'nd@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        // Seed a prior successful login from the same IP/UA
        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.login_success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Symfony',
            'new_values' => null,
        ]);

        $this->withHeader('User-Agent', 'Symfony')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ])->assertOk();

        Notification::assertNothingSent();
        $this->assertFalse(AuditLog::where('event', 'auth.new_device_login')->exists());
    }

    public function test_login_from_new_ip_or_ua_triggers_notification_and_audit(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'nd@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.login_success',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'OldBrowser/1.0',
            'new_values' => null,
        ]);

        $this->withHeader('User-Agent', 'NewBrowser/9.0')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'correct-password',
            ])->assertOk();

        Notification::assertSentTo($user, NewLoginNotification::class);

        $newDeviceLog = AuditLog::where('event', 'auth.new_device_login')->latest('id')->first();
        $this->assertNotNull($newDeviceLog);
        $this->assertSame($user->id, $newDeviceLog->user_id);
        $this->assertSame('203.0.113.99', $newDeviceLog->ip_address);
    }

    public function test_login_history_returns_recent_events_for_current_user(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        $other = User::factory()->create(['role' => 'officer']);

        AuditLog::create(['user_id' => $user->id, 'event' => 'auth.login_success', 'ip_address' => '1.1.1.1']);
        AuditLog::create(['user_id' => $user->id, 'event' => 'auth.failed_login', 'ip_address' => '1.1.1.2']);
        AuditLog::create(['user_id' => $user->id, 'event' => 'admin.user_locked', 'ip_address' => '1.1.1.3']); // excluded
        AuditLog::create(['user_id' => $other->id, 'event' => 'auth.login_success', 'ip_address' => '2.2.2.2']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/login-history')->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $events = collect($data)->pluck('event')->all();
        $this->assertContains('auth.login_success', $events);
        $this->assertContains('auth.failed_login', $events);
        $this->assertNotContains('admin.user_locked', $events);
    }
}
