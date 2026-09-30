<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
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

    private function agent(): User
    {
        return User::factory()->create([
            'role' => 'agent',
            'roles' => ['agent'],
            'name' => 'Support Agent',
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

    public function test_member_speak_to_support_lands_in_inbox_queue(): void
    {
        [$user, $customer] = $this->member();

        $this->actingAs($user)
            ->postJson(route('site.borrower.support.speak'), [
                'body' => 'Nimeomba mkopo lakini sijui kwa nini ombi langu halijasonga.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('support_conversations', [
            'customer_id' => $customer->id,
            'needs_human' => 1,
        ]);

        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.inbox', ['filter' => 'waiting']))
            ->assertOk()
            ->assertSee('Maclean Mwaijonga', false)
            ->assertSee('Nimeomba mkopo', false);
    }

    public function test_inbox_is_three_pane_with_quick_replies(): void
    {
        $admin = $this->admin();
        [$user, $customer] = $this->member();

        $conversation = app(SupportConversationService::class)->requestHuman(
            $customer,
            $user,
            'Nimekwama kwenye guarantor',
            'Loan application',
        );

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Received', false)
            ->assertSee('Create case', false)
            ->assertSee('Open Member 360', false)
            ->assertSee('Nimekwama kwenye guarantor', false);
    }

    public function test_create_case_escalate_and_resolve_notifies_member(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        [$user, $customer] = $this->member();

        $conversation = app(SupportConversationService::class)->requestHuman(
            $customer,
            $user,
            'Help with application',
        );

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id]);

        $this->post(route('admin.support.inbox.create-case', $conversation), [
            'subject' => 'Application stuck',
            'category' => 'application',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->where('subject', 'Application stuck')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($conversation->id, (int) $ticket->support_conversation_id);

        $this->post(route('admin.support-tickets.escalate', $ticket), [
            'escalated_to_role' => 'credit',
            'reason' => 'Application appears stuck after guarantor completion',
            'notify_member' => 1,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('credit', $ticket->escalated_to_role);
        $this->assertTrue(
            $conversation->fresh()->messages()->where('body', 'like', '%Tumelifikisha%')->exists()
        );

        $this->post(route('admin.support-tickets.resolve', $ticket), [
            'resolution_type' => 'guidance_provided',
            'resolution_notes' => 'Explained guarantor step',
            'invite_rating' => 1,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertTrue(
            $conversation->fresh()->messages()->where('body', 'like', '%limetatuliwa%')->exists()
        );
    }

    public function test_notifications_route_redirects_to_inbox(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.notifications'))
            ->assertRedirect(route('admin.support.inbox'));
    }

    public function test_quick_replies_default_swahili_first(): void
    {
        $replies = app(SupportQuickReplyService::class)->defaults();
        $this->assertNotEmpty($replies);
        $this->assertSame('received', $replies[0]['key']);
        $this->assertStringContainsString('Tumepokea', $replies[0]['body_sw']);
    }

    public function test_duplicate_rating_prevented(): void
    {
        [$user, $customer] = $this->member();
        $ticket = app(SupportTicketService::class)->create([
            'customer_id' => $customer->id,
            'subject' => 'Rate me',
            'description' => 'x',
            'status' => 'resolved',
            'resolved_at' => now(),
            'source' => 'chatbot',
        ]);

        $svc = app(SupportTicketService::class);
        $first = $svc->recordRating($ticket, 5, 'Asante');
        $second = $svc->recordRating($ticket->fresh(), 1, 'Nope');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(5, $second->rating);
    }
}
