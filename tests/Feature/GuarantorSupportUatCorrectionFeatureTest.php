<?php

namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorSupportUatCorrectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_support_talk_to_team_opens_guest_chat_without_login(): void
    {
        $index = $this->get(route('site.support'))->assertOk();
        $indexHtml = $index->getContent();
        $this->assertTrue(str_contains($indexHtml, '/support/chat'), 'public support missing guest chat link');
        $this->assertTrue(str_contains($indexHtml, 'open-feedback'), 'public support missing open-feedback dispatch');

        $chat = $this->get(route('site.support.chat'))->assertOk();
        $chatHtml = $chat->getContent();
        $this->assertTrue(str_contains($chatHtml, 'speakUrl'), 'guest chat missing speakUrl config');
        $this->assertTrue(str_contains($chatHtml, 'needsGuestIdentity'), 'guest chat missing identity gate config');
        $this->assertTrue(
            str_contains($chatHtml, 'support') && str_contains($chatHtml, 'chat') && str_contains($chatHtml, 'speak'),
            'guest chat speak endpoint not wired'
        );
    }

    public function test_public_guest_speak_enters_same_support_queue_as_guest(): void
    {
        $response = $this->postJson(route('site.support.chat.speak'), [
            'body' => 'Habari, nahitaji msaada kuhusu usajili.',
            'guest_name' => 'UAT Guest',
            'guest_phone' => '0715222132',
        ]);

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('guest', true);

        $conversation = SupportConversation::query()->latest('id')->first();
        $this->assertNotNull($conversation);
        $this->assertNull($conversation->customer_id);
        $this->assertNull($conversation->user_id);
        $this->assertSame('UAT Guest', $conversation->guest_name);
        $this->assertNotEmpty($conversation->guest_phone);
        $this->assertTrue((bool) $conversation->needs_human);
        $this->assertSame(SupportConversationService::STATUS_WAITING, $conversation->status);
        $this->assertTrue(
            $conversation->messages()->where('sender_type', 'guest')->exists()
        );
    }

    public function test_guarantor_request_preview_uses_focused_wizard_width(): void
    {
        // Width contract is in the Blade shell — assert the focused container marker is present
        // on the public guest chat (same max-w-3xl principle) and the request-show template source.
        $path = resource_path('views/site/borrower/guarantor-request-show.blade.php');
        $blade = file_get_contents($path);
        $this->assertIsString($blade);
        $this->assertStringContainsString('max-w-3xl mx-auto w-full min-w-0', $blade);
        $this->assertStringNotContainsString('content-width="wide"', $blade);
    }

    public function test_change_guarantor_footer_always_exposes_continue_in_supplement_mode(): void
    {
        $footer = file_get_contents(resource_path('views/components/site/wizard-footer.blade.php'));
        $this->assertIsString($footer);
        $this->assertStringContainsString('supplementMode && stepKey === \'guarantor\'', $footer);
        $this->assertStringContainsString("borrower.apply.continue", $footer);

        $js = file_get_contents(resource_path('js/apply-wizard.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString('} else if (this.supplementMode) {', $js);
        $this->assertStringContainsString('this.addGuarantorOpen = true;', $js);
    }

    public function test_borrower_support_chat_centered_at_focused_width(): void
    {
        $blade = file_get_contents(resource_path('views/site/borrower/support.blade.php'));
        $this->assertIsString($blade);
        $this->assertStringContainsString('max-w-3xl mx-auto w-full', $blade);

        $partner = file_get_contents(resource_path('views/site/vendor/support.blade.php'));
        $this->assertIsString($partner);
        $this->assertStringContainsString('max-w-3xl mx-auto w-full', $partner);
    }

    public function test_feedback_panel_desktop_centered_modal_classes(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/feedback-form-panel.blade.php'));
        $this->assertIsString($blade);
        $this->assertStringContainsString('lg:left-1/2 lg:top-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2', $blade);
        $this->assertStringContainsString('lg:rounded-3xl', $blade);
        $this->assertStringContainsString('lg:hidden', $blade);
    }
}
