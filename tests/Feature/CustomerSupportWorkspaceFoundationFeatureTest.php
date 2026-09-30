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

    public function test_entering_support_workspace_is_role_first_not_ops_dashboard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'workspace_key' => 'support',
            ])
            ->assertRedirect(route('admin.support.home'));

        $this->assertSame($admin->id, Auth::guard('admin')->id());
        $this->assertSame('Support', app(AdminRoleViewService::class)->bannerLabel());

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Support', false)
            ->assertSee('Support queue', false)
            ->assertSee(__('admin.role_view.staff_all'), false)
            ->assertDontSee('Operations dashboard', false);

        $this->assertSame('Support', app(AdminRoleViewService::class)->bannerLabel());
        $this->assertSame('support', app(AdminRoleViewService::class)->active()['role_key']);
    }

    public function test_support_shell_nav_hides_admin_workspaces(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'workspace_key' => 'support',
            ]);

        $html = $this->get(route('admin.support.home'))->assertOk()->getContent();
        $navChunk = Str::before(Str::after($html, 'aria-label="Main navigation"'), '</nav>');

        foreach (['Home', 'Inbox', 'Cases', 'Members', 'Reports'] as $label) {
            $this->assertMatchesRegularExpression('/>\s*'.preg_quote($label, '/').'\s*</', $navChunk);
        }
        $this->assertDoesNotMatchRegularExpression('/>\s*Notifications\s*</', $navChunk);
        $this->assertStringNotContainsString('Lending', $navChunk);
        $this->assertStringNotContainsString('Money', $navChunk);
        $this->assertStringNotContainsString('Partners', $navChunk);
        $this->assertStringNotContainsString('Growth', $navChunk);
    }

    public function test_selecting_staff_filters_queue_and_sets_availability_on_person(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->post(route('admin.role-view.select-staff'), ['staff_id' => $agent->id])
            ->assertRedirect(route('admin.support.home'));

        $this->post(route('admin.support.availability'), ['availability' => 'online'])
            ->assertRedirect();

        $agent->refresh();
        $this->assertSame('online', app(CustomerSupportWorkspaceService::class)->availability($agent));
    }

    public function test_queue_and_case_reuse_existing_models_for_member_and_guest(): void
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

        $guest = SupportConversation::create([
            'channel' => 'web_chat',
            'status' => 'open',
            'needs_human' => true,
            'last_message_at' => now(),
        ]);
        $guest->messages()->create([
            'sender_type' => 'guest',
            'body' => 'I need help before registering',
            'is_automated' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Maclean Mwaijonga', false)
            ->assertSee('Payment issue', false);

        $this->get(route('admin.support.inbox.show', $guest))
            ->assertOk()
            ->assertSee('Guest / Non-member', false);

        $this->post(route('admin.support.inbox.create-case', $conversation), [
            'subject' => 'Payment follow-up',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Payment follow-up',
            'customer_id' => $customer->id,
            'source' => 'chatbot',
        ]);
    }

    public function test_viewing_banner_and_exit_preserved(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support']);

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee(__('admin.role_view.viewing'), false)
            ->assertSee(__('admin.role_view.exit'), false);

        $this->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
    }
}
