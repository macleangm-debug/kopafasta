<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
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
        $this->assertMatchesRegularExpression('/^SUP-\d{4}-\d{6}$/', $ticket->ticket_number);

        $events = SupportTicketEvent::query()
            ->where('support_ticket_id', $ticket->id)
            ->pluck('event')
            ->all();

        $this->assertContains('created', $events);
        $this->assertContains('assigned', $events);
        $this->assertContains('opened', $events);
    }

    public function test_ticket_number_uses_settings_prefix(): void
    {
        Setting::set(SupportTicketService::TICKET_PREFIX_KEY, 'HELP');
        User::factory()->create(['role' => 'agent', 'is_active' => true]);

        $ticket = app(SupportTicketService::class)->create([
            'guest_name' => 'Prefixed',
            'source' => 'admin',
            'subject' => 'Number',
            'description' => 'x',
            'category' => 'general',
        ]);

        $this->assertStringStartsWith('HELP-'.now()->format('Y').'-', $ticket->ticket_number);
        $this->assertMatchesRegularExpression('/^HELP-\d{4}-\d{6}$/', $ticket->ticket_number);
    }

    public function test_roles_json_agent_is_included_in_round_robin(): void
    {
        $agent = User::factory()->create([
            'role' => 'officer',
            'roles' => ['agent'],
            'is_active' => true,
            'name' => 'Hybrid Agent',
        ]);

        $ticket = app(SupportTicketService::class)->create([
            'guest_name' => 'Roles JSON',
            'source' => 'admin',
            'subject' => 'Desk',
            'description' => 'x',
            'category' => 'general',
        ]);

        $this->assertSame($agent->id, $ticket->assigned_to);
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

    public function test_create_form_exposes_member_guest_wizard_without_manual_ticket_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Who is contacting us?', $html);
        $this->assertStringContainsString('>Member</button>', $html);
        $this->assertStringContainsString('>Guest</button>', $html);
        $this->assertStringContainsString('Search name, phone, member number, or email', $html);
        $this->assertStringContainsString('data-step-label="Contact"', $html);
        $this->assertStringContainsString('data-step-label="Issue"', $html);
        $this->assertStringContainsString('data-step-label="Triage / Review"', $html);
        $this->assertStringContainsString('No Support Agents yet', $html);
        $this->assertStringContainsString('supportTicketForm', $html);
        $this->assertStringContainsString('support-tickets-customers', $html);
        $this->assertStringContainsString('Assigned to', $html);
        $this->assertStringNotContainsString('name="ticket_number"', $html);
        $this->assertStringNotContainsString('Ticket # (optional)', $html);
        $this->assertStringNotContainsString('>Customer</button>', $html);
    }

    public function test_admin_create_guest_ticket_via_http_auto_numbers_and_assigns(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true, 'name' => 'Queue Agent']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.store'), [
                'contact_kind' => 'guest',
                'guest_name' => 'Baraka Guest',
                'guest_phone' => '255711223344',
                'guest_email' => 'baraka@example.com',
                'source' => 'admin',
                'category' => 'other',
                'category_other' => 'Billing dispute',
                'subject' => 'Other',
                'subject_other' => 'Wrong fee charged',
                'description' => 'Please review.',
                'priority' => 'normal',
                'status' => 'open',
                'assigned_to' => '',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->customer_id);
        $this->assertSame('guest', $ticket->contact_kind);
        $this->assertSame('Billing dispute', $ticket->category);
        $this->assertSame('Wrong fee charged', $ticket->subject);
        $this->assertSame($agent->id, $ticket->assigned_to);
        $this->assertMatchesRegularExpression('/^SUP-\d{4}-\d{6}$/', $ticket->ticket_number);
        $this->assertDatabaseMissing('customers', ['first_name' => 'Baraka Guest']);
    }

    public function test_supervisor_can_override_assignment_on_create(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'agent', 'is_active' => true, 'name' => 'Auto One']);
        $chosen = User::factory()->create(['role' => 'agent', 'is_active' => true, 'name' => 'Chosen Agent']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.store'), [
                'contact_kind' => 'guest',
                'guest_name' => 'Override Guest',
                'guest_phone' => '255700111222',
                'source' => 'admin',
                'category' => 'general',
                'subject' => 'Account question',
                'description' => 'x',
                'priority' => 'normal',
                'status' => 'open',
                'assigned_to' => $chosen->id,
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->latest('id')->first();
        $this->assertSame($chosen->id, $ticket->assigned_to);

        $assigned = SupportTicketEvent::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('event', 'assigned')
            ->first();
        $this->assertNotNull($assigned);
        $this->assertSame('manual', $assigned->meta['mode'] ?? null);
    }

    public function test_member_search_matches_number_phone_email_and_hides_phone_local(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-SEARCH-99',
            'member_no' => 'KPF-TZ-SEARCH99',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Mushi',
            'phone' => '255799111222',
            'email' => 'neema.search@example.com',
        ]);
        $phoneLocal = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-PHONE-LOCAL',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Local',
            'last_name' => 'Only',
            'phone' => '255799333444',
            'email' => '255799333444@phone.kopafasta.local',
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => 'KPF-TZ-SEARCH']))
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => '799111222']))
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);

        $label = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => 'neema.search']))
            ->assertOk()
            ->json('data.0.label');
        $this->assertStringContainsString('neema.search@example.com', $label);
        $this->assertStringNotContainsString('@phone.kopafasta.local', $label);

        $localLabel = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => 'CU-PHONE-LOCAL']))
            ->assertOk()
            ->json('data.0.label');
        $this->assertSame($phoneLocal->id, $this->actingAs($admin, 'admin')
            ->getJson(route('admin.support-tickets.customers', ['q' => 'CU-PHONE-LOCAL']))
            ->json('data.0.id'));
        $this->assertStringNotContainsString('@phone.kopafasta.local', $localLabel);
    }
}
