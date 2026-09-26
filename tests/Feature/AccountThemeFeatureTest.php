<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountThemeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_and_partner_shells_share_one_theme_engine(): void
    {
        $borrowerLayout = file_get_contents(resource_path('views/components/site/borrower-layout.blade.php'));
        $partnerShell = file_get_contents(resource_path('views/components/site/partner-shell.blade.php'));
        $affiliateLayout = file_get_contents(resource_path('views/components/site/affiliate-layout.blade.php'));
        $supplierLayout = file_get_contents(resource_path('views/components/site/supplier-layout.blade.php'));
        $engine = file_get_contents(resource_path('js/account-theme.js'));
        $css = file_get_contents(resource_path('css/app.css'));
        $appJs = file_get_contents(resource_path('js/app.js'));
        $receipt = file_get_contents(resource_path('views/site/borrower/payments/_show_body.blade.php'));

        $this->assertStringContainsString('kf-account-shell', $borrowerLayout);
        $this->assertStringContainsString('kf-account-shell', $partnerShell);
        $this->assertStringContainsString('site.account-theme-boot', $borrowerLayout);
        $this->assertStringContainsString('site.account-theme-boot', $partnerShell);
        $this->assertStringContainsString('site.theme-toggle', $borrowerLayout);
        $this->assertStringContainsString('site.theme-toggle', $partnerShell);
        $this->assertStringContainsString('x-site.partner-shell', $affiliateLayout);
        $this->assertStringContainsString('x-site.partner-shell', $supplierLayout);
        $this->assertStringContainsString('bindAccountTheme', $appJs);
        $this->assertStringContainsString('startViewTransition', $engine);
        $this->assertStringContainsString('kf-account-theme', $engine);
        $this->assertStringContainsString('html.kf-account-shell[data-theme="dark"]', $css);
        $this->assertStringContainsString('--kf-accent-ink', $css);
        $this->assertStringContainsString('.kf-mobile-bottom-nav', $css);
        $this->assertStringContainsString('.text-brand\\/70', $css);
        $this->assertStringContainsString('.kf-skeleton', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('.kf-receipt', $css);
        $this->assertStringContainsString('kf-receipt', $receipt);
        $this->assertStringContainsString('kf-mobile-bottom-nav', $borrowerLayout);
        $this->assertStringContainsString('kf-mobile-bottom-nav', $partnerShell);
        $this->assertStringContainsString('site.skeleton', $borrowerLayout);
        $this->assertStringNotContainsString('affiliate-dark-mode', $css);
        $this->assertStringNotContainsString('borrower-dark-mode', $css);

        $boot = file_get_contents(resource_path('views/components/site/account-theme-boot.blade.php'));
        $this->assertStringContainsString('html.kf-account-shell[data-theme="dark"]', $boot);
        $this->assertStringContainsString('#0c1110', $boot);

        $skeleton = file_get_contents(resource_path('views/components/site/skeleton.blade.php'));
        $this->assertStringContainsString("variant === 'hero'", $skeleton);
        $this->assertStringContainsString("variant === 'asset'", $skeleton);
    }

    public function test_theme_persists_for_authenticated_account_and_stays_off_public_site(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);

        $this->actingAs($user)
            ->post(route('site.account.theme'), ['theme' => 'dark'])
            ->assertNoContent();

        $this->assertSame('dark', data_get($user->fresh()->preferences, AccountThemeService::PREFERENCE_KEY));

        $this->actingAs($user->fresh())
            ->withCookie(AccountThemeService::COOKIE, 'dark')
            ->get(route('site.borrower.settings'))
            ->assertOk()
            ->assertSee('kf-account-shell', false)
            ->assertSee('data-kf-theme-toggle', false)
            ->assertSee('data-theme="dark"', false);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertDontSee('kf-account-shell', false)
            ->assertDontSee('data-kf-theme-toggle', false);
    }
}
