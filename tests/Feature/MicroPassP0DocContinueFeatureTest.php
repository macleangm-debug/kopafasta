<?php

namespace Tests\Feature;

use Tests\TestCase;

class MicroPassP0DocContinueFeatureTest extends TestCase
{
    public function test_document_source_picker_uses_window_events_and_teleport(): void
    {
        $picker = file_get_contents(resource_path('views/components/site/document-source-picker.blade.php'));

        $this->assertStringContainsString("dispatchEvent(new CustomEvent('document-source'", $picker);
        $this->assertStringContainsString('x-teleport="body"', $picker);
        $this->assertStringContainsString('pick(', $picker);
        $this->assertStringNotContainsString('$dispatch(\'document-source\'', $picker);
    }

    public function test_apply_and_profile_holders_listen_on_window(): void
    {
        $apply = file_get_contents(resource_path('views/site/apply/_apply-document-holder.blade.php'));
        $profile = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));

        $this->assertStringContainsString('@document-source.window', $apply);
        $this->assertStringContainsString('@document-source.window', $profile);
        $this->assertStringContainsString('host-id', $apply);
        $this->assertStringContainsString('host-id', $profile);
    }

    public function test_single_image_source_driven_requires_confirm_use(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/single-image-document-upload.blade.php'));

        $this->assertStringContainsString('confirmUse()', $blade);
        $this->assertStringContainsString('document_upload.use_photo', $blade);
        $this->assertStringContainsString('if (! this.sourceDriven || this.autoSubmit || ! this.fromCamera)', $blade);
        $this->assertStringContainsString('commitFile', $blade);
    }

    public function test_agriculture_ready_uses_draft_fallback_in_wizard(): void
    {
        $js = file_get_contents(resource_path('js/apply-wizard.js'));

        $this->assertStringContainsString('_lastDraftInputs', $js);
        $this->assertStringContainsString('agricultureStepReady', $js);
        $this->assertStringContainsString('syncAgricultureFieldsFromAlpine', $js);
        $this->assertStringContainsString('farm_activity_photos', $js);
        $this->assertStringContainsString('land_use_evidence', $js);
    }

    public function test_apply_document_remove_uses_confirm_form(): void
    {
        $blade = file_get_contents(resource_path('views/site/apply/_apply-document-holder.blade.php'));
        $this->assertStringContainsString('confirmForm(null', $blade);
        $this->assertStringContainsString('removeEducationDocument', $blade);
    }

    public function test_single_image_shows_saving_saved_states(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/single-image-document-upload.blade.php'));
        $this->assertStringContainsString("saveState === 'saving'", $blade);
        $this->assertStringContainsString("saveState === 'saved'", $blade);
        $this->assertStringContainsString('document_upload.could_not_save', $blade);
        $this->assertStringContainsString('retrySave()', $blade);
    }

    public function test_borrower_layout_skips_feedback_modal_for_inline_status(): void
    {
        $layout = file_get_contents(resource_path('views/components/site/borrower-layout.blade.php'));
        $this->assertStringContainsString('kf_status_inline', $layout);

        $controller = file_get_contents(app_path('Http/Controllers/Site/BorrowerController.php'));
        $this->assertStringContainsString("->with('kf_status_inline', true)", $controller);
        $this->assertStringContainsString("__('borrower.profile.saved_inline')", $controller);
        $this->assertStringNotContainsString(
            "->with('status', __('borrower.profile.save_confirm_title'));",
            $controller
        );
    }

    public function test_profile_update_accepts_post_and_put(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString("Route::match(['put', 'post'], '/borrower/profile/{section}'", $routes);
    }

    public function test_profile_document_field_avoids_nested_remove_form(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));
        $this->assertStringContainsString('confirmRemoveDocument()', $blade);
        $this->assertStringContainsString('submitProfileDocumentForm()', $blade);
        $this->assertStringNotContainsString('@method(\'DELETE\')', $blade);
        $this->assertStringContainsString('statusLabel', $blade);
    }

    public function test_multi_page_supports_rotate_and_add_page_icons(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/multi-page-document-upload.blade.php'));
        $this->assertStringContainsString('rotatePage(', $blade);
        $this->assertStringContainsString('document_upload.rotate', $blade);
        $this->assertStringContainsString('labels.addAnother', $blade);
    }

    public function test_apply_document_badges_hide_required_when_supplied(): void
    {
        $blade = file_get_contents(resource_path('views/site/apply/_apply-document-holder.blade.php'));
        $this->assertStringContainsString('!educationDocuments[@js($docCode)]?.customer_document_id', $blade);
        $this->assertStringContainsString('status_pending', $blade);
    }

    public function test_document_upload_skips_confirm_modal(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/document-upload.blade.php'));
        $this->assertStringContainsString('this.performSubmit();', $blade);
        $this->assertStringNotContainsString('confirmForm(null, {', $blade);
        $this->assertStringNotContainsString('showBorrowerFeedback', $blade);
    }
}
