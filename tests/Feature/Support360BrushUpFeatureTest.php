<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Support360BrushUpFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_digital_assistants_configured_and_round_robin(): void
    {
        $automation = app(SupportAutomationService::class);
        $personas = $automation->personas();
        $this->assertCount(5, $personas);
        $names = collect($personas)->pluck('name')->all();
        $this->assertSame(['Amani', 'Neema', 'Baraka', 'Rehema', 'Daniel'], $names);

        Setting::set(SupportAutomationService::PERSONAS_SETTING_KEY, $personas);
        Setting::set('support.msaidizi.persona_rr', 0);

        $keys = [];
        for ($i = 0; $i < 5; $i++) {
            $meta = [];
            $ref = new \ReflectionClass($automation);
            $method = $ref->getMethod('ensurePersona');
            $method->setAccessible(true);
            $persona = $method->invokeArgs($automation, [&$meta]);
            $keys[] = $persona['key'];
        }

        $this->assertSame(
            collect($personas)->pluck('key')->all(),
            $keys,
            'New conversations should rotate fairly across the five assistants'
        );
    }

    public function test_automated_resolve_requests_csat_for_member(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715333444']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-BRUSH-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'MacLean',
            'last_name' => 'Brush',
            'phone' => '255715333444',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $start = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'start',
        ]);
        $start->assertOk();
        $conversationId = (int) $start->json('conversation_id');

        $conversation = SupportConversation::query()->findOrFail($conversationId);
        $this->assertNotEmpty($conversation->publicNumber());
        $this->assertStringStartsWith('KPF-CNV-', $conversation->publicNumber());
        $this->assertStringNotContainsString('#', $conversation->publicNumber());

        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        $meta['phase'] = 'confirm';
        $meta['category_key'] = 'account';
        $meta['issue_slug'] = 'cannot-login';
        $conversation->update(['automation_meta' => $meta, 'customer_id' => $customer->id]);

        $yes = $this->actingAs($user)->postJson(route('site.borrower.support.automation'), [
            'action' => 'resolved_yes',
            'conversation_id' => $conversationId,
        ]);
        $yes->assertOk();
        $yes->assertJsonPath('show_rating', true);
        $this->assertNotEmpty($yes->json('rating_prompt'));
        $persona = (string) ($yes->json('persona_name') ?? '');
        $this->assertNotSame('', $persona);
        $this->assertStringContainsString($persona, (string) $yes->json('rating_prompt'));

        $conversation->refresh();
        $this->assertNotNull($conversation->rating_requested_at);
        $this->assertTrue($conversation->awaitsRating());
    }

    public function test_waiting_followup_locks_after_one_customer_message(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'phone' => '255715555666']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-BRUSH-2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Neema',
            'last_name' => 'Wait',
            'phone' => '255715555666',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $service = app(SupportConversationService::class);

        $conversation = SupportConversation::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => SupportConversationService::STATUS_WAITING,
            'needs_human' => true,
            'handling_state' => SupportAutomationService::STATE_ESCALATED,
            'conversation_number' => $service->nextConversationNumber(),
            'channel' => 'web_chat',
            'topic' => 'Support',
            'automation_meta' => [
                'waiting_followup_allowed' => true,
                'waiting_followup_used' => false,
                'persona_key' => 'amani',
                'persona_name' => 'Amani',
            ],
            'last_message_at' => now(),
            'waiting_since' => now(),
        ]);

        $first = $service->requestHuman($customer, $user, 'Here are more details about my issue.');
        $this->assertSame($conversation->id, $first->id);
        $meta = is_array($first->automation_meta) ? $first->automation_meta : [];
        $this->assertTrue((bool) ($meta['waiting_followup_used'] ?? false));
        $this->assertTrue((bool) ($service->memberChatPresence($first)['composer_locked'] ?? false));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('composer_locked');
        $service->requestHuman($customer, $user, 'Another spam message');
    }

    public function test_customer_facing_topic_never_exposes_slug(): void
    {
        app()->setLocale('sw');
        $label = app(SupportAutomationService::class)->customerFacingTopicLabel('cannot-login', 'member', 'sw');
        $this->assertNotSame('cannot-login', $label);
        $this->assertStringNotContainsString('_', $label);

        $status = app(SupportConversationService::class)->customerFacingStatusLabel('open', 'sw');
        $this->assertSame('Imefunguliwa', $status);
        $this->assertNotSame('Open', $status);
    }

    public function test_public_number_never_uses_hash_id(): void
    {
        $cnv = SupportConversation::query()->create([
            'status' => 'waiting',
            'channel' => 'web_chat',
            'conversation_number' => null,
            'last_message_at' => now(),
        ]);
        $this->assertStringStartsWith('KPF-CNV-', $cnv->publicNumber());
        $this->assertStringNotContainsString('#'.$cnv->id, $cnv->publicNumber());
        $this->assertDoesNotMatchRegularExpression('/KPF-CNV-0+'.$cnv->id.'$/', $cnv->publicNumber());
    }
}
