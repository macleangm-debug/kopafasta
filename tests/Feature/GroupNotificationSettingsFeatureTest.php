<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Messaging\TransactionalMessagingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupNotificationSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_save_group_notification_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $messaging = app(TransactionalMessagingService::class);
        $messaging->ensureDefaults();

        // Seed a non-group event so we can prove group save does not wipe it.
        $messaging->save([
            'enabled' => true,
            'force_log_driver' => true,
            'overdue_reminders' => true,
            'reminder_offsets_days' => '3,1,0',
            'channels' => ['sms' => true, 'email' => true, 'in_app' => true, 'whatsapp' => false, 'push' => false],
            'events' => [
                'payment_received' => [
                    'enabled' => true,
                    'channels' => ['sms', 'email'],
                ],
                'group_contract_sign_required' => [
                    'enabled' => true,
                    'channels' => ['sms', 'in_app'],
                ],
            ],
            'whatsapp' => ['provider' => 'log', 'api_url' => '', 'api_token' => '', 'from_number' => ''],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.group-notifications'))
            ->assertOk()
            ->assertSee('Group notifications', false)
            ->assertSee('Member consent / invite required', false)
            ->assertSee('Leader — member screening feedback', false)
            ->assertSee('group_contract_sign_required', false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.group-notifications.save'), [
                'events' => [
                    'group_contract_sign_required' => [
                        'enabled' => '0',
                        'channels' => ['sms'],
                    ],
                    'group_member_consent_required' => [
                        'enabled' => '1',
                        'channels' => ['sms', 'in_app'],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertFalse($messaging->eventEnabled('group_contract_sign_required'));
        $this->assertTrue($messaging->eventEnabled('group_member_consent_required'));
        $this->assertSame(['sms'], $messaging->eventConfig('group_contract_sign_required')['channels'] ?? []);

        // Non-group event must remain intact after group-only save.
        $payment = $messaging->eventConfig('payment_received');
        $this->assertNotNull($payment);
        $this->assertTrue($payment['enabled']);
        $this->assertSame(['sms', 'email'], $payment['channels']);
    }

    public function test_disabled_group_event_skips_customer_sms(): void
    {
        $messaging = app(TransactionalMessagingService::class);
        $messaging->ensureDefaults();
        $messaging->saveGroupEvents([
            'events' => [
                'group_member_consent_required' => [
                    'enabled' => false,
                    'channels' => ['sms', 'in_app'],
                ],
            ],
        ]);

        NotificationTemplate::updateOrCreate(
            ['code' => 'group_member_consent_required'],
            [
                'name' => 'Group Consent',
                'channel' => 'sms',
                'subject' => 'Consent',
                'body' => 'Hi {{ name }}, join {{ leader_name }}: {{ invite_url }}',
                'is_active' => true,
            ]
        );

        $customer = Customer::query()->create([
            'customer_number' => 'CUS-GL-MSG-001',
            'first_name' => 'Rogathe',
            'last_name' => 'Nyelle',
            'phone' => '+255710008888',
            'status' => 'active',
        ]);

        app(NotificationService::class)->notifyCustomer($customer, 'group_member_consent_required', [
            'name' => 'Rogathe',
            'leader_name' => 'Gaspari',
            'invite_url' => 'https://example.test/invite',
        ]);

        $this->assertSame(0, NotificationLog::query()->where('template', 'group_member_consent_required')->where('status', 'sent')->count());
    }
}
