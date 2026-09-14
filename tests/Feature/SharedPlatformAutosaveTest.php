<?php

namespace Tests\Feature;

use Tests\TestCase;

class SharedPlatformAutosaveTest extends TestCase
{
    public function test_shared_kf_autosave_module_is_registered_once(): void
    {
        $init = file_get_contents(resource_path('js/alpine-init.js'));
        $this->assertStringContainsString("from './kf-autosave'", $init);
        $this->assertSame(1, substr_count($init, 'registerKfAutosave(Alpine)'));

        $module = file_get_contents(resource_path('js/kf-autosave.js'));
        $this->assertStringContainsString('window.kfBindAutosaveForm', $module);
        $this->assertStringContainsString('kfShowInlineSaving', $module);
        $this->assertStringContainsString('kfFlashInlineSaved', $module);
        $this->assertStringContainsString('900', $module); // Loan Wizard debounce reused
        $this->assertStringContainsString('mySeq !== seq', $module); // stale guard
        $this->assertStringNotContainsString('BorrowerAutosave', $module);
        $this->assertStringNotContainsString('AffiliateAutosave', $module);
    }

    public function test_profile_and_partner_forms_opt_into_shared_autosave(): void
    {
        $personal = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $this->assertStringContainsString('data-kf-autosave', $personal);
        $this->assertStringContainsString('value="kin"', $personal);
        $this->assertStringContainsString('value="contact"', $personal);

        $partner = file_get_contents(resource_path('views/site/partner-account/personal.blade.php'));
        $this->assertStringContainsString('data-kf-autosave', $partner);
        $this->assertStringContainsString('value="contact"', $partner);
    }

    public function test_nida_holder_uses_landscape_thumbs_without_view_ctas(): void
    {
        $holder = file_get_contents(resource_path('views/site/borrower/profile/_national_id_holder.blade.php'));
        $this->assertStringContainsString('aspect-[1.586]', $holder);
        $this->assertStringNotContainsString("view_document') }} · '", $holder);
        $this->assertStringContainsString('beginReplace', $holder);
    }

    public function test_borrower_registration_no_longer_requires_password_step(): void
    {
        $blade = file_get_contents(resource_path('views/site/auth/register-borrower.blade.php'));
        $this->assertStringContainsString('2-step', $blade);
        $this->assertStringNotContainsString('Step 3: Password', $blade);
        $this->assertStringNotContainsString('name="password"', $blade);

        $auth = file_get_contents(app_path('Http/Controllers/Site/AuthController.php'));
        $this->assertStringContainsString("Hash::make(Str::password(32))", $auth);
        $this->assertStringContainsString('setup-pin', $auth);
    }
}
