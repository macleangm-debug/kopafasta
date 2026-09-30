<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AdminRoleViewService;
use App\Services\Support\CustomerSupportWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSupportWorkspaceFoundationFeatureTest extends TestCase
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

    public function test_entering_customer_support_role_routes_to_support_workspace_not_ops_dashboard(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ])
            ->assertRedirect(route('admin.support.home'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('agent', $ctx['role_key']);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Customer Support', false)
            ->assertSee('Rogathe Nyela', false)
            ->assertSee('My queue', false)
            ->assertSee('Online', false)
            ->assertDontSee('Operations dashboard', false)
            ->assertDontSee('capital_available', false);
    }

    public function test_support_shell_nav_hides_admin_workspaces(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ]);

        $html = $this->get(route('admin.support.home'))
            ->assertOk()
            ->getContent();

        foreach (['Home', 'Inbox', 'Cases', 'Members', 'Notifications', 'Reports'] as $label) {
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*</',
                $html,
                "Missing support nav label {$label}"
            );
        }

        $navChunk = Str::before(Str::after($html, 'aria-label="Main navigation"'), '</nav>');
        $this->assertStringNotContainsString('Lending', $navChunk);
        $this->assertStringNotContainsString('Money', $navChunk);
        $this->assertStringNotContainsString('Partners', $navChunk);
        $this->assertStringNotContainsString('Growth', $navChunk);
    }

    public function test_availability_persists_and_is_audited(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ]);

        $this->post(route('admin.support.availability'), [
            'availability' => 'online',
        ])->assertRedirect();

        $agent->refresh();
        $this->assertSame('online', app(CustomerSupportWorkspaceService::class)->availability($agent));
    }

    public function test_queue_and_case_reuse_existing_conversation_and_ticket_models(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $customer = Customer::create([
            'customer_number' => 'CU-SUP-001',
            'first_name' => 'Maclean',
            'last_name' => 'Mwaijonga',
            'phone' => '255700123456',
            'status' => 'active',
        ]);

        $conversation = SupportConversation::create([
            'customer_id' => $customer->id,
            'channel' => 'web_chat',
            'status' => 'open',
            'needs_human' => true,
            'last_message_at' => now()->subMinutes(4),
        ]);
        $conversation->messages()->create([
            'sender_type' => 'customer',
            'body' => 'My payment has gone through but…',
            'is_automated' => false,
        ]);

        SupportTicket::create([
            'ticket_number' => 'SUP-2026-000099',
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
            'subject' => 'Payment issue',
            'description' => 'Needs investigation',
            'priority' => 'high',
            'status' => 'open',
            'category' => 'payment',
            'source' => 'admin',
            'contact_kind' => 'customer',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ]);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Maclean Mwaijonga', false)
            ->assertSee('My payment has gone through', false)
            ->assertSee('Payment issue', false);

        $this->get(route('admin.support.inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Create case', false)
            ->assertSee('View member', false);

        $this->post(route('admin.support.inbox.create-case', $conversation), [
            'subject' => 'Payment follow-up',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Payment follow-up',
            'customer_id' => $customer->id,
            'source' => 'chatbot',
        ]);
    }

    public function test_guest_conversation_is_labeled_non_member(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $conversation = SupportConversation::create([
            'channel' => 'web_chat',
            'status' => 'open',
            'needs_human' => true,
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'sender_type' => 'guest',
            'body' => 'I need help before registering',
            'is_automated' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ]);

        $this->get(route('admin.support.inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Guest / Non-member', false)
            ->assertSee('I need help before registering', false);
    }

    public function test_viewing_banner_and_exit_preserved(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ]);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee(__('admin.role_view.viewing'), false)
            ->assertSee(__('admin.role_view.exit'), false);

        $this->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
    }

    public function test_performance_reports_metric_gaps_without_inventing_values(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ]);

        $this->get(route('admin.support.performance'))
            ->assertOk()
            ->assertSee('Metric gaps', false)
            ->assertSee('No CSAT/rating capture yet', false)
            ->assertSee('No SLA due clock', false);
    }
}
