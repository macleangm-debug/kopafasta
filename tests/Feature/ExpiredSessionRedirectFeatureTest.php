<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExpiredSessionRedirectFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth:web'])->group(function (): void {
            Route::get('/borrower/__kf-qa/session-probe', fn () => 'ok');
            Route::get('/partner/__kf-qa/session-probe', fn () => 'ok');
        });

        // CSRF expiry on protected path prefixes (auth already gone / token stale).
        Route::middleware('web')->group(function (): void {
            Route::post('/borrower/__kf-qa/csrf', function () {
                throw new TokenMismatchException;
            });
            Route::post('/partner/__kf-qa/csrf', function () {
                throw new TokenMismatchException;
            });
        });
    }

    public function test_guest_borrower_route_redirects_to_login_with_session_message(): void
    {
        $response = $this->get('/borrower/__kf-qa/session-probe');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/login', $location);
        $this->assertStringNotContainsString('/admin/login', $location);
        $response->assertSessionHas('status', __('site.auth.session_expired'));
    }

    public function test_guest_partner_route_redirects_to_partner_login(): void
    {
        $response = $this->get('/partner/__kf-qa/session-probe');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/login', $location);
        $this->assertStringContainsString('portal=partner', $location);
        $this->assertStringNotContainsString('/admin/login', $location);
        $response->assertSessionHas('status', __('site.auth.session_expired'));
    }

    public function test_borrower_csrf_mismatch_redirects_to_login_not_419_page(): void
    {
        $response = $this->post('/borrower/__kf-qa/csrf');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $response->assertSessionHas('status', __('site.auth.session_expired'));
        $this->assertNotEquals(419, $response->status());
    }

    public function test_partner_csrf_mismatch_redirects_to_partner_login(): void
    {
        $response = $this->post('/partner/__kf-qa/csrf');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/login', $location);
        $this->assertStringContainsString('portal=partner', $location);
        $response->assertSessionHas('status', __('site.auth.session_expired'));
    }

    public function test_session_expired_copy_localized(): void
    {
        app()->setLocale('sw');
        $this->assertSame(
            'Kipindi chako kimeisha. Tafadhali ingia tena.',
            __('site.auth.session_expired')
        );
        app()->setLocale('en');
        $this->assertSame(
            'Your session has expired. Please sign in again.',
            __('site.auth.session_expired')
        );
    }
}
