<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StagingAdminCredentialSyncAndMemberCardCopyTest extends TestCase
{
    public function test_staging_sync_admin_command_refuses_non_staging_environment(): void
    {
        $this->assertNotSame('staging', app()->environment());

        $exit = Artisan::call('staging:sync-admin-credentials', [
            '--payload' => '/tmp/does-not-matter.json',
            '--force' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('APP_ENV=staging', Artisan::output());
    }

    public function test_member_card_copy_uses_member_not_customer_terminology(): void
    {
        $this->assertSame('KOPAFASTA MEMBER', __('borrower.membership.member_role_customer', [], 'en'));
        $this->assertSame('Member since', __('borrower.membership.customer_since_label', [], 'en'));
        $this->assertSame('Permanent member', __('borrower.membership.identity_standing_title', [], 'en'));
        $this->assertSame(
            'Your member number stays with you — this card does not expire.',
            __('borrower.membership.identity_standing_body', [], 'en')
        );

        $this->assertSame('MWANACHAMA WA KOPAFASTA', __('borrower.membership.member_role_customer', [], 'sw'));
        $this->assertSame('Mwanachama tangu', __('borrower.membership.customer_since_label', [], 'sw'));
        $this->assertSame('Mwanachama wa kudumu', __('borrower.membership.identity_standing_title', [], 'sw'));
        $this->assertSame(
            'Nambari yako ya uanachama itabaki kuwa yako — kadi hii haina muda wa kuisha.',
            __('borrower.membership.identity_standing_body', [], 'sw')
        );
    }
}
