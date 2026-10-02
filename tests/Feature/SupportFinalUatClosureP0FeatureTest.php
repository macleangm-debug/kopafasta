<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Surgical Support final UAT closure guards — eight observed defects only.
 */
class SupportFinalUatClosureP0FeatureTest extends TestCase
{
    public function test_route_login_is_not_used_in_support_automation_join_cta(): void
    {
        $src = File::get(app_path('Services/Support/SupportAutomationService.php'));
        $this->assertStringNotContainsString("route('login')", $src);
        $this->assertStringContainsString("route('site.login')", $src);
        $this->assertStringContainsString("route('site.register.borrower')", $src);
    }

    public function test_safe_chat_text_blocks_route_not_defined_leaks(): void
    {
        $svc = app(\App\Services\Support\SupportConversationService::class);
        $out = $svc->safeChatText('Route [login] not defined.');
        $this->assertStringNotContainsString('Route [login]', $out);
        $this->assertNotSame('Route [login] not defined.', $out);
    }

    public function test_guest_chat_requires_exactly_nine_national_digits(): void
    {
        $html = File::get(resource_path('views/components/site/ai-support-chat.blade.php'));
        $this->assertStringContainsString('nationalPhoneDigits()', $html);
        $this->assertStringContainsString('national.length === 9', $html);
        $this->assertStringContainsString('Weka tarakimu 9 za nambari ya simu.', $html);
        $this->assertStringContainsString('safeCustomerError', $html);
    }

    public function test_digital_assistant_360_uses_single_hero_when_focused(): void
    {
        $html = File::get(resource_path('views/admin/support-workspace/assistants.blade.php'));
        $this->assertStringContainsString('@if (! $focus)', $html);
        $this->assertStringContainsString('One Digital Assistant 360 hero', $html);
        $this->assertStringNotContainsString('Digital Assistant 360 — Settings persona, not a Staff login.', $html);
    }

    public function test_human_digital_selectors_are_searchable_and_wide(): void
    {
        $html = File::get(resource_path('views/admin/partials/_support-header-controls.blade.php'));
        $this->assertStringContainsString('filteredHumans', $html);
        $this->assertStringContainsString('filteredDigital', $html);
        $this->assertStringContainsString('w-[20rem]', $html);
        $this->assertStringContainsString('Search humans', $html);
        $this->assertStringContainsString('Search assistants', $html);
    }

    public function test_mobile_support_chat_hides_help_fab(): void
    {
        $layout = File::get(resource_path('views/components/site/layout.blade.php'));
        $this->assertStringContainsString("routeIs('site.support.chat')", $layout);
        $this->assertStringContainsString('max-lg:hidden', $layout);
    }

    public function test_nginx_branded_50x_fallback_exists(): void
    {
        $this->assertFileExists(public_path('errors/50x.html'));
        $html = File::get(public_path('errors/50x.html'));
        $this->assertStringContainsString('Samahani, huduma haipatikani kwa muda', $html);
        $this->assertStringContainsString('Sorry, this service is temporarily unavailable', $html);
        $staging = File::get(base_path('deploy/nginx-staging.conf.example'));
        $this->assertStringContainsString('error_page 502 503 504 /errors/50x.html', $staging);
    }

    public function test_resolve_automated_does_not_emit_join_cta_before_rating(): void
    {
        $src = File::get(app_path('Services/Support/SupportAutomationService.php'));
        $start = strpos($src, 'private function resolveAutomated');
        $this->assertNotFalse($start);
        $chunk = substr($src, $start, 2500);
        $this->assertStringContainsString("\$payload['show_rating'] = true", $chunk);
        $this->assertStringNotContainsString("\$payload['join_cta']", $chunk);
    }
}