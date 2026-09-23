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
        $this->assertStringContainsString('queueMicrotask', $autosave);
        $this->assertStringContainsString('requestAnimationFrame', $autosave);
        $this->assertStringContainsString('isSystemFieldName', $autosave);
        $this->assertStringContainsString('syncSitePhoneInput', $autosave);
        $this->assertStringContainsString('profile-section-edit', $autosave);
        $this->assertStringNotContainsString('data-kf-autosave-status', $autosave);
        $this->assertStringNotContainsString('✓ ${labels.saved}', $autosave);

        $overlay = file_get_contents(resource_path('js/saving-overlay.js'));
        $this->assertStringContainsString('1800', $overlay);
        $this->assertStringContainsString('kfShowSaveError', $overlay);
        $this->assertStringContainsString('data-kf-canonical-saving', $overlay);
        $this->assertStringContainsString('data-kf-canonical-saved', $overlay);
        $this->assertStringNotContainsString("querySelector('[data-kf-saved-toast]')", $overlay);

        foreach ([
            resource_path('views/site/borrower/profile/activity.blade.php'),
            resource_path('views/site/borrower/profile/personal.blade.php'),
            resource_path('views/site/borrower/profile/residence.blade.php'),
            resource_path('views/site/partner-account/personal.blade.php'),
            resource_path('views/site/partner-account/activity.blade.php'),
            resource_path('views/site/partner-account/residence.blade.php'),
        ] as $path) {
            $this->assertStringNotContainsString('data-kf-autosave-status', file_get_contents($path));
        }
    }

    public function test_autosave_does_not_snap_edit_form_closed(): void
    {
        $js = file_get_contents(resource_path('js/profile-autosave-view.js'));
        $this->assertStringContainsString('Do NOT collapse cards on ordinary field saves', $js);
        $this->assertStringContainsString('Never snap Edit → View on autosave', $js);
        $this->assertStringNotContainsString('data.open = false', $js);
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

    public function test_family_and_kin_view_mirror_edit_user_facing_fields(): void
    {
        $personal = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $this->assertStringContainsString('familyViewRows', $personal);
        $this->assertStringContainsString('spouse_first_name', $personal);
        $this->assertStringContainsString('spouse_middle_name', $personal);
        $this->assertStringContainsString('spouse_last_name', $personal);
        $this->assertStringContainsString('fields.number_of_children', $personal);
        $this->assertStringNotContainsString('spouse_full_name', $personal);
        $this->assertStringContainsString('kinViewRows', $personal);
        $this->assertStringContainsString('fields.first_name', $personal);
        $this->assertStringContainsString('fields.middle_name', $personal);
        $this->assertStringContainsString('fields.last_name', $personal);
        $this->assertStringContainsString('name="marital_status"', $personal);
        $this->assertStringContainsString('contactEmail', $personal);
        $this->assertStringContainsString("fields.email') }}", $personal);
        $this->assertStringContainsString('x-site.profile-select', $personal);
        $this->assertStringContainsString('@profile-select', $personal);

        $select = file_get_contents(resource_path('views/components/site/profile-select.blade.php'));
        $this->assertStringContainsString('x-teleport="body"', $select);
        $this->assertStringContainsString("dispatch('profile-select'", $select);
        $this->assertStringContainsString('z-[10200]', $select);
        $this->assertStringContainsString('kfMirrorAutosaveFormToView', file_get_contents(resource_path('js/kf-autosave.js')));

        $kinFields = file_get_contents(resource_path('views/components/site/kin-fields.blade.php'));
        $this->assertStringContainsString('phone-input', $kinFields);
        $this->assertStringNotContainsString('kinPhone', $kinFields);

        $shell = file_get_contents(resource_path('views/site/borrower/profile/_profile_shell.blade.php'));
        $this->assertStringContainsString('data-kf-page-status', $shell);
        $this->assertStringNotContainsString('data-kf-saved-toast', $shell);

        $autosave = file_get_contents(resource_path('js/kf-autosave.js'));
        $this->assertStringContainsString('isSystemFieldName', $autosave);
        $this->assertStringContainsString('syncSitePhoneInput', $autosave);
        $this->assertStringContainsString('kfFlushAutosaveForm', $autosave);
        $this->assertStringContainsString('focusin', $autosave);
        $this->assertStringContainsString("addEventListener('profile-select'", $autosave);
        $this->assertStringContainsString('__kfAutosaveDelegated', $autosave);

        $card = file_get_contents(resource_path('js/profile-section-card.js'));
        $this->assertStringContainsString('requestClose()', $card);

        $activity = file_get_contents(resource_path('views/site/borrower/profile/activity.blade.php'));
        $this->assertStringContainsString('seenDetailKeys', $activity);

        $completion = file_get_contents(app_path('Services/ProfileCompletionService.php'));
        $this->assertStringContainsString('employment_type', $completion);
        $this->assertStringContainsString('hasDocument', $completion);
    }
}
