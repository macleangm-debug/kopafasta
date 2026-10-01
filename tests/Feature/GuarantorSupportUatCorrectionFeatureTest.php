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
        $this->assertTrue(str_contains($chatHtml, 'guestFirstName'), 'guest chat missing first name field');
        $this->assertTrue(str_contains($chatHtml, 'guestLastName'), 'guest chat missing last name field');
        $this->assertTrue(str_contains($chatHtml, 'name="guest_phone"'), 'guest chat missing phone component');
        $this->assertTrue(
            str_contains($chatHtml, 'support') && str_contains($chatHtml, 'chat') && str_contains($chatHtml, 'speak'),
            'guest chat speak endpoint not wired'
        );
    }

    public function test_public_guest_speak_enters_same_support_queue_as_guest(): void
    {
        $response = $this->postJson(route('site.support.chat.speak'), [
            'body' => 'Habari, nahitaji msaada kuhusu usajili.',
            'guest_first_name' => 'UAT',
            'guest_last_name' => 'Guest',
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
        $requestShow = file_get_contents(resource_path('views/site/borrower/guarantor-request-show.blade.php'));
        $this->assertIsString($requestShow);
        $this->assertStringContainsString('content-width="focused"', $requestShow);
        $this->assertStringNotContainsString('content-width="wide"', $requestShow);

        $guaranteed = file_get_contents(resource_path('views/site/borrower/guaranteed-show.blade.php'));
        $this->assertIsString($guaranteed);
        $this->assertStringContainsString('max-w-3xl mx-auto w-full min-w-0', $guaranteed);
        $this->assertStringNotContainsString('content-width="wide"', $guaranteed);
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

    public function test_footer_and_public_support_cta_routing_are_separated(): void
    {
        $layout = file_get_contents(resource_path('views/components/site/layout.blade.php'));
        $this->assertIsString($layout);
        $this->assertStringContainsString("route('site.support')", $layout);
        $this->assertStringContainsString("route('site.support', ['feedback' => 1])", $layout);
        $this->assertStringNotContainsString("route('site.feedback', ['open' => 1])", $layout);

        $landing = file_get_contents(resource_path('views/site/help/_landing-body.blade.php'));
        $this->assertIsString($landing);
        $this->assertStringContainsString('data-kf-support-action="chat"', $landing);
        $this->assertStringContainsString('data-kf-support-action="feedback"', $landing);
        $this->assertStringNotContainsString("route('site.feedback', ['open' => 1])", $landing);

        $wizard = file_get_contents(resource_path('js/apply-wizard.js'));
        $this->assertIsString($wizard);
        $this->assertStringContainsString('supplementShareReady', $wizard);
        $this->assertStringContainsString('// Finish (supplementShareReady) is the only submit', $wizard);
    }
}
