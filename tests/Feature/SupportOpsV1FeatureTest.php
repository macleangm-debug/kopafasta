<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportOpsV1FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_ticket_auto_assigns_to_active_agent_and_logs_events(): void
    {
        $agent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
            'name' => 'Desk Agent',
        ]);

        $ticket = app(SupportTicketService::class)->create([
            'guest_name' => 'Amina Guest',
            'guest_email' => 'amina@example.com',
            'guest_phone' => '255712345678',
            'contact_kind' => 'guest',
            'source' => 'admin',
            'category' => 'general',
            'subject' => 'Need help',
            'description' => 'Please call me back.',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->assertSame($agent->id, $ticket->assigned_to);
        $this->assertSame('guest', $ticket->contact_kind);
        $this->assertSame('Amina Guest', $ticket->guest_name);

        $events = SupportTicketEvent::query()
            ->where('support_ticket_id', $ticket->id)
            ->pluck('event')
            ->all();

        $this->assertContains('created', $events);
        $this->assertContains('assigned', $events);
    }

    public function test_public_feedback_creates_ticket_with_guest_fields(): void
    {
        $agent = User::factory()->create([
            'role' => 'agent',
            'is_active' => true,
        ]);

        $response = $this->post(route('site.feedback.post'), [
            'category' => 'suggestion',
            'name' => 'Juma Guest',
            'email' => 'juma@example.com',
            'phone' => '255712345678',
            'phone_local' => '712345678',
            'subject' => 'Website tip',
            'message' => 'Please add Swahili tooltips.',
        ]);

        $response->assertRedirect(route('site.feedback', ['open' => 1]));

        $ticket = SupportTicket::query()->latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertSame('public_feedback', $ticket->source);
        $this->assertSame('guest', $ticket->contact_kind);
        $this->assertSame('Juma Guest', $ticket->guest_name);
        $this->assertSame('juma@example.com', $ticket->guest_email);
        $this->assertSame('suggestion', $ticket->category);
        $this->assertSame($agent->id, $ticket->assigned_to);
        $this->assertTrue(
            SupportTicketEvent::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('event', 'created')
                ->exists()
        );
    }

    public function test_complaint_feedback_still_creates_complaint_not_ticket(): void
    {
        User::factory()->create(['role' => 'agent', 'is_active' => true]);

        $beforeTickets = SupportTicket::query()->count();

        $this->post(route('site.feedback.post'), [
            'category' => 'complaint',
            'name' => 'Angry Guest',
            'email' => 'angry@example.com',
            'subject' => 'Bad experience',
            'message' => 'This was not okay.',
        ])->assertRedirect();

        $this->assertDatabaseHas('complaints', [
            'subject' => 'Bad experience',
        ]);
        $this->assertSame($beforeTickets, SupportTicket::query()->count());
    }

    public function test_round_robin_advances_across_agents(): void
    {
        $a = User::factory()->create(['role' => 'agent', 'is_active' => true, 'name' => 'A']);
        $b = User::factory()->create(['role' => 'agent', 'is_active' => true, 'name' => 'B']);

        $service = app(SupportTicketService::class);
        $t1 = $service->create([
            'guest_name' => 'One',
            'source' => 'admin',
            'subject' => 'First',
            'description' => 'x',
            'category' => 'general',
        ]);
        $t2 = $service->create([
            'guest_name' => 'Two',
            'source' => 'admin',
            'subject' => 'Second',
            'description' => 'x',
            'category' => 'general',
        ]);

        $this->assertSame($a->id, $t1->assigned_to);
        $this->assertSame($b->id, $t2->assigned_to);
    }

    public function test_create_form_exposes_searchable_customer_and_agent_empty_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Who is contacting us?', $html);
        $this->assertStringContainsString('Search name, phone, customer number, or email', $html);
        $this->assertStringContainsString('No Support Agents yet', $html);
        $this->assertStringContainsString('supportTicketForm', $html);
        $this->assertStringContainsString('support-tickets-customers', $html);
    }

    public function test_customer_search_matches_number_phone_and_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = \App\Models\Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SEARCH-99',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Mushi',
            'phone' => '255799111222',
            'email' => 'neema.search@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => 'CU-SEARCH']))
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => '799111222']))
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);
    }
}
