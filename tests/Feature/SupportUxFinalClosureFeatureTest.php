<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\NotificationCtaService;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportUxFinalClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_notification_has_no_conversation_cta(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715333100', 'name' => 'Notif']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-NOTIF',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Notif',
            'last_name' => 'User',
            'phone' => '255715333100',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-NTF001',
            'customer_id' => $customer->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_CLOSED,
            'topic' => 'Malipo',
            'rating_requested_at' => now(),
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        app(SupportConversationService::class)->notifyRatingRequest($cnv);

        $log = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'support_resolved')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('/borrower/support', (string) $log->recipient);
        $this->assertStringNotContainsString('chat=1', (string) ($log->message ?? ''));

        $cta = app(NotificationCtaService::class)->resolve($log);
        $this->assertNull($cta['action_url']);
        $this->assertNull($cta['action_label']);
    }

    public function test_fresh_digital_start_returns_category_choices_not_human_mode(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715333101', 'name' => 'Fresh']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-FRESH',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Fresh',
            'last_name' => 'Member',
            'phone' => '255715333101',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $payload = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'start',
        ])->assertOk()->json();

        $this->assertNotEmpty($payload['choices'] ?? []);
        $this->assertSame('category', $payload['phase'] ?? null);
        $this->assertNotSame('human', $payload['mode'] ?? null);
        $this->assertFalse((bool) ($payload['needs_human'] ?? false));
        $this->assertGreaterThan(0, count($payload['messages'] ?? []));
    }

    public function test_start_does_not_restart_bot_flow_after_human_handover(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715333102', 'name' => 'Handover']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-HAND',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Hand',
            'last_name' => 'Over',
            'phone' => '255715333102',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'start',
        ])->assertOk();

        $cnv = SupportConversation::query()->where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($cnv);

        $esc = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'escalate',
            'conversation_id' => $cnv->id,
            'key' => 'human',
        ])->assertOk()->json();

        $cnv->refresh();
        $this->assertTrue((bool) $cnv->needs_human);
        $this->assertSame(SupportConversationService::STATUS_WAITING, $cnv->status);
        $this->assertStringContainsString('Tunatafuta mtoa huduma', implode(' ', array_column($esc['messages'] ?? [], 'text')));

        $again = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'start',
            'conversation_id' => $cnv->id,
        ])->assertOk()->json();

        $this->assertSame('human', $again['mode'] ?? null);
        $this->assertSame([], $again['choices'] ?? []);
        $this->assertSame($cnv->id, (int) ($again['conversation_id'] ?? 0));
    }

    public function test_waiting_acknowledgement_uses_finding_agent_copy(): void
    {
        app()->setLocale('sw');
        $this->assertSame(
            'Tunatafuta mtoa huduma anayefaa kukusaidia. Tafadhali subiri kidogo.',
            app(SupportConversationService::class)->waitingAcknowledgement()
        );
        app()->setLocale('en');
        $this->assertStringContainsString(
            'finding the right support agent',
            app(SupportConversationService::class)->waitingAcknowledgement()
        );
    }

    public function test_customers_nav_label_is_wateja_not_mawasiliano(): void
    {
        $this->assertSame('Wateja', __('admin.support.nav.members', [], 'sw'));
        $this->assertSame('Customers', __('admin.support.nav.members', [], 'en'));
        $this->assertSame('Wanachama', __('admin.support.contacts.members', [], 'sw'));
        $this->assertSame('Wageni', __('admin.support.contacts.guests', [], 'sw'));
        $this->assertSame('Washirika', __('admin.support.contacts.partners', [], 'sw'));
    }

    public function test_glass_css_includes_visible_surface_and_one_time_glare(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.kf-glass-hero__surface', $css);
        $this->assertStringContainsString('kf-glass-glare', $css);
        $this->assertStringContainsString('animation: kf-glass-glare 1.05s ease-out 1 both', $css);
        $this->assertStringContainsString('rgba(255, 255, 255, 0.14)', $css);
        $this->assertStringContainsString('backdrop-filter: blur(10px)', $css);
        $this->assertStringNotContainsString('.kf-glass-hero > *:not(.kf-glass-hero__surface)', $css);
        $this->assertStringContainsString('padding-block: 3rem', $css);
        $this->assertStringContainsString('padding-block: 5rem', $css);
    }
}
