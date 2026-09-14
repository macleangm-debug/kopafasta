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
        $this->assertStringContainsString('agricultureOverviewReady', $js);
        $this->assertStringContainsString('agricultureNamedInput', $js);
        $this->assertStringContainsString('agricultureAddressAlpine', $js);
        $this->assertStringContainsString('farm_activity_photos', $js);
        $this->assertStringContainsString('land_use_evidence', $js);
        $this->assertStringContainsString('KopaFastaForm.isComplete', $js);
    }

    public function test_agriculture_overview_exposes_region_district_address_fields(): void
    {
        $agro = file_get_contents(resource_path('views/site/apply/_agriculture-details-step.blade.php'));
        $address = file_get_contents(resource_path('views/components/site/address-fields.blade.php'));

        $this->assertStringContainsString('data-agro-overview', $agro);
        $this->assertStringContainsString('x-site.address-fields', $agro);
        $this->assertStringContainsString('prefix="farming"', $agro);
        $this->assertStringContainsString('pickRegion(value)', $address);
        $this->assertStringContainsString('this.$refs.regionSelect', $address);
        $this->assertStringNotContainsString('@js($regionName)', $address);
        $this->assertStringNotContainsString('@js($districtName)', $address);
        // Blade treats @js() even inside comments as a directive — must never appear bare.
        $this->assertStringNotContainsString('@js()', $address);
        $this->assertStringContainsString('hidden lg:block', $address);
    }

    public function test_address_fields_component_renders_without_blade_js_crash(): void
    {
        $html = $this->view('components.site.address-fields', [
            'prefix' => 'nok',
            'required' => true,
            'locations' => [
                'Dar es Salaam' => ['Ilala', 'Kinondoni'],
                'Morogoro' => ['Morogoro', 'Kilosa'],
            ],
        ])->render();

        $this->assertStringContainsString('name="nok_region"', $html);
        $this->assertStringContainsString('name="nok_district"', $html);
        $this->assertStringContainsString('Dar es Salaam', $html);
    }

    public function test_camera_opens_fresh_after_replace_or_delete(): void
    {
        $multi = file_get_contents(resource_path('views/components/site/multi-page-document-upload.blade.php'));
        $single = file_get_contents(resource_path('views/components/site/single-image-document-upload.blade.php'));
        $apply = file_get_contents(resource_path('views/site/apply/_apply-document-holder.blade.php'));

        $this->assertStringContainsString('resetCapture()', $multi);
        $this->assertStringContainsString('fresh', $multi);
        $this->assertStringContainsString('fresh: true', $apply);
        $this->assertStringContainsString("clear-capture", $apply);
        $this->assertStringContainsString('$event.detail?.fresh', $single);
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
    }

    public function test_member_facing_document_holders_hide_status_badges(): void
    {
        $profile = file_get_contents(resource_path('views/components/site/profile-document-field.blade.php'));
        $apply = file_get_contents(resource_path('views/site/apply/_apply-document-holder.blade.php'));

        $this->assertStringNotContainsString('status_required', $profile);
        $this->assertStringNotContainsString('status_optional', $profile);
        $this->assertStringNotContainsString('statusLabel', $profile);
        $this->assertStringNotContainsString('status_required', $apply);
        $this->assertStringNotContainsString('status_pending', $apply);
        $this->assertStringNotContainsString('status_optional', $apply);
    }

    public function test_multi_page_supports_rotate_and_add_page_icons(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/multi-page-document-upload.blade.php'));
        $this->assertStringContainsString('rotatePage(', $blade);
        $this->assertStringContainsString('document_upload.rotate', $blade);
        $this->assertStringContainsString('labels.addAnother', $blade);
        $this->assertStringContainsString(':aria-label="pages.length ? labels.captureMore : labels.capturePage"', $blade);
        $this->assertStringContainsString(':aria-label="labels.addAnother"', $blade);
        $this->assertStringContainsString('size-[4.25rem]', $blade);
    }

    public function test_document_upload_skips_confirm_modal(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/document-upload.blade.php'));
        $this->assertStringContainsString('this.performSubmit();', $blade);
        $this->assertStringNotContainsString('confirmForm(null, {', $blade);
        $this->assertStringNotContainsString('showBorrowerFeedback', $blade);
    }
}
