<?php

namespace Tests\Feature;

use Tests\TestCase;

class AffiliateEconomicPassFeatureTest extends TestCase
{
    public function test_shared_upload_and_save_wiring_does_not_stall_at_ninety_two(): void
    {
        $overlay = file_get_contents(resource_path('views/components/site/upload-busy-overlay.blade.php'));
        $saving = file_get_contents(resource_path('js/saving-overlay.js'));
        $setupPin = file_get_contents(resource_path('views/site/auth/setup-pin.blade.php'));
        $authShell = file_get_contents(resource_path('views/components/site/auth-shell.blade.php'));
        $adminUpload = file_get_contents(resource_path('views/components/admin/document-upload.blade.php'));
        $formDoc = file_get_contents(resource_path('views/components/site/form-document-field.blade.php'));
        $feePay = file_get_contents(resource_path('views/site/affiliate/apply-fee-pay.blade.php'));
        $tracking = file_get_contents(resource_path('views/site/partners/tracking.blade.php'));

        $this->assertStringNotContainsString('92', $overlay);
        $this->assertStringContainsString('this.percent < 90', $overlay);
        $this->assertStringContainsString('event.defaultPrevented', $saving);
        $this->assertStringContainsString('kfHideSaving', $saving);
        $this->assertStringContainsString('items-start lg:items-center', $authShell);
        $this->assertStringContainsString('relative z-10', $setupPin);
        $this->assertStringContainsString('kf-auth-input relative z-10', $setupPin);
        $this->assertStringContainsString('document-source-picker', $adminUpload);
        $this->assertStringContainsString('document-open-camera', $adminUpload);
        $this->assertStringContainsString('data-document-holder', $formDoc);
        $this->assertStringContainsString('data-document-attach-only', $formDoc);
        $this->assertStringContainsString('document-source-picker', $formDoc);
        $this->assertStringContainsString('site.borrower.payments._show_body', $feePay);
        $this->assertStringContainsString('kf-premium-panel', $feePay);
        $this->assertStringContainsString("success_modal_title", $tracking);
    }
}
