<?php

namespace Tests\Feature;

use Tests\TestCase;

class PartnerConfirmModalsFeatureTest extends TestCase
{
    public function test_recovery_case_view_wires_confirm_on_completing_actions(): void
    {
        $blade = file_get_contents(resource_path('views/site/vendor/recovery-case.blade.php'));

        $this->assertStringContainsString('window.confirmForm($el', $blade);
        $this->assertStringContainsString('repossession_title', $blade);
        $this->assertStringContainsString('sold_title', $blade);
        $this->assertStringContainsString('reminder_title', $blade);
    }

    public function test_task_and_wallet_views_wire_confirm_modals(): void
    {
        $task = file_get_contents(resource_path('views/site/vendor/task.blade.php'));
        $cover = file_get_contents(resource_path('views/site/vendor/_cover_job_detail.blade.php'));
        $wallet = file_get_contents(resource_path('views/site/vendor/recovery-wallet.blade.php'));

        $this->assertStringContainsString('window.confirmForm($el', $task);
        $this->assertStringContainsString('task_complete_button', $task);
        $this->assertStringContainsString('window.confirmForm($el', $cover);
        $this->assertStringContainsString('accept_cover_title', $cover);
        $this->assertStringContainsString('insurance_title', $cover);
        $this->assertStringContainsString('tab_asset', $cover);
        $this->assertStringContainsString('ownership_document', $cover);
        $this->assertStringContainsString("value=\"comprehensive\"", $cover);
        $this->assertStringNotContainsString('third_party', $cover);
        $this->assertStringContainsString('prev()', $cover);
        $this->assertStringContainsString('next()', $cover);
        $this->assertStringContainsString('window.confirmForm($el', $wallet);
        $this->assertStringContainsString('payout_title', $wallet);
        $this->assertStringContainsString('dispute_title', $wallet);
    }

    public function test_partner_and_affiliate_apply_views_wire_submit_confirm(): void
    {
        $partner = file_get_contents(resource_path('views/site/partners/apply.blade.php'));
        $affiliate = file_get_contents(resource_path('views/site/affiliate/apply.blade.php'));

        $this->assertStringContainsString('window.confirmForm($el', $partner);
        $this->assertStringContainsString('confirm_message_roles', $partner);
        $this->assertStringContainsString('window.confirmForm($el', $affiliate);
        $this->assertStringContainsString('confirm_title', $affiliate);
    }
}
