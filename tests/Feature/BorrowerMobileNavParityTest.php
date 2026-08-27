<?php

namespace Tests\Feature;

use App\Services\BorrowerMobileNavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerMobileNavParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_parity_matrix_has_no_orphaned_borrower_or_plus_destinations(): void
    {
        $rows = app(BorrowerMobileNavService::class)->parityMatrix();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertTrue(
                $row['reachable'],
                $row['current'].' has no reachable mobile home ('.$row['parent'].' → '.$row['route'].')'
            );
        }
    }

    public function test_main_shell_has_five_destinations_and_plus_workspace_has_five(): void
    {
        $nav = app(BorrowerMobileNavService::class);

        $this->assertCount(5, $nav->mobilePrimaryNav());
        $this->assertSame(['dashboard', 'loans', 'marketplace', 'plus', 'profile'], array_column($nav->mobilePrimaryNav(), 'key'));
        $this->assertCount(5, $nav->plusWorkspaceNav());
        $this->assertSame(['plus-home', 'money', 'business', 'goals', 'more'], array_column($nav->plusWorkspaceNav(), 'key'));
        $this->assertCount(4, $nav->plusMoreItems());
    }

    public function test_focused_journeys_hide_global_bottom_nav(): void
    {
        $nav = app(BorrowerMobileNavService::class);

        $this->assertTrue($nav->hidesMobileNav('site.borrower.payments.show'));
        $this->assertTrue($nav->hidesMobileNav('site.borrower.application.contract'));
        $this->assertFalse($nav->hidesMobileNav('site.borrower.dashboard'));
        $this->assertFalse($nav->hidesMobileNav('site.borrower.loans'));
    }
}
