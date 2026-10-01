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
            ->assertDontSee('Old Guest');
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
