<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProfileUxFinalizationTest extends TestCase
{
    public function test_canonical_shell_toast_is_only_save_indicator(): void
    {
        $autosave = file_get_contents(resource_path('js/kf-autosave.js'));
        $this->assertStringContainsString('kfShowInlineSaving', $autosave);
        $this->assertStringContainsString('kfFlashInlineSaved', $autosave);
        $this->assertStringContainsString('kfShowSaveError', $autosave);
        $this->assertStringContainsString('isInstantControl', $autosave);
        $this->assertStringContainsString('hasPersistablePayload', $autosave);
        $this->assertStringNotContainsString('data-kf-autosave-status', $autosave);
        $this->assertStringNotContainsString('✓ ${labels.saved}', $autosave);

        $overlay = file_get_contents(resource_path('js/saving-overlay.js'));
        $this->assertStringContainsString('1800', $overlay);
        $this->assertStringContainsString('kfShowSaveError', $overlay);

        foreach ([
            resource_path('views/site/borrower/profile/activity.blade.php'),
            resource_path('views/site/borrower/profile/personal.blade.php'),
            resource_path('views/site/borrower/profile/residence.blade.php'),
            resource_path('views/site/partner-account/personal.blade.php'),
        ] as $path) {
            $this->assertStringNotContainsString('data-kf-autosave-status', file_get_contents($path));
        }
    }

    public function test_profile_card_is_collapsed_view_then_edit(): void
    {
        $js = file_get_contents(resource_path('js/profile-section-card.js'));
        $this->assertStringContainsString('showHeaderEdit', $js);
        $this->assertStringContainsString('Collapsed + complete only', $js);

        $blade = file_get_contents(resource_path('views/components/site/profile-section-card.blade.php'));
        $this->assertStringContainsString('showHeaderEdit', $blade);
        $this->assertStringContainsString("hub.view') }} →", $blade);
        $this->assertStringNotContainsString('hub.view_edit', $blade);
    }

    public function test_activity_view_shows_all_persisted_segment_fields(): void
    {
        $activity = file_get_contents(resource_path('views/site/borrower/profile/activity.blade.php'));
        $this->assertStringContainsString('activity_fields_localized()', $activity);
        $this->assertStringContainsString('activityViewRows', $activity);
        $this->assertStringContainsString('font-medium text-gray-900', $activity);
        $this->assertStringNotContainsString('data-kf-autosave-status', $activity);
    }
}
