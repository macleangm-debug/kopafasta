<?php

namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportHelpLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAutomationFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{guest_first_name: string, guest_last_name: string, guest_name: string, guest_phone: string}
     */
    private function guestIdentity(): array
    {
        return [
            'guest_first_name' => 'Asha',
            'guest_last_name' => 'Juma',
            'guest_name' => 'Asha Juma',
            'guest_phone' => '+255712345678',
        ];
    }

    public function test_public_start_requires_guest_identity(): void
    {
        $this->postJson(route('site.support.chat.automation'), [
            'action' => 'start',
        ])->assertStatus(422)->assertJsonPath('needs_guest', true);
    }

    public function test_public_automation_category_issue_resolve_automated(): void
    {
        $start = $this->withSession([])->postJson(route('site.support.chat.automation'), array_merge([
            'action' => 'start',
        ], $this->guestIdentity()));
        $start->assertOk()->assertJsonPath('ok', true);
        $start->assertJsonPath('handling_state', SupportAutomationService::STATE_WAITING_CUSTOMER);
        $this->assertNotEmpty($start->json('choices'));
        $this->assertNotEmpty($start->json('persona_name'));
        $this->assertStringContainsString('Msaidizi wa Kopafasta', (string) $start->json('persona_display')
            ?: (string) $start->json('handling_label'));
        $conversationId = (int) $start->json('conversation_id');
        $this->assertGreaterThan(0, $conversationId);

        $persona = (string) $start->json('persona_name');
        $greeting = collect($start->json('messages') ?? [])->firstWhere('role', 'bot')['text'] ?? '';
        $this->assertStringContainsString('Asha', (string) $greeting);
        $this->assertStringContainsString($persona, (string) $greeting);
        $this->assertStringNotContainsString('Shikamoo', (string) $greeting);
        $this->assertTrue(
            str_contains((string) $greeting, 'Habari') || str_contains((string) $greeting, 'Mambo'),
            'SW greeting must use Habari or Mambo'
        );
        $this->assertStringNotContainsString('SMS', (string) $greeting);
        $this->assertStringNotContainsString('bot', strtolower((string) $greeting));
        $this->assertStringNotContainsString('automated', strtolower((string) $greeting));

        $categoryKey = collect($start->json('choices'))->firstWhere('action', 'category')['key'] ?? null;
        $this->assertNotEmpty($categoryKey);

        $cat = $this->postJson(route('site.support.chat.automation'), array_merge([
            'action' => 'category',
            'key' => $categoryKey,
            'conversation_id' => $conversationId,
        ], $this->guestIdentity()));
        $cat->assertOk();
        $this->assertSame('issue', $cat->json('phase'));
        $this->assertSame($persona, $cat->json('persona_name'));
        $issueSlug = collect($cat->json('choices'))->firstWhere('action', 'issue')['key'] ?? null;
        $this->assertNotEmpty($issueSlug);

        // Prefer a non-ticket issue for resolve path.
        $help = app(SupportHelpLibraryService::class);
        $safeSlug = collect($help->category($categoryKey, 'member')['articles'] ?? [])
            ->first(fn ($a) => empty($a['creates_ticket']))['slug'] ?? $issueSlug;

        $issue = $this->postJson(route('site.support.chat.automation'), array_merge([
            'action' => 'issue',
            'slug' => $safeSlug,
            'key' => $safeSlug,
            'conversation_id' => $conversationId,
        ], $this->guestIdentity()));
        $issue->assertOk();
        $this->assertSame('confirm', $issue->json('phase'));
        $this->assertSame($persona, $issue->json('persona_name'));

        $yes = $this->postJson(route('site.support.chat.automation'), array_merge([
            'action' => 'resolved_yes',
            'conversation_id' => $conversationId,
        ], $this->guestIdentity()));
        $yes->assertOk();
        $yes->assertJsonPath('handling_state', SupportAutomationService::STATE_RESOLVED_AUTOMATED);
        $closing = collect($yes->json('messages') ?? [])->last()['text'] ?? '';
        $this->assertStringNotContainsString('Automated', (string) $closing);
        $this->assertStringNotContainsString('Otomatiki', (string) $closing);

        $conversation = SupportConversation::query()->findOrFail($conversationId);
        $this->assertSame(SupportAutomationService::STATE_RESOLVED_AUTOMATED, $conversation->handling_state);
        $this->assertSame('closed', $conversation->status);
        $this->assertFalse((bool) $conversation->needs_human);
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        $this->assertSame($persona, $meta['persona_name'] ?? null);
        $this->assertSame('Resolved by Msaidizi', $conversation->resolution_note);
    }

    public function test_no_other_issue_resolves_immediately(): void
    {
        $start = $this->postJson(route('site.support.chat.automation'), array_merge([
            'action' => 'start',
        ], $this->guestIdentity()));
        $start->assertOk();
        $conversationId = (int) $start->json('conversation_id');

        $done = $this->postJson(route('site.support.chat.automation'), array_merge([
            'action' => 'no_other_issue',
            'conversation_id' => $conversationId,
        ], $this->guestIdentity()));
        $done->assertOk();
        $done->assertJsonPath('handling_state', SupportAutomationService::STATE_RESOLVED_AUTOMATED);
        $closing = collect($done->json('messages') ?? [])->last()['text'] ?? '';
        $this->assertStringContainsString('Asha', (string) $closing);
        $this->assertStringNotContainsString('Shikamoo', (string) $closing);
        $this->assertStringNotContainsString('automated', strtolower((string) $closing));

        $conversation = SupportConversation::query()->findOrFail($conversationId);
        $this->assertSame('closed', $conversation->status);
        $this->assertSame('Resolved by Msaidizi', $conversation->resolution_note);
    }

    public function test_registration_help_has_no_otp_or_sms_code_fiction(): void
    {
        $help = app(SupportHelpLibraryService::class);
        $article = $help->article('getting-started', 'open-account', 'member');
        $this->assertNotNull($article);

        $blob = strtolower(implode(' ', array_filter([
            $article['a_en'] ?? '',
            $article['a_sw'] ?? '',
            implode(' ', $article['steps_en'] ?? []),
            implode(' ', $article['steps_sw'] ?? []),
        ])));

        $this->assertStringNotContainsString('confirm the sms', $blob);
        $this->assertStringNotContainsString('thibitisha msimbo', $blob);
        $this->assertStringNotContainsString('enter the sms', $blob);
        $this->assertStringNotContainsString('otp', $blob);
        $this->assertStringContainsString('pin', $blob);
        $this->assertStringContainsString('security', $blob);

        $forgot = $help->article('getting-started', 'forgot-pin', 'member');
        $forgotBlob = strtolower(implode(' ', array_filter([
            $forgot['a_en'] ?? '',
            implode(' ', $forgot['steps_en'] ?? []),
        ])));
        $this->assertStringNotContainsString('enter the sms', $forgotBlob);
        $this->assertStringContainsString('security question', $forgotBlob);
    }

    public function test_branding_kopafasta_in_assistant_strings(): void
    {
        $sw = __('site.support.assistant_title', [], 'sw');
        $en = __('site.support.assistant_title', [], 'en');
        $this->assertStringContainsString('Kopafasta', $sw);
        $this->assertStringContainsString('Kopafasta', $en);
        $this->assertStringNotContainsString('KopaFasta', $sw);
        $this->assertStringNotContainsString('KopaFasta', $en);
        $this->assertStringNotContainsString('Kopa Fasta', $sw);
        $this->assertStringNotContainsString('Kopa Fasta', $en);
    }

    public function test_help_library_still_serves_categories(): void
    {
        $cats = app(SupportHelpLibraryService::class)->categories('member');
        $this->assertNotEmpty($cats);
        $this->assertTrue(collect($cats)->contains(fn ($c) => ($c['key'] ?? '') === 'fees-payments'));
    }
}
