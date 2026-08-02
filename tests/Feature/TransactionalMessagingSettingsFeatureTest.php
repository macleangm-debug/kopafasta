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

class TransactionalMessagingSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_save_messaging_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.messaging'))
            ->assertOk()
            ->assertSee('Transactional messaging', false)
            ->assertSee('PIN reset OTP', false)
            ->assertSee('Repayment due soon', false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.messaging.save'), [
                'enabled' => '1',
                'force_log_driver' => '1',
                'overdue_reminders' => '1',
                'reminder_offsets_days' => '5,2,0',
                'channels' => [
                    'sms' => '1',
                    'email' => '0',
                    'in_app' => '1',
                    'whatsapp' => '0',
                    'push' => '0',
                ],
                'events' => [
                    'repayment_due_soon' => [
                        'enabled' => '1',
                        'channels' => ['sms', 'in_app'],
                    ],
                    'pin_reset_otp' => [
                        'enabled' => '1',
                        'channels' => ['sms'],
                    ],
                ],
                'whatsapp' => [
                    'provider' => 'log',
                    'api_url' => '',
                    'api_token' => '',
                    'from_number' => '',
                ],
            ])
            ->assertRedirect();

        $messaging = app(TransactionalMessagingService::class);
        $this->assertTrue($messaging->isGloballyEnabled());
        $this->assertTrue($messaging->forceLogDriver());
        $this->assertSame([0, 2, 5], $messaging->reminderOffsetsDays());
        $this->assertTrue($messaging->channelEnabled('sms'));
        $this->assertFalse($messaging->channelEnabled('email'));
    }

    public function test_disabled_event_skips_customer_sms(): void
    {
        app(TransactionalMessagingService::class)->ensureDefaults();
        app(TransactionalMessagingService::class)->save([
            'enabled' => true,
            'force_log_driver' => true,
            'overdue_reminders' => true,
            'reminder_offsets_days' => '3,1,0',
            'channels' => ['sms' => true, 'email' => true, 'in_app' => true, 'whatsapp' => false, 'push' => false],
            'events' => [
                'payment_received' => [
                    'enabled' => false,
                    'channels' => ['sms'],
                ],
            ],
            'whatsapp' => ['provider' => 'log', 'api_url' => '', 'api_token' => '', 'from_number' => ''],
        ]);

        NotificationTemplate::updateOrCreate(
            ['code' => 'payment_received'],
            [
                'name' => 'Payment Received',
                'channel' => 'sms',
                'subject' => 'Payment',
                'body' => 'Hi {{ name }}, paid {{ amount }}. Balance {{ balance }}.',
                'is_active' => true,
            ]
        );

        $customer = Customer::query()->create([
            'customer_number' => 'CUS-MSG-001',
            'first_name' => 'Ada',
            'last_name' => 'Loan',
            'phone' => '+255710009999',
            'status' => 'active',
        ]);

        app(NotificationService::class)->notifyCustomer($customer, 'payment_received', [
            'name' => 'Ada',
            'amount' => 'TZS 10,000',
            'balance' => 'TZS 40,000',
            'loan_number' => 'LN-1',
        ]);

        $this->assertSame(0, NotificationLog::query()->where('template', 'payment_received')->where('status', 'sent')->count());
    }
}
