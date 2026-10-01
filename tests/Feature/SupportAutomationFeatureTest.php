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

    public function test_public_automation_category_issue_resolve_automated(): void
    {
        $start = $this->withSession([])->postJson(route('site.support.chat.automation'), [
            'action' => 'start',
        ]);
        $start->assertOk()->assertJsonPath('ok', true);
        $start->assertJsonPath('handling_state', SupportAutomationService::STATE_WAITING_CUSTOMER);
        $this->assertNotEmpty($start->json('choices'));
        $conversationId = (int) $start->json('conversation_id');
        $this->assertGreaterThan(0, $conversationId);

        $categoryKey = collect($start->json('choices'))->firstWhere('action', 'category')['key'] ?? null;
        $this->assertNotEmpty($categoryKey);

        $cat = $this->postJson(route('site.support.chat.automation'), [
            'action' => 'category',
            'key' => $categoryKey,
            'conversation_id' => $conversationId,
        ]);
        $cat->assertOk();
        $this->assertSame('issue', $cat->json('phase'));
        $issueSlug = collect($cat->json('choices'))->firstWhere('action', 'issue')['key'] ?? null;
        $this->assertNotEmpty($issueSlug);

        // Prefer a non-ticket issue for resolve path.
        $help = app(SupportHelpLibraryService::class);
        $safeSlug = collect($help->category($categoryKey, 'member')['articles'] ?? [])
            ->first(fn ($a) => empty($a['creates_ticket']))['slug'] ?? $issueSlug;

        $issue = $this->postJson(route('site.support.chat.automation'), [
            'action' => 'issue',
            'slug' => $safeSlug,
            'key' => $safeSlug,
            'conversation_id' => $conversationId,
        ]);
        $issue->assertOk();
        $this->assertSame('confirm', $issue->json('phase'));

        $yes = $this->postJson(route('site.support.chat.automation'), [
            'action' => 'resolved_yes',
            'conversation_id' => $conversationId,
        ]);
        $yes->assertOk();
        $yes->assertJsonPath('handling_state', SupportAutomationService::STATE_RESOLVED_AUTOMATED);

        $conversation = SupportConversation::query()->findOrFail($conversationId);
        $this->assertSame(SupportAutomationService::STATE_RESOLVED_AUTOMATED, $conversation->handling_state);
        $this->assertSame('closed', $conversation->status);
        $this->assertFalse((bool) $conversation->needs_human);
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
