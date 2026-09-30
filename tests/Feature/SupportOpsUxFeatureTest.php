<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
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
            'preferences' => ['support_availability' => 'online'],
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
            ->assertSee('Kituo cha Usaidizi', false)
            ->assertSee('Ongea na Timu ya Usaidizi', false)
            ->assertSee('HOW TO', false);
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
        $this->assertTrue(
            $conversation->messages()->where('is_automated', true)->where('body', 'like', '%Kopafasta Customer Support%')->exists()
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

        $this->post(route('admin.support.inbox.resolve', $conversation), [
            'resolution_category' => 'answered',
            'ask_rating' => 1,
        ])
            ->assertRedirect(route('admin.support.inbox', ['filter' => 'waiting']));

        $fresh = $conversation->fresh();
        $this->assertSame('closed', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNotNull($fresh->rating_requested_at);
        $this->assertSame('answered', $fresh->resolution_category);
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
            'party' => 'registered',
            'channel' => 'phone',
            'customer_id' => $customer->id,
            'subject_key' => 'guarantor',
            'body' => 'Called about guarantor status. Advised waiting.',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_conversations', [
            'customer_id' => $customer->id,
            'channel' => 'phone',
        ]);
        $this->assertDatabaseMissing('support_tickets', [
            'customer_id' => $customer->id,
            'subject' => 'Guarantor',
        ]);

        $this->post(route('admin.support.interactions.store'), [
            'party' => 'non_member',
            'channel' => 'phone',
            'guest_first_name' => 'Juma',
            'guest_last_name' => 'Guest',
            'guest_phone' => '255711000088',
            'subject_key' => 'how_to_join',
            'body' => 'Asked how to register.',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_conversations', [
            'guest_phone' => '255711000088',
            'channel' => 'phone',
            'guest_name' => 'Juma Guest',
        ]);
        $this->assertDatabaseMissing('support_tickets', [
            'guest_phone' => '255711000088',
        ]);
    }

    public function test_new_conversation_waits_on_team_not_historical_agent(): void
    {
        [$user, $customer] = $this->member();
        $agent = $this->agent('Rogathe Nyela');
        $svc = app(SupportConversationService::class);

        $first = $svc->requestHuman($customer, $user, 'Old issue');
        $svc->accept($first, $agent);
        $svc->resolve($first, $agent, 'Done', 'answered', true);
        $this->assertSame('closed', $first->fresh()->status);

        $second = $svc->requestHuman($customer, $user, 'New issue');
        $this->assertNotSame($first->id, $second->id);
        $this->assertNull($second->assigned_to);
        $this->assertSame('waiting', $second->status);

        $presence = $svc->memberChatPresence($second);
        $this->assertNull($presence['agent_first_name']);
        $this->assertSame('online', $presence['presence']);
        $this->assertStringContainsString('Waiting', $presence['desk_label']);
        $this->assertTrue(
            $second->messages()->where('is_automated', true)->where(function ($q) {
                $q->where('body', 'like', 'Tumepokea ujumbe wako%')
                    ->orWhere('body', 'like', 'We received your message%');
            })->exists()
        );
    }

    public function test_member_chat_presence_shows_assigned_agent_first_name(): void
    {
        [$user, $customer] = $this->member();
        $agent = $this->agent('Rogathe Mushi');
        $svc = app(SupportConversationService::class);
        $conversation = $svc->requestHuman($customer, $user, 'Need help');

        $before = $svc->memberChatPresence($conversation->fresh());
        $this->assertNull($before['agent_first_name']);
        $this->assertSame('online', $before['presence']);

        $svc->accept($conversation, $agent);

        $presence = $svc->memberChatPresence($conversation->fresh('assignedTo'));
        $this->assertSame('assigned', $presence['presence']);
        $this->assertSame('Rogathe', $presence['agent_first_name']);
        $this->assertSame((int) $agent->id, (int) $presence['assigned_to']);
    }

    public function test_support_home_does_not_auto_open_chat(): void
    {
        [$user, $customer] = $this->member();
        app(SupportConversationService::class)->requestHuman($customer, $user, 'Hello');

        $html = $this->actingAs($user)
            ->get(route('site.borrower.support'))
            ->assertOk()
            ->getContent();
        $this->assertTrue(
            str_contains($html, 'Continue conversation')
            || str_contains($html, 'Mazungumzo yanayoendelea')
            || str_contains($html, 'Endelea mazungumzo')
            || str_contains($html, 'Active support')
            || str_contains($html, 'Usaidizi hai'),
            'Support Home should show continue / Active support when an open thread exists'
        );
        $this->assertStringContainsString('human: false', $html);

        $this->actingAs($user)
            ->get(route('site.borrower.support', ['chat' => 1]))
            ->assertOk()
            ->assertSee('Kopafasta Support', false);
    }

    public function test_timestamps_serialize_in_display_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Africa/Dar_es_Salaam']);
        [$user, $customer] = $this->member();
        $svc = app(SupportConversationService::class);
        $conversation = $svc->requestHuman($customer, $user, 'Timezone check');
        $msg = $conversation->messages()->where('sender_type', 'customer')->latest('id')->first();
        $this->assertNotNull($msg);
        $msg->forceFill(['created_at' => \Illuminate\Support\Carbon::parse('2026-09-30 12:49:00', 'UTC')])->saveQuietly();

        $serialized = $svc->serializeMessages($conversation->fresh());
        $row = collect($serialized)->firstWhere('id', $msg->id);
        $this->assertSame('15:49', $row['time']);
    }

    public function test_quick_replies_signature_only_on_introduction(): void
    {
        $svc = app(SupportQuickReplyService::class);
        $keys = collect($svc->defaults())->pluck('key')->all();
        $this->assertContains('introduction', $keys);

        $received = $svc->compose('received', 'sw', [], false);
        $this->assertStringContainsString('Tumepokea ombi lako', $received);
        $this->assertStringNotContainsString('Kopafasta Customer Support', $received);

        $intro = $svc->compose('introduction', 'sw', [
            'member_first_name' => 'Maclean',
            'agent_first_name' => 'Rogathe',
        ], true);
        $this->assertStringContainsString('Habari Maclean', $intro);
        $this->assertStringContainsString('Rogathe', $intro);
        $this->assertStringContainsString('Kopafasta Customer Support', $intro);
        $this->assertStringContainsString('Simu:', $intro);

        $broken = $svc->compose('introduction', 'sw', [], false);
        $this->assertStringNotContainsString('Habari ,', $broken);
        $this->assertStringNotContainsString('jina langu ni  kutoka', $broken);
    }

    public function test_accept_without_staff_stays_on_inbox_with_error(): void
    {
        $admin = $this->admin();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Need help');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->post(route('admin.support.inbox.accept', $conversation))
            ->assertRedirect(route('admin.support.inbox.show', $conversation))
            ->assertSessionHas('error');

        $this->assertNull($conversation->fresh()->assigned_to);
    }

    public function test_accept_refuses_offline_agent_without_unassigning_later(): void
    {
        $admin = $this->admin();
        $agent = $this->agent('Rogathe Nyela');
        $agent->forceFill([
            'preferences' => array_merge((array) $agent->preferences, ['support_availability' => 'offline']),
        ])->save();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Need agent');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.inbox.accept', $conversation))
            ->assertRedirect(route('admin.support.inbox.show', $conversation))
            ->assertSessionHas('error');

        $this->assertNull($conversation->fresh()->assigned_to);

        $agent->forceFill([
            'preferences' => array_merge((array) $agent->preferences, ['support_availability' => 'online']),
        ])->save();
        app(SupportConversationService::class)->accept($conversation->fresh(), $agent->fresh());
        $this->assertSame($agent->id, (int) $conversation->fresh()->assigned_to);

        $agent->forceFill([
            'preferences' => array_merge((array) $agent->preferences, ['support_availability' => 'offline']),
        ])->save();

        app(SupportConversationService::class)->requestHuman($customer, $user, 'Are you there?');
        $conversation->refresh();
        $this->assertSame($agent->id, (int) $conversation->assigned_to);
        $this->assertTrue(
            $conversation->messages()->where('is_automated', true)->where(function ($q) {
                $q->where('body', 'like', 'Mtoa huduma wako hayupo mtandaoni%')
                    ->orWhere('body', 'like', 'Your Support agent is offline%');
            })->exists()
        );
        // Once per offline stretch — second customer message does not duplicate.
        app(SupportConversationService::class)->requestHuman($customer, $user, 'Still waiting');
        $this->assertSame(
            1,
            $conversation->messages()->where('is_automated', true)->where(function ($q) {
                $q->where('body', 'like', 'Mtoa huduma wako hayupo mtandaoni%')
                    ->orWhere('body', 'like', 'Your Support agent is offline%');
            })->count()
        );
    }

    public function test_waiting_acknowledgement_allows_follow_up_without_resetting_waiting_since(): void
    {
        [$user, $customer] = $this->member();
        $svc = app(SupportConversationService::class);
        $frozen = now()->startOfSecond();
        \Illuminate\Support\Carbon::setTestNow($frozen);
        app()->setLocale('sw');
        $first = $svc->requestHuman($customer, $user, 'First ping');
        $since = $first->waiting_since?->copy();
        $this->assertNotNull($since);
        $this->assertStringContainsString('Unaweza kuongeza maelezo mengine hapa wakati unasubiri', $svc->waitingAcknowledgement());
        app()->setLocale('en');
        $this->assertStringContainsString('You can add more details here while you wait', $svc->waitingAcknowledgement());

        \Illuminate\Support\Carbon::setTestNow($frozen->copy()->addMinutes(2));
        $second = $svc->requestHuman($customer, $user, 'More detail while waiting');
        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->waiting_since?->equalTo($since));
        $this->assertFalse(in_array($second->status, ['closed', 'resolved'], true));
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_member_support_round_trip_same_conversation(): void
    {
        $admin = $this->admin();
        $agent = $this->agent('Rogathe Nyela');
        [$user, $customer] = $this->member();

        $this->actingAs($user)
            ->postJson(route('site.borrower.support.speak'), ['body' => 'P0-A'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $conversation = SupportConversation::query()->where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($conversation);
        $msgA = $conversation->messages()->where('body', 'P0-A')->where('sender_type', 'customer')->first();
        $this->assertNotNull($msgA);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->postJson(route('admin.support.inbox.accept', $conversation))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assigned_to', $agent->id);

        $conversation->refresh();
        $this->assertSame($agent->id, (int) $conversation->assigned_to);
        $this->assertTrue($conversation->messages()->where('body', 'like', '%jina langu ni Rogathe%')->exists());
        $this->assertTrue($conversation->messages()->where('body', 'like', '%Simu:%')->exists());

        $reply = $this->postJson(route('admin.support.inbox.reply', $conversation), ['body' => 'P0-B'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->json();
        $msgB = (int) ($reply['message_id'] ?? 0);
        $this->assertGreaterThan(0, $msgB);

        $thread = $this->actingAs($user)
            ->getJson(route('site.borrower.support.thread'))
            ->assertOk()
            ->json();
        $this->assertSame($conversation->id, (int) $thread['conversation_id']);
        $texts = collect($thread['messages'])->pluck('text')->all();
        $this->assertContains('P0-A', $texts);
        $this->assertContains('P0-B', $texts);

        $this->postJson(route('site.borrower.support.speak'), ['body' => 'P0-C'])
            ->assertOk();

        $staffThread = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support.inbox.thread', $conversation))
            ->assertOk()
            ->json();
        $staffTexts = collect($staffThread['messages'])->pluck('text')->all();
        $this->assertContains('P0-C', $staffTexts);

        $this->postJson(route('admin.support.inbox.reply', $conversation), ['body' => 'P0-D'])
            ->assertOk();

        $final = $this->actingAs($user)
            ->getJson(route('site.borrower.support.thread'))
            ->assertOk()
            ->json();
        $finalTexts = collect($final['messages'])->pluck('text')->all();
        $this->assertContains('P0-D', $finalTexts);
        $this->assertSame($conversation->id, (int) $final['conversation_id']);
        $this->assertSame('Rogathe', $final['agent_first_name'] ?? null);
        $this->assertSame('assigned', $final['presence'] ?? null);
        $this->assertSame(1, SupportConversation::query()->where('customer_id', $customer->id)->whereNotIn('status', ['resolved', 'closed'])->count());

        $this->postJson(route('site.borrower.support.speak'), ['body' => 'Kaka-2'])
            ->assertOk();

        $kakaThread = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support.inbox.thread', $conversation))
            ->assertOk()
            ->json();
        $this->assertContains('Kaka-2', collect($kakaThread['messages'])->pluck('text')->all());
        $this->assertSame($conversation->id, (int) $kakaThread['conversation_id']);
    }

    public function test_admin_can_reset_staff_password_without_exposing_current(): void
    {
        $admin = $this->admin();
        $staff = $this->agent('Asha Support');
        $oldHash = $staff->password;

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.edit', $staff))
            ->assertOk()
            ->assertSee('Reset password', false)
            ->assertSee('never shown', false)
            ->assertDontSee('name="current_password"', false);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.users.reset-password', $staff), [
                'password' => 'TempPass9!',
                'password_confirmation' => 'TempPass9!',
            ])
            ->assertRedirect(route('admin.users.edit', $staff))
            ->assertSessionHas('temporary_password', 'TempPass9!');

        $this->assertNotSame($oldHash, $staff->fresh()->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('TempPass9!', $staff->fresh()->password));
    }

    public function test_notifications_route_redirects_to_inbox(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.notifications'))
            ->assertRedirect(route('admin.support.inbox'));
    }

    public function test_waiting_conversation_exposes_accept_only(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Queue me');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $waiting = $this->get(route('admin.support.inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Accept', false)
            ->assertSee('Waiting now', false)
            ->assertSee('Assign to…', false)
            ->getContent();

        $this->assertStringContainsString('Waiting queue', $waiting);
        $this->assertStringNotContainsString('>Create ticket</button>', $waiting);
        $this->assertStringNotContainsString('Write a reply… templates insert here', $waiting);

        $this->postJson(route('admin.support.inbox.accept', $conversation), [
            'agent_id' => $agent->id,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('assigned_to', $agent->id);

        $this->assertSame($agent->id, (int) $conversation->fresh()->assigned_to);

        $active = $this->get(route('admin.support.inbox.show', $conversation->fresh()))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('>Create ticket</button>', $active);
        $this->assertStringContainsString('Resolve</button>', $active);
        $this->assertStringContainsString('Write a reply… templates insert here', $active);
        $this->assertStringContainsString('Search templates', $active);
    }

    public function test_create_ticket_uses_kpf_tkt_number_and_sla(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Long issue');
        app(SupportConversationService::class)->accept($conversation, $agent);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);
        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.inbox.create-case', $conversation), [
            'subject' => 'Payment not reflecting',
            'priority' => 'urgent',
            'category' => 'payments',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->where('support_conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertMatchesRegularExpression('/^KPF-TKT-\d{6}$/', (string) $ticket->ticket_number);
        $this->assertNotNull($ticket->sla_due_at);

        $sla = app(SupportTicketService::class)->slaStatus($ticket);
        $this->assertContains($sla['state'], ['on_track', 'warning']);

        $this->get(route('admin.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->ticket_number, false)
            ->assertSee('Ticket', false)
            ->assertSee('Conversation', false)
            ->assertSee('Activity', false)
            ->assertDontSee('>Reply</button>', false);
    }

    public function test_resolve_always_requests_rating_and_stores_stars(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();
        $svc = app(SupportConversationService::class);
        $conversation = $svc->requestHuman($customer, $user, 'Done soon');
        $svc->accept($conversation, $agent);
        $svc->resolve($conversation, $agent, null, 'answered', true);

        $conversation->refresh();
        $this->assertSame('closed', $conversation->status);
        $this->assertNotNull($conversation->rating_requested_at);
        $this->assertTrue(
            $conversation->messages()->where('body', 'like', '%Tafadhali tathmini huduma yetu%')->exists()
            || $conversation->messages()->where('body', 'like', '%nyota 1–5%')->exists()
            || $conversation->messages()->where('body', 'like', '%nyota 1-5%')->exists()
        );

        $this->actingAs($user)
            ->postJson(route('site.borrower.support.conversation.rate', $conversation), [
                'rating' => 5,
                'comment' => 'Vizuri sana',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(5, (int) $conversation->fresh()->rating);
    }

    public function test_support_sla_settings_hub_saves_priority_and_issue_overrides(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.support'))
            ->assertOk()
            ->assertSee('Support SLA')
            ->assertSee('Issue SLA matrix')
            ->assertSee('Payments')
            ->assertSee('Recurring Issue flag');

        $categories = \App\Support\SupportTaxonomy::categoryKeys();
        $payload = [
            'warning_percent' => 75,
            'priority_minutes' => [
                'urgent' => 90,
                'high' => 180,
                'normal' => 360,
                'low' => 1200,
            ],
            'recurring_count' => 3,
            'recurring_window_hours' => 12,
            'default_priority' => [],
            'target_minutes' => [],
            'approaching_pct' => [],
        ];
        foreach ($categories as $key) {
            $payload['default_priority'][$key] = $key === 'payments' ? 'urgent' : 'normal';
            $payload['target_minutes'][$key] = $key === 'payments' ? 90 : 480;
            $payload['approaching_pct'][$key] = $key === 'payments' ? 70 : 80;
        }

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.support.save'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(90, (int) \App\Models\Setting::get('support.sla.minutes.urgent'));
        $this->assertSame(75, (int) \App\Models\Setting::get('support.sla.warning_percent'));
        $this->assertSame(3, (int) \App\Models\Setting::get('support.recurring.issue_count'));
        $this->assertSame(12, (int) \App\Models\Setting::get('support.recurring.window_hours'));
        $this->assertSame('urgent', \App\Support\SupportTaxonomy::defaultPriorityFor('payments'));
        $this->assertSame(90, \App\Support\SupportTaxonomy::targetMinutesFor('payments'));
        $this->assertSame(70, \App\Support\SupportTaxonomy::approachingPercentFor('payments'));
    }

    public function test_support_home_surfaces_recurring_issue_flags(): void
    {
        $admin = $this->admin();
        Setting::set('support.recurring.issue_count', 3);
        Setting::set('support.recurring.window_hours', 24);

        for ($i = 1; $i <= 3; $i++) {
            SupportTicket::create([
                'ticket_number' => 'KPF-TKT-REC0'.$i,
                'subject' => 'Payment not reflecting '.$i,
                'description' => 'Aggregate flag test',
                'priority' => 'high',
                'status' => 'open',
                'category' => 'payments',
                'source' => 'admin',
                'contact_kind' => 'customer',
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Recurring issues', false)
            ->assertSee('Payments', false)
            ->assertSee('3 tickets', false);
    }

    public function test_ticket_create_snapshots_sla_fields_and_resolve_sets_time_to_resolve(): void
    {
        Setting::set('support.ticket_categories', array_merge(\App\Support\SupportTaxonomy::defaults(), [
            'target_resolution_minutes' => array_merge(
                \App\Support\SupportTaxonomy::defaults()['target_resolution_minutes'],
                ['payments' => 90]
            ),
            'approaching_threshold_percent' => array_merge(
                \App\Support\SupportTaxonomy::defaults()['approaching_threshold_percent'],
                ['payments' => 70]
            ),
        ]));

        $ticket = app(SupportTicketService::class)->create([
            'subject' => 'Payment not reflecting',
            'category' => 'payments',
            'priority' => 'high',
            'description' => 'SLA snapshot test',
            'source' => 'admin',
            'contact_kind' => 'guest',
            'guest_name' => 'UAT Guest',
        ]);

        $this->assertSame(90, (int) $ticket->sla_target_minutes);
        $this->assertSame(70, (int) $ticket->sla_approaching_pct);
        $this->assertNotNull($ticket->sla_due_at);

        $ticket->forceFill(['created_at' => now()->subMinutes(45)])->save();
        $resolved = app(SupportTicketService::class)->resolveCase($ticket->fresh(), [
            'resolution_type' => 'answered',
            'resolution_notes' => 'Fixed',
        ]);

        $this->assertSame('resolved', $resolved->status);
        $this->assertNotNull($resolved->time_to_resolve_minutes);
        $this->assertGreaterThanOrEqual(45, (int) $resolved->time_to_resolve_minutes);
    }

    public function test_performance_page_shows_range_charts_and_custom(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.performance'))
            ->assertOk()
            ->assertSee('Team performance', false)
            ->assertSee('Conversations received / resolved', false)
            ->assertSee('Top Issues', false)
            ->assertSee('Custom', false);

        $this->get(route('admin.support.performance', [
            'range' => 'custom',
            'from' => now()->subDays(3)->toDateString(),
            'to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Apply', false);
    }

    public function test_support_home_viewing_switcher_shows_team_label(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Viewing', false)
            ->assertSee('Team ▾', false);
    }

    public function test_inbox_agent_picker_uses_action_panel_on_mobile(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();
        $conversation = app(SupportConversationService::class)->requestHuman($customer, $user, 'Need help');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $html = $this->get(route('admin.support.inbox.show', $conversation))->assertOk()->getContent();
        $this->assertStringContainsString('Assign to…', $html);
        $this->assertStringContainsString($agent->name, $html);
        // Mobile sheet uses canonical action-panel (bottom sheet on small screens).
        $this->assertTrue(
            str_contains($html, 'data-integration-live-test-panel')
            || str_contains($html, 'x-site.action-panel')
            || (str_contains($html, 'md:hidden') && str_contains($html, 'Assign to…'))
        );
    }
}
