<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\SupportAccountDiagnosticService;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportSafeDiagnosticsClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_message_never_stringifies_eloquent_model_or_json_dump(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'phone' => '255715111000',
            'name' => 'Leak Test',
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SAFE-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leak',
            'last_name' => 'Test',
            'phone' => '255715111000',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $conversation = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-SAFE01',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
            'needs_human' => false,
        ]);

        $svc = app(SupportConversationService::class);

        // Eloquent User/Customer cast to string as JSON — must never enter a bubble.
        $blockedUser = $svc->appendMessage($conversation, 'bot', $user, null, true, false);
        $this->assertStringNotContainsString('"email_verified_at"', (string) $blockedUser->body);
        $this->assertStringNotContainsString('"preferences"', (string) $blockedUser->body);
        $this->assertStringNotContainsString('"id":', (string) $blockedUser->body);

        $blockedCustomer = $svc->appendMessage($conversation, 'bot', $customer, null, true, false);
        $this->assertStringNotContainsString('"created_at"', (string) $blockedCustomer->body);

        $rawJson = json_encode($user->toArray(), JSON_PRETTY_PRINT);
        $blockedJson = $svc->appendMessage($conversation, 'bot', $rawJson, null, true, false);
        $this->assertFalse($svc->looksLikeSerializedDump((string) $blockedJson->body));
        $this->assertStringNotContainsString('email_verified_at', (string) $blockedJson->body);
    }

    public function test_escalate_does_not_embed_user_json_as_first_name(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'phone' => '255715111001',
            'name' => 'Safe Member',
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SAFE-2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Safe',
            'last_name' => 'Member',
            'phone' => '255715111001',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'start',
        ])->assertOk();

        $conversation = SupportConversation::query()->where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($conversation);

        $escalate = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'escalate',
            'conversation_id' => $conversation->id,
            'key' => 'human',
        ])->assertOk();

        $conversation->refresh();
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        $this->assertSame('Safe', $meta['customer_first_name'] ?? null);
        $this->assertFalse(
            app(SupportConversationService::class)->looksLikeSerializedDump((string) ($meta['customer_first_name'] ?? ''))
        );

        foreach ($conversation->messages as $message) {
            $this->assertFalse(
                app(SupportConversationService::class)->looksLikeSerializedDump((string) $message->body),
                'Message leaked serialized payload: '.$message->body
            );
        }

        $this->assertSame('human', $escalate->json('mode'));
        $this->assertTrue((bool) $escalate->json('needs_human'));
    }

    public function test_active_human_conversation_stays_human_on_reload_presence(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'name' => 'Neema Staff']);
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715111002']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SAFE-3',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Cont',
            'last_name' => 'Inuity',
            'phone' => '255715111002',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $conversation = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-SAFE03',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
            'needs_human' => false,
            'assigned_to' => $agent->id,
            'accepted_at' => now(),
            'handling_state' => SupportAutomationService::STATE_HUMAN,
            'automation_meta' => ['persona_key' => 'amani', 'persona_name' => 'Amani'],
        ]);

        $presence = app(SupportConversationService::class)->memberChatPresence($conversation, 'sw');
        $this->assertSame('Neema', $presence['agent_first_name']);
        $this->assertStringContainsString('Kopafasta Support', (string) $presence['brand_title']);
        $this->assertFalse((bool) $presence['composer_locked']);
    }

    public function test_staff_diagnostic_draft_is_safe_prose_not_model_dump(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715111003']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SAFE-4',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Diag',
            'last_name' => 'Safe',
            'phone' => '255715111003',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $draft = app(SupportAccountDiagnosticService::class)->staffDiagnosticDraft(
            $customer,
            $user,
            'profile',
            'profile-progress',
            'sw',
        );

        $this->assertNotSame('', $draft);
        $this->assertFalse(app(SupportConversationService::class)->looksLikeSerializedDump($draft));
        $this->assertStringNotContainsString('"email_verified_at"', $draft);
        $this->assertStringNotContainsString('"preferences"', $draft);
    }
}
