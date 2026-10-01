<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SupportGuestChatFormReadyTest extends TestCase
{
    public function test_ai_support_chat_wires_guest_form_ready_sync(): void
    {
        $html = File::get(resource_path('views/components/site/ai-support-chat.blade.php'));

        $this->assertStringContainsString('guestFormReady', $html);
        $this->assertStringContainsString('syncGuestPhone', $html);
        $this->assertStringContainsString('guestPhoneDigits', $html);
        $this->assertStringContainsString('Anza mazungumzo', $html);
        $this->assertStringContainsString('@input.capture="syncGuestPhone()"', $html);
        $this->assertStringContainsString('x-show="!needsGuestGate"', $html);
        $this->assertStringContainsString('responseDelayMinMs', $html);
        $this->assertStringContainsString('paceDelay', $html);
        $this->assertStringContainsString('personaDisplay', $html);
        $this->assertStringContainsString('Kabla ya kuanza mazungumzo', $html);
    }

    public function test_feedback_form_panel_is_single_responsive_surface(): void
    {
        $html = File::get(resource_path('views/components/site/feedback-form-panel.blade.php'));

        $this->assertStringContainsString('lg:inset-auto lg:left-1/2', $html);
        $this->assertStringContainsString('lg:hidden', $html);
        $this->assertStringNotContainsString('x-site.bottom-sheet', $html);
        $this->assertStringContainsString('@open-feedback.window', $html);
    }

    public function test_phone_input_dispatches_parent_sync_events(): void
    {
        $html = File::get(resource_path('views/components/site/phone-input.blade.php'));
        $this->assertStringContainsString("dispatchEvent(new Event('input'", $html);
        $this->assertStringContainsString("dispatchEvent(new Event('change'", $html);
    }

    public function test_support_inbox_filter_labels_translate_sw(): void
    {
        app()->setLocale('sw');
        $this->assertSame('Inasubiri', __('admin.support.inbox.filter_waiting'));
        $this->assertSame('Hai', __('admin.support.inbox.filter_active'));
        $this->assertSame('+ Usaidizi mpya', __('admin.support.new_support'));
        $this->assertStringContainsString('Kopafasta', __('admin.support.tips_body'));
        $this->assertStringNotContainsString('KopaFasta', __('admin.support.tips_body'));
    }
}
