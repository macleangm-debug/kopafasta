<?php

namespace Tests\Feature;

use Tests\TestCase;

class AffiliateDocumentUploadP0FeatureTest extends TestCase
{
    public function test_apply_holders_attach_locally_and_never_submit_the_parent_form(): void
    {
        $formDoc = file_get_contents(resource_path('views/components/site/form-document-field.blade.php'));
        $adminDoc = file_get_contents(resource_path('views/components/admin/document-upload.blade.php'));
        $single = file_get_contents(resource_path('views/components/site/single-image-document-upload.blade.php'));
        $multi = file_get_contents(resource_path('views/components/site/multi-page-document-upload.blade.php'));
        $picker = file_get_contents(resource_path('views/components/site/document-source-picker.blade.php'));

        $this->assertStringContainsString('data-document-attach-only', $formDoc);
        $this->assertStringContainsString('data-document-attach-only', $adminDoc);
        $this->assertStringContainsString("this.\$el.closest('[data-document-attach-only]')", $single);
        $this->assertStringContainsString('this.markAttachedLocally()', $single);
        $this->assertStringContainsString('Never requestSubmit', $single);
        $this->assertStringContainsString('kfFlashInlineSaved', $single);
        $this->assertStringContainsString('[data-document-attach-only]', $multi);
        $this->assertStringContainsString('kfFlashInlineSaved', $multi);
        $this->assertStringContainsString('menuOpen', $picker);
        $this->assertStringContainsString('sheetOpen', $picker);
    }

    public function test_affiliate_and_partner_apply_reuse_the_shared_holder(): void
    {
        $affiliate = file_get_contents(resource_path('views/site/affiliate/apply.blade.php'));
        $partner = file_get_contents(resource_path('views/site/partners/apply.blade.php'));

        $this->assertStringContainsString('x-site.form-nida-capture', $affiliate);
        $this->assertStringContainsString('doc_national_id_front', $affiliate);
        $this->assertStringContainsString('doc_national_id_back', $affiliate);
        $this->assertStringContainsString('x-site.form-document-field', $partner);
    }
}
