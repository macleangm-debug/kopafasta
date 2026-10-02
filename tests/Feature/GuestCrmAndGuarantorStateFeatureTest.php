<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportGuest;
use App\Models\User;
use App\Services\Support\SupportGuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCrmAndGuarantorStateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_chat_speak_upserts_one_guest_by_phone(): void
    {
        $payload = [
            'body' => 'Habari, nahitaji msaada.',
            'guest_first_name' => 'Asha',
            'guest_last_name' => 'Juma',
            'guest_phone' => '0715000111',
        ];

        $this->postJson(route('site.support.chat.speak'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('guest', true);

        $this->postJson(route('site.support.chat.speak'), array_merge($payload, [
            'body' => 'Second message',
            'guest_first_name' => 'Asha',
            'guest_last_name' => 'Juma Updated',
        ]))->assertOk();

        $this->assertSame(1, SupportGuest::query()->count());
        $guest = SupportGuest::query()->first();
        $this->assertSame('guest', $guest->registration_status);
        $this->assertSame(2, (int) $guest->contact_count);
        $this->assertSame('Asha', $guest->first_name);
        $this->assertSame('Juma', $guest->last_name);
        $this->assertSame('Juma Updated', $guest->presented_last_name);
        $this->assertNotNull($guest->name_mismatch_at);
        $this->assertSame(SupportGuestService::SOURCE_GUEST_CHAT, $guest->source);
    }

    public function test_guest_converts_on_member_registration_and_leaves_active_list(): void
    {
        $guest = SupportGuest::create([
            'phone' => '255715000222',
            'first_name' => 'Guest',
            'last_name' => 'Person',
            'source' => SupportGuestService::SOURCE_GUEST_CHAT,
            'first_contact_at' => now()->subDay(),
            'last_contact_at' => now()->subHour(),
            'contact_count' => 1,
            'registration_status' => 'guest',
        ]);

        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715000222']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-G-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Guest',
            'last_name' => 'Person',
            'phone' => '255715000222',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        app(SupportGuestService::class)->convertOnRegistration('255715000222', $customer, 'member');

        $guest->refresh();
        $this->assertSame('converted', $guest->registration_status);
        $this->assertSame($customer->id, $guest->customer_id);
        $this->assertSame(0, SupportGuest::query()->where('registration_status', 'guest')->count());
    }

    public function test_admin_guests_index_lists_active_guests_only(): void
    {
        SupportGuest::create([
            'phone' => '255715000333',
            'first_name' => 'Active',
            'last_name' => 'Guest',
            'source' => SupportGuestService::SOURCE_PHONE_CALL,
            'first_contact_at' => now(),
            'last_contact_at' => now(),
            'contact_count' => 1,
            'registration_status' => 'guest',
        ]);
        SupportGuest::create([
            'phone' => '255715000334',
            'first_name' => 'Old',
            'last_name' => 'Guest',
            'source' => SupportGuestService::SOURCE_GUEST_CHAT,
            'first_contact_at' => now()->subDays(10),
            'last_contact_at' => now()->subDays(5),
            'contact_count' => 2,
            'registration_status' => 'converted',
            'converted_at' => now()->subDay(),
            'converted_as' => 'member',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.guests.index'))
            ->assertOk()
            ->assertSee('Active Guest')
            ->assertSee('+ Add Guest')
            ->assertDontSee('Old Guest');
    }

    public function test_add_guest_saves_identity_without_support_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.guests.store'), [
                'first_name' => 'Neema',
                'last_name' => 'Dial',
                'guest_phone' => '0715000999',
            ]);

        $guest = SupportGuest::query()->where('phone', '255715000999')->first();
        $this->assertNotNull($guest);
        $response->assertRedirect(route('admin.customers.guests.show', $guest));

        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertDatabaseCount('support_tickets', 0);

        // Same phone upserts — no duplicate Guest.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.guests.store'), [
                'first_name' => 'Neema',
                'last_name' => 'Updated',
                'guest_phone' => '0715000999',
            ])
            ->assertRedirect(route('admin.customers.guests.show', $guest));

        $this->assertSame(1, SupportGuest::query()->count());
        $fresh = $guest->fresh();
        $this->assertSame('Dial', $fresh->last_name);
        $this->assertSame('Updated', $fresh->presented_last_name);
        $this->assertNotNull($fresh->name_mismatch_at);
        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_same_phone_matching_name_reuses_guest_without_mismatch(): void
    {
        $service = app(SupportGuestService::class);
        $first = $service->touchGuest('John', 'Mushi', '0712345678', SupportGuestService::SOURCE_GUEST_CHAT);
        $second = $service->touchGuest('john', 'mushi', '+255712345678', SupportGuestService::SOURCE_GUEST_CHAT);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, SupportGuest::query()->count());
        $this->assertNull($second->fresh()->name_mismatch_at);
        $this->assertSame('John', $second->fresh()->first_name);
    }

    public function test_different_name_same_phone_preserves_canonical_and_flags_discrepancy(): void
    {
        $service = app(SupportGuestService::class);
        $service->touchGuest('John', 'Mushi', '712345678', SupportGuestService::SOURCE_GUEST_CHAT);
        $guest = $service->touchGuest('Peter', 'Mushi', '0712345678', SupportGuestService::SOURCE_PHONE_CALL);

        $this->assertSame(1, SupportGuest::query()->count());
        $this->assertSame('John', $guest->first_name);
        $this->assertSame('Mushi', $guest->last_name);
        $this->assertSame('Peter', $guest->presented_first_name);
        $this->assertSame('Mushi', $guest->presented_last_name);
        $this->assertNotNull($guest->name_mismatch_at);
    }

    public function test_incomplete_tanzania_phone_is_rejected(): void
    {
        $guest = app(SupportGuestService::class)->touchGuest('Asha', 'Juma', '71234567', SupportGuestService::SOURCE_GUEST_CHAT);

        $this->assertNull($guest);
        $this->assertSame(0, SupportGuest::query()->count());
    }

    public function test_staff_cannot_start_guest_conversation_from_new_support(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support.interactions.store'), [
                'party' => 'non_member',
                'support_action' => 'conversation',
                'guest_first_name' => 'Juma',
                'guest_last_name' => 'Caller',
                'guest_phone' => '0715000888',
                'channel' => 'web_chat',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertSame(0, SupportGuest::query()->count());
    }

    public function test_new_support_save_guest_creates_contact_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.support.interactions.store'), [
                'party' => 'non_member',
                'support_action' => 'save_guest',
                'guest_first_name' => 'Asha',
                'guest_last_name' => 'Contact',
                'guest_phone' => '0715000777',
                'channel' => 'web_chat',
            ]);

        $guest = SupportGuest::query()->where('phone', '255715000777')->first();
        $this->assertNotNull($guest);
        $response->assertRedirect(route('admin.customers.guests.show', $guest));
        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_guest_360_has_no_start_conversation_cta(): void
    {
        $guest = SupportGuest::create([
            'phone' => '255715000666',
            'first_name' => 'Only',
            'last_name' => 'Identity',
            'source' => SupportGuestService::SOURCE_OTHER,
            'first_contact_at' => now(),
            'last_contact_at' => now(),
            'contact_count' => 1,
            'registration_status' => 'guest',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.guests.show', $guest))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Record phone call', $html);
        $this->assertStringContainsString('Create ticket', $html);
        $this->assertStringNotContainsString('Start conversation', $html);
        $this->assertStringNotContainsString('Anza mazungumzo', $html);
    }

    public function test_guarantor_invite_actions_hidden_after_accept_and_change_started_flash_removed(): void
    {
        $progress = file_get_contents(resource_path('views/site/borrower/loan-profile/_guarantor_progress.blade.php'));
        $this->assertIsString($progress);
        $this->assertStringContainsString("\$uiState === 'pending'", $progress);
        $this->assertStringNotContainsString("accepted_incomplete'], true)", $progress);
        $this->assertStringNotContainsString('borrower_change_hint', $progress);

        $controller = file_get_contents(app_path('Http/Controllers/Site/BorrowerController.php'));
        $this->assertIsString($controller);
        $this->assertStringNotContainsString('borrower_change_started', $controller);
        $this->assertStringContainsString('hasRecordedConsent', $controller);

        $chat = file_get_contents(resource_path('views/components/site/ai-support-chat.blade.php'));
        $this->assertIsString($chat);
        $this->assertStringContainsString('guestFormReady', $chat);
        $this->assertStringContainsString(':required="false"', $chat);

        $layout = file_get_contents(resource_path('views/components/site/layout.blade.php'));
        $this->assertIsString($layout);
        $this->assertStringContainsString('help-fab', $layout);
        $this->assertStringNotContainsString('chatbot-widget', $layout);
    }
}
