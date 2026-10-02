<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\SupportAccountDiagnosticService;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportFinalClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_keeps_one_open_conversation_and_preserves_history(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715222100', 'name' => 'One Cnv']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-ONE-CNV',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'One',
            'last_name' => 'Cnv',
            'phone' => '255715222100',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $older = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-OLD001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_WAITING,
            'needs_human' => true,
            'last_message_at' => now()->subHour(),
        ]);
        $newer = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-NEW001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
            'needs_human' => false,
            'last_message_at' => now(),
        ]);

        $svc = app(SupportConversationService::class);
        $keep = $svc->reconcileOpenConversationsFor($customer, null);

        $this->assertNotNull($keep);
        $this->assertSame($newer->id, $keep->id);
        $older->refresh();
        $this->assertSame(SupportConversationService::STATUS_RESOLVED, $older->status);
        $this->assertSame('duplicate_reconcile', $older->resolution_category);
        $this->assertSame(1, SupportConversation::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->count());
    }

    public function test_unread_for_customer_counts_staff_and_bot_and_clears_on_read(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715222101', 'name' => 'Unread']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-UNREAD',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Un',
            'last_name' => 'Read',
            'phone' => '255715222101',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-UNR001',
            'customer_id' => $customer->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
        ]);
        SupportMessage::query()->create([
            'support_conversation_id' => $cnv->id,
            'sender_type' => 'bot',
            'body' => 'Habari',
            'is_automated' => true,
            'read_at' => null,
        ]);
        SupportMessage::query()->create([
            'support_conversation_id' => $cnv->id,
            'sender_type' => 'staff',
            'body' => 'Je, tatizo lako limetatuliwa?',
            'is_automated' => false,
            'read_at' => null,
        ]);

        $svc = app(SupportConversationService::class);
        $this->assertSame(2, $svc->unreadForCustomer($cnv));
        $svc->markReadForCustomer($cnv);
        $this->assertSame(0, $svc->unreadForCustomer($cnv->fresh()));
    }

    public function test_human_resolution_yes_resolves_and_requests_rating(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715222102', 'name' => 'Resolve Yes']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-RES-YES',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Resolve',
            'last_name' => 'Yes',
            'phone' => '255715222102',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-RES001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
            'needs_human' => false,
            'automation_meta' => [
                'awaiting_customer_resolution' => true,
                'persona_key' => 'amani',
                'persona_name' => 'Amani',
            ],
        ]);

        $res = $this->actingAs($user)->postJson(
            route('site.borrower.support.conversation.resolution', $cnv),
            ['resolved' => 'yes']
        )->assertOk()->json();

        $this->assertTrue($res['ok']);
        $this->assertTrue($res['resolved']);
        $this->assertTrue($res['show_rating'] ?? false);
        $cnv->refresh();
        $this->assertContains($cnv->status, ['resolved', 'closed']);
        $this->assertNotNull($cnv->rating_requested_at);
        $meta = is_array($cnv->automation_meta) ? $cnv->automation_meta : [];
        $this->assertArrayNotHasKey('awaiting_customer_resolution', $meta);
    }

    public function test_human_resolution_no_keeps_same_conversation_open(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715222103', 'name' => 'Resolve No']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-RES-NO',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Resolve',
            'last_name' => 'No',
            'phone' => '255715222103',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-RES002',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
            'automation_meta' => ['awaiting_customer_resolution' => true],
        ]);

        $this->actingAs($user)->postJson(
            route('site.borrower.support.conversation.resolution', $cnv),
            ['resolved' => 'no']
        )->assertOk()->assertJson(['ok' => true, 'resolved' => false]);

        $cnv->refresh();
        $this->assertNotContains($cnv->status, ['resolved', 'closed']);
        $this->assertNull($cnv->rating_requested_at);
    }

    public function test_digital_assistant_confirm_choices_are_yes_no_only(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715222104', 'name' => 'Confirm']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-CONFIRM',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Confirm',
            'last_name' => 'Member',
            'phone' => '255715222104',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-CNF001',
            'customer_id' => $customer->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_WAITING,
            'handling_state' => SupportAutomationService::STATE_WAITING_CUSTOMER,
            'automation_meta' => [
                'phase' => 'confirm',
                'audience' => 'member',
                'persona_key' => 'neema',
                'persona_name' => 'Neema',
            ],
        ]);

        $payload = app(SupportAutomationService::class)->payload($cnv, 'member', 'sw');
        $keys = collect($payload['choices'] ?? [])->pluck('key')->all();
        $this->assertSame(['yes', 'no'], $keys);
        $this->assertStringContainsString('tatizo lako limetatuliwa', app(SupportAutomationService::class)->resolvedPrompt('sw'));
    }

    public function test_wallet_diagnostic_member_returns_honest_no_data_not_generic_failure(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715222105', 'name' => 'Wallet']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-WALLET',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Wallet',
            'last_name' => 'Member',
            'phone' => '255715222105',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $draft = app(SupportAccountDiagnosticService::class)->staffDiagnosticDraft(
            $customer,
            $user,
            'affiliate',
            'wallet-balance',
            'sw',
            null,
        );

        $this->assertStringNotContainsString('Sijaweza kuandaa muhtasari', $draft);
        $this->assertStringContainsString('Mwanachama', $draft);
    }

    public function test_customer_facing_status_never_returns_raw_awaiting_guarantor(): void
    {
        $label = app(SupportConversationService::class)->customerFacingStatusLabel('awaiting_guarantor', 'sw');
        $this->assertSame('Inasubiri mdhamini', $label);
        $this->assertStringNotContainsString('awaiting_guarantor', $label);

        $en = app(SupportConversationService::class)->customerFacingStatusLabel('awaiting_guarantor / awaiting_guarantor', 'en');
        $this->assertStringNotContainsString('awaiting_guarantor', $en);
    }
}
