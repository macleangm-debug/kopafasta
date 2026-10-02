<?php

namespace Tests\Feature;

use App\Services\MemberMessagesService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FinalUxClosureStagingFeatureTest extends TestCase
{
    public function test_resolved_support_surfaces_are_read_only(): void
    {
        $chat = File::get(resource_path('views/components/site/ai-support-chat.blade.php'));
        $this->assertStringContainsString('!composerLocked', $chat);
        $this->assertStringContainsString("config.isSw ? 'Imekamilishwa' : 'Resolved'", $chat);

        $conversation = File::get(resource_path('views/admin/support-workspace/conversation.blade.php'));
        $this->assertStringContainsString("in_array((string) \$conversation->status, ['resolved', 'closed'], true)", $conversation);
        $this->assertStringContainsString('Read-only history', $conversation);

        $inbox = File::get(resource_path('views/admin/support-workspace/inbox.blade.php'));
        $this->assertStringContainsString('$isResolvedHistory', $inbox);
        $this->assertStringContainsString('No composer, templates, or new messages', $inbox);

        $controller = File::get(app_path('Http/Controllers/Admin/SupportWorkspaceController.php'));
        $this->assertSame(5, substr_count($controller, "in_array((string) \$supportConversation->status, ['resolved', 'closed'], true)"));
    }

    public function test_registration_country_uses_separate_desktop_and_mobile_state(): void
    {
        $html = File::get(resource_path('views/site/auth/register-borrower.blade.php'));
        $this->assertStringContainsString('countryOpen: false, countrySheet: false', $html);
        $this->assertStringContainsString('@click="countrySheet = true"', $html);
        $this->assertStringContainsString('@click="countryOpen = !countryOpen"', $html);
        $this->assertStringContainsString('open="countrySheet"', $html);
        $this->assertStringNotContainsString('@click="countryOpen = true"', $html);
    }

    public function test_quick_tools_replace_collateral_with_messages(): void
    {
        $html = File::get(resource_path('views/components/site/borrower-dashboard-quick-actions.blade.php'));
        $this->assertStringContainsString("'messages'", $html);
        $this->assertStringContainsString('Ujumbe', $html);
        $this->assertStringContainsString('Taarifa', $html);
        $this->assertStringContainsString('site.borrower.messages', $html);
        $this->assertStringContainsString('kf-action-tile__icon', $html);
        $this->assertStringNotContainsString('Collateral', $html);
        $this->assertStringNotContainsString('Dhamana', $html);
        $this->assertStringContainsString('kf-action-tile', $html);
    }

    public function test_messages_foundation_reuses_notification_log_bucket(): void
    {
        $this->assertTrue(class_exists(MemberMessagesService::class));
        $this->assertSame('message', MemberMessagesService::BUCKET);
        $this->assertTrue(File::exists(resource_path('views/site/borrower/messages.blade.php')));
        $routes = File::get(base_path('routes/web.php'));
        $this->assertStringContainsString("name('borrower.messages')", $routes);
        $messages = File::get(resource_path('views/site/borrower/messages.blade.php'));
        $this->assertStringNotContainsString('Arifa ni tofauti', $messages);
    }

    public function test_theme_contrast_tokens_cover_action_tiles_and_loan_cards(): void
    {
        $css = File::get(resource_path('css/app.css'));
        $this->assertStringContainsString('--kf-card-border', $css);
        $this->assertStringContainsString('.kf-action-tile', $css);
        $this->assertStringContainsString('.loan-product-card', $css);

        $card = File::get(resource_path('views/components/site/loan-product-card.blade.php'));
        $this->assertStringContainsString('loan-product-card kf-surface-card', $card);
    }
}
