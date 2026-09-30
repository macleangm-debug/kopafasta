<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportQuickReplyService;
use App\Services\Support\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportOpsUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);
    }

    private function agent(string $name = 'Rogathe Nyela'): User
    {
        return User::factory()->create([
            'role' => 'agent',
            'roles' => ['agent'],
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function member(): array
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'roles' => ['borrower'],
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-OPS-001',
            'first_name' => 'Maclean',
            'last_name' => 'Mwaijonga',
            'phone' => '255700999001',
            'status' => 'active',
        ]);

        return [$user, $customer];
    }

    public function test_member_support_page_is_faq_first(): void
    {
        [$user] = $this->member();

        $this->actingAs($user)
            ->get(route('site.borrower.support'))
            ->assertOk()
            ->assertSee(__('borrower.support_page.faq_title'), false)
            ->assertSee(__('borrower.support_page.talk_to_team'), false)
            ->assertSee(__('borrower.support_page.still_need_help'), false);
    }

    public function test_habari_message_is_clean_and_visible_in_inbox(): void
    {
        [$user, $customer] = $this->member();

        $this->actingAs($user)
            ->postJson(route('site.borrower.support.speak'), [
                'body' => 'Habari',
                'context' => "Bot: long FAQ dump\nMember: earlier",
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $conversation = SupportConversation::query()->where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($conversation);
        $this->assertSame('waiting', $conversation->status);
        $this->assertTrue($conversation->needs_human);

        $memberBodies = $conversation->messages()->where('sender_type', 'customer')->pluck('body')->all();
        $this->assertContains('Habari', $memberBodies);
        $this->assertFalse(collect($memberBodies)->contains(fn ($b) => str_contains($b, 'Bot: long FAQ')));

        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.inbox', ['filter' => 'waiting', 'q' => 'Habari']))
            ->assertOk()
            ->assertSee('Maclean Mwaijonga', false)
            ->assertSee('Habari', false);
    }

    public function test_existing_conversation_resumes_without_duplicate(): void
    {
        [$user, $customer] = $this->member();
        $svc = app(SupportConversationService::class);

        $first = $svc->requestHuman($customer, $user, 'First');
        $second = $svc->requestHuman($customer, $user, 'Second');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SupportConversation::query()->where('customer_id', $customer->id)->whereNotIn('status', ['resolved', 'closed'])->count());
    }

    public function test_accept_sends_named_agent_introduction(): void
    {
        $admin = $this->admin();
        $agent = $this->agent('Rogathe Nyela');
        [$user, $customer] = $this->member();

        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Habari');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.inbox.accept', $conversation))
            ->assertRedirect();

        $conversation->refresh();
        $this->assertSame($agent->id, (int) $conversation->assigned_to);
        $this->assertSame('assigned', $conversation->status);
        $this->assertTrue(
            $conversation->messages()->where('body', 'like', '%jina langu ni Rogathe%')->exists()
        );
        $this->assertTrue(
            $conversation->messages()->where('body', 'like', '%Habari Maclean%')->exists()
        );
    }

    public function test_resolve_conversation_without_case(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Quick question');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.inbox.resolve', $conversation))
            ->assertRedirect(route('admin.support.inbox'));

        $this->assertSame('resolved', $conversation->fresh()->status);
        $this->assertSame(0, SupportTicket::query()->where('support_conversation_id', $conversation->id)->count());
    }

    public function test_create_case_does_not_auto_escalate(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Investigate please');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.inbox.create-case', $conversation), [
            'subject' => 'Needs investigation',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->where('subject', 'Needs investigation')->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->escalated_to_role);
        $this->assertSame($conversation->id, (int) $ticket->support_conversation_id);
    }

    public function test_specialist_response_stays_internal(): void
    {
        $admin = $this->admin();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Payment stuck');
        $before = $conversation->messages()->count();

        $ticket = app(SupportTicketService::class)->create([
            'customer_id' => $customer->id,
            'support_conversation_id' => $conversation->id,
            'subject' => 'Payment',
            'description' => 'x',
            'source' => 'chatbot',
            'assigned_to' => $admin->id,
        ]);
        app(SupportTicketService::class)->escalate($ticket, 'credit', 'Looks inconsistent', $admin, null, false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.specialist-response', $ticket), [
                'body' => 'Ask member to resubmit ID image.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $ticket->id,
            'event' => 'specialist_response',
            'body' => 'Ask member to resubmit ID image.',
        ]);
        $this->assertSame($before, $conversation->fresh()->messages()->count());
    }

    public function test_phone_interaction_guest_and_member(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [, $customer] = $this->member();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.interactions.store'), [
            'channel' => 'phone',
            'customer_id' => $customer->id,
            'body' => 'Called about guarantor status. Advised waiting.',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_conversations', [
            'customer_id' => $customer->id,
            'channel' => 'phone',
        ]);

        $this->post(route('admin.support.interactions.store'), [
            'channel' => 'phone',
            'guest_name' => 'Guest Juma',
            'guest_phone' => '255711000088',
            'body' => 'Asked how to register.',
            'create_case' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('support_conversations', [
            'guest_phone' => '255711000088',
            'channel' => 'phone',
        ]);
        $this->assertDatabaseHas('support_tickets', [
            'guest_name' => 'Guest Juma',
        ]);
    }

    public function test_quick_replies_include_introduction_and_signature(): void
    {
        $svc = app(SupportQuickReplyService::class);
        $keys = collect($svc->defaults())->pluck('key')->all();
        $this->assertContains('introduction', $keys);
        $composed = $svc->compose('received', 'sw');
        $this->assertStringContainsString('Tumepokea ombi lako', $composed);
        $this->assertStringContainsString('Kopafasta Customer Support', $composed);
        $this->assertStringContainsString('Simu:', $composed);
    }

    public function test_notifications_route_redirects_to_inbox(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.notifications'))
            ->assertRedirect(route('admin.support.inbox'));
    }
}
