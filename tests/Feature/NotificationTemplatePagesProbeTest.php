<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Services\Sms\UnitxtDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplatePagesProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_show_and_edit_templates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $t = NotificationTemplate::create([
            'name' => 'Payment received',
            'code' => 'payment_received',
            'locale' => 'en',
            'channel' => 'all',
            'subject' => 'Hi {{ name }}',
            'body' => 'Hello {{ name }}, we received {{ amount }}.',
            'is_active' => true,
        ]);

        NotificationTemplate::create([
            'name' => 'Payment received',
            'code' => 'payment_received',
            'locale' => 'sw',
            'channel' => 'all',
            'subject' => 'Habari {{ name }}',
            'body' => 'Habari {{ name }}, tumepokea {{ amount }}.',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notification-templates.create'))
            ->assertOk()
            ->assertSee('Select event', false)
            ->assertSee('payment_received', false)
            ->assertSee('English', false)
            ->assertSee('Kiswahili', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notification-templates.edit', $t))
            ->assertOk()
            ->assertSee('English', false)
            ->assertSee('Hello {{ name }}', false)
            ->assertSee('Habari {{ name }}', false);
    }

    public function test_bilingual_save_updates_en_and_sw(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.notification-templates.store'), [
                'name' => 'Payment received',
                'code' => 'payment_received',
                'channel' => 'sms',
                'is_active' => '1',
                'translations' => [
                    'en' => ['locale' => 'en', 'subject' => 'Paid', 'body' => 'EN body {{ amount }}'],
                    'sw' => ['locale' => 'sw', 'subject' => 'Malipo', 'body' => 'SW body {{ amount }}'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_templates', [
            'code' => 'payment_received',
            'locale' => 'en',
            'body' => 'EN body {{ amount }}',
        ]);
        $this->assertDatabaseHas('notification_templates', [
            'code' => 'payment_received',
            'locale' => 'sw',
            'body' => 'SW body {{ amount }}',
        ]);
    }

    public function test_templates_index_groups_by_lifecycle_stage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        NotificationTemplate::create([
            'name' => 'Overdue',
            'code' => 'repayment_overdue',
            'locale' => 'en',
            'channel' => 'sms',
            'subject' => null,
            'body' => 'Overdue notice',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.notification-templates.index'))
            ->assertOk()
            ->assertSee('Late payments', false)
            ->assertSee('Edit EN + SW', false)
            ->assertSee('repayment_overdue', false);
    }

    public function test_messaging_page_links_to_templates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.messaging'))
            ->assertOk()
            ->assertSee('Open notification templates', false)
            ->assertSee(route('admin.notification-templates.index'), false);
    }

    public function test_gateways_page_uses_unitxt_text_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.gateways'))
            ->assertOk()
            ->assertSee('name="sms_provider"', false)
            ->assertDontSee('Beem Africa', false)
            ->assertSee('unitxt', false);
    }

    public function test_unitxt_driver_health_requires_credentials(): void
    {
        $driver = new UnitxtDriver('', '', '');
        $result = $driver->healthCheck();
        $this->assertFalse($result['ok']);
        $this->assertSame('unitxt', $result['provider']);

        $ready = new UnitxtDriver('key-123', '', 'KOPAFASTA');
        $ok = $ready->healthCheck();
        $this->assertTrue($ok['ok']);
        $this->assertSame('unitxt', $ok['provider']);
    }

    public function test_sms_manager_resolves_unitxt(): void
    {
        \App\Models\Setting::setMany([
            'gateway.sms_provider' => 'unitxt',
            'gateway.sms_api_key' => 'demo-key',
            'gateway.sms_sender_id' => 'KOPAFASTA',
        ]);
        \Illuminate\Support\Facades\Cache::forget('sms.settings.v1');

        app(\App\Services\Messaging\TransactionalMessagingService::class)->save([
            'enabled' => true,
            'force_log_driver' => false,
            'overdue_reminders' => true,
            'reminder_offsets_days' => '3,1,0',
            'channels' => ['sms' => true, 'email' => true, 'in_app' => true, 'whatsapp' => false, 'push' => false],
            'events' => [],
            'whatsapp' => ['provider' => 'log', 'api_url' => '', 'api_token' => '', 'from_number' => ''],
        ]);

        $this->app->forgetInstance(SmsManager::class);
        $this->assertInstanceOf(UnitxtDriver::class, app(SmsManager::class)->driver());
    }
}
