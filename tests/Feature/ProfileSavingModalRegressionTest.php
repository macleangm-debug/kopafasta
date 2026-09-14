<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSavingModalRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_saving_overlay_never_modals_on_borrower_profile(): void
    {
        $overlay = file_get_contents(resource_path('js/saving-overlay.js'));

        $this->assertStringContainsString('kfIsBorrowerProfileContext', $overlay);
        $this->assertStringContainsString('kfShowInlineSaving', $overlay);
        $this->assertTrue(
            str_contains($overlay, 'borrower/profile') || str_contains($overlay, 'borrower\\/profile'),
            'Overlay must detect /borrower/profile routes'
        );
        $this->assertStringContainsString('data-kf-profile-page', $overlay);
        // Blocking store must not be armed when Profile context is true.
        $this->assertMatchesRegularExpression(
            '/kfShowSaving[\s\S]*kfIsBorrowerProfileContext\(\)[\s\S]*kfShowInlineSaving/',
            $overlay
        );
        $this->assertStringContainsString('data-inline-document-progress', $overlay);
    }

    public function test_profile_shell_marks_profile_page_context(): void
    {
        $shell = file_get_contents(resource_path('views/site/borrower/profile/_profile_shell.blade.php'));
        $this->assertStringContainsString('data-kf-profile-page', $shell);
        $this->assertStringContainsString('data-kf-saved-toast', $shell);
    }

    public function test_face_wizard_does_not_force_blocking_upload_modal_copy(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));
        $this->assertStringNotContainsString("kfShowSaving(@js(__('borrower.profile.uploading_documents')))", $blade);
        $this->assertStringContainsString('kfShowInlineSaving', $blade);
        $this->assertStringContainsString("data-no-saving", $blade);
    }
}
