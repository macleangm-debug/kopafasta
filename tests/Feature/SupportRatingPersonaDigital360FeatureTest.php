<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\CustomerSupportWorkspaceService;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Rating completion + Digital persona management + Digital 360 metric/table cleanup.
 */
class SupportRatingPersonaDigital360FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_rating_json_returns_account_nav_without_redirect(): void
    {
        $html = File::get(resource_path('views/components/site/ai-support-chat.blade.php'));
        $this->assertStringContainsString('Rudi kwenye akaunti', $html);
        $this->assertStringContainsString('accountNav', $html);
        $this->assertStringContainsString('account_nav', File::get(app_path('Http/Controllers/Site/BorrowerController.php')));
        $this->assertStringNotContainsString(
            "'redirect' => route('site.borrower.support')",
            File::get(app_path('Http/Controllers/Site/BorrowerController.php'))
        );
    }

    public function test_rating_attaches_to_conversation_once_with_comment_in_meta(): void
    {
        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-RATE01',
            'channel' => 'web_chat',
            'status' => 'resolved',
            'resolved_at' => now(),
            'rating_requested_at' => now(),
            'automation_meta' => ['persona_key' => 'baraka', 'persona_name' => 'Baraka'],
        ]);

        $svc = app(SupportConversationService::class);
        $first = $svc->recordConversationRating($cnv, 5, 'Huduma nzuri');
        $this->assertSame(5, (int) $first->rating);
        $this->assertSame('Huduma nzuri', $first->automation_meta['rating_comment'] ?? null);

        $second = $svc->recordConversationRating($first, 1, 'overwrite');
        $this->assertSame(5, (int) $second->rating);
        $this->assertSame('Huduma nzuri', $second->automation_meta['rating_comment'] ?? null);
    }

    public function test_inactive_persona_stays_out_of_rotation_but_in_directory(): void
    {
        Setting::set(SupportAutomationService::PERSONAS_SETTING_KEY, [
            ['key' => 'amani', 'name' => 'Amani', 'active' => true],
            ['key' => 'neema', 'name' => 'Neema', 'active' => false],
        ]);
        Setting::set(SupportAutomationService::PERSONAS_MAX_SETTING_KEY, 20);

        $active = collect(app(SupportAutomationService::class)->personas())->pluck('key')->all();
        $all = collect(app(SupportAutomationService::class)->allPersonas())->pluck('key')->all();
        $this->assertSame(['amani'], $active);
        $this->assertSame(['amani', 'neema'], $all);
    }

    public function test_digital_resolution_excludes_overnight_idle_and_reports_median(): void
    {
        $start = now()->subHours(10);
        $cnv = SupportConversation::query()->create([
            'conversation_number' => 'KPF-CNV-IDLE01',
            'channel' => 'web_chat',
            'status' => 'resolved',
            'resolution_kind' => 'msaidizi',
            'handling_state' => SupportAutomationService::STATE_RESOLVED_AUTOMATED,
            'created_at' => $start,
            'resolved_at' => $start->copy()->addHours(9)->addMinutes(47),
            'automation_meta' => ['persona_key' => 'baraka', 'persona_name' => 'Baraka'],
        ]);
        SupportMessage::query()->create([
            'support_conversation_id' => $cnv->id,
            'sender_type' => 'bot',
            'body' => 'Habari',
            'is_automated' => true,
            'created_at' => $start,
            'updated_at' => $start,
        ]);
        SupportMessage::query()->create([
            'support_conversation_id' => $cnv->id,
            'sender_type' => 'customer',
            'body' => 'Ndiyo',
            'is_automated' => false,
            'created_at' => $start->copy()->addSeconds(20),
            'updated_at' => $start->copy()->addSeconds(20),
        ]);
        // Long overnight gap then resolve — must not inflate active digital seconds.
        SupportMessage::query()->create([
            'support_conversation_id' => $cnv->id,
            'sender_type' => 'bot',
            'body' => 'Asante',
            'is_automated' => true,
            'created_at' => $start->copy()->addHours(9)->addMinutes(46),
            'updated_at' => $start->copy()->addHours(9)->addMinutes(46),
        ]);

        $svc = app(CustomerSupportWorkspaceService::class);
        $seconds = $svc->digitalActiveResolutionSeconds($cnv->fresh(['messages']));
        $this->assertNotNull($seconds);
        $this->assertLessThan(120, $seconds, 'Overnight idle must be excluded from digital resolution time');
        $this->assertGreaterThan(0, $seconds);
    }

    public function test_assistants_blade_has_performance_table_and_median(): void
    {
        $html = File::get(resource_path('views/admin/support-workspace/assistants.blade.php'));
        $this->assertStringContainsString('Median resolution', $html);
        $this->assertStringContainsString('Handover rate', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Reference', $html);
        $this->assertStringContainsString('Outcome', $html);
        $this->assertStringContainsString('N/A', $html);
    }

    public function test_support_home_no_longer_duplicates_human_digital_selectors(): void
    {
        $home = File::get(resource_path('views/admin/support-workspace/home.blade.php'));
        $this->assertStringNotContainsString('Digital Assistants ▾', $home);
        $this->assertStringContainsString('top Support bar', $home);
        $header = File::get(resource_path('views/admin/partials/_support-header-controls.blade.php'));
        $this->assertStringContainsString('allPersonas', $header);
    }

    public function test_settings_blade_supports_add_digital_assistant(): void
    {
        $html = File::get(resource_path('views/admin/settings/support.blade.php'));
        $this->assertStringContainsString('Add Digital Assistant', $html);
        $this->assertStringContainsString('persona_active', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_availability_key_is_human_staff_preferences_only(): void
    {
        $src = File::get(app_path('Services/Support/CustomerSupportWorkspaceService.php'));
        $this->assertStringContainsString("public const AVAILABILITY_KEY = 'support_availability'", $src);
        $this->assertStringContainsString('data_get($agent->preferences, self::AVAILABILITY_KEY', $src);
    }
}
