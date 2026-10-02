<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\CustomerSupportWorkspaceService;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportHandoverP0FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_escalate_places_same_conversation_in_waiting_immediately(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715111222']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-HOP0-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Handover',
            'last_name' => 'Member',
            'phone' => '255715111222',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $start = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'start',
        ])->assertOk();
        $conversationId = (int) $start->json('conversation_id');
        $number = (string) $start->json('conversation_number');
        $this->assertNotSame('', $number);

        $conversation = SupportConversation::query()->findOrFail($conversationId);
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        $meta['phase'] = 'escalate_offer';
        $meta['audience'] = 'member';
        $meta['persona_key'] = $meta['persona_key'] ?? 'amani';
        $conversation->update(['automation_meta' => $meta, 'customer_id' => $customer->id]);

        $escalate = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'escalate',
            'conversation_id' => $conversationId,
        ])->assertOk();

        $conversation->refresh();
        $this->assertSame($conversationId, (int) $escalate->json('conversation_id'));
        $this->assertSame($number, $conversation->publicNumber());
        $this->assertTrue((bool) $conversation->needs_human);
        $this->assertSame(SupportConversationService::STATUS_WAITING, $conversation->status);
        $this->assertNull($conversation->assigned_to);
        $this->assertNotNull($conversation->waiting_since);
        $this->assertSame(SupportAutomationService::STATE_ESCALATED, $conversation->handling_state);

        $waiting = app(CustomerSupportWorkspaceService::class)->waitingConversations();
        $this->assertTrue($waiting->contains('id', $conversationId), 'Escalated CNV must appear in waiting queue immediately');
        $this->assertSame($conversationId, $waiting->first()->id, 'Newest handover sorts first by waiting_since');

        $serialized = app(CustomerSupportWorkspaceService::class)->serializeConversation($conversation);
        $this->assertTrue((bool) $serialized['is_waiting']);
        $this->assertNotNull($serialized['waiting_label']);

        $ack = app(SupportConversationService::class)->waitingAcknowledgement();
        $this->assertStringContainsString('Tumepokea ujumbe wako', $ack);
        $this->assertStringContainsString('Tafadhali subiri kidogo', $ack);
    }

    public function test_guest_escalate_never_enters_human_waiting_queue(): void
    {
        $automation = app(SupportAutomationService::class);
        $payload = $automation->start(
            null,
            null,
            'guest',
            'Guest A',
            '255715999888',
            null,
            'sw',
            'Guest',
        );
        $conversation = SupportConversation::query()->findOrFail((int) $payload['conversation_id']);

        $result = $automation->step($conversation, 'escalate', [
            'guest_name' => 'Guest A',
            'guest_phone' => '255715999888',
        ], null, null, 'sw');

        $conversation->refresh();
        $this->assertFalse((bool) $conversation->needs_human);
        $this->assertNotSame(SupportConversationService::STATUS_WAITING, $conversation->status);
        $this->assertTrue((bool) ($result['show_join_cta'] ?? false));
        $this->assertFalse(
            app(CustomerSupportWorkspaceService::class)->waitingConversations()->contains('id', $conversation->id)
        );
    }

    public function test_optional_followup_locks_with_gentle_waiting_ack_only_once(): void
    {
        app()->setLocale('sw');
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715333444']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-HOP0-2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Lock',
            'last_name' => 'Test',
            'phone' => '255715333444',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $conversation = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-TEST01',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_WAITING,
            'needs_human' => true,
            'handling_state' => SupportAutomationService::STATE_ESCALATED,
            'waiting_since' => now(),
            'automation_meta' => [
                'waiting_followup_allowed' => true,
                'waiting_followup_used' => false,
                'persona_key' => 'neema',
                'persona_name' => 'Neema',
            ],
        ]);

        $service = app(SupportConversationService::class);
        $first = $service->requestHuman($customer, $user, 'Here is one more detail.');
        $this->assertSame($conversation->id, $first->id);
        $acks = $first->messages()->where('is_automated', true)->where('body', 'like', 'Tumepokea ujumbe wako%')->count();
        $this->assertSame(1, $acks);

        $this->expectExceptionMessage('composer_locked');
        $service->requestHuman($customer, $user, 'Spam after lock');
    }
}
