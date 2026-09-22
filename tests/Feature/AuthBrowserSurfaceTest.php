<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthBrowserSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_bare_locale_and_does_not_lock_zoom(): void
    {
        $this->withSession(['locale' => 'sw'])
            ->get(route('site.login'))
            ->assertOk()
            ->assertSee('lang="sw"', false)
            ->assertSee('interactive-widget=resizes-content', false)
            ->assertDontSee('maximum-scale=1', false)
            ->assertSee('Notification.requestPermission', false)
            ->assertSee('notifications=()', false)
            ->assertSee('translate="no"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('data-lpignore="true"', false)
            ->assertSee('data-phone-locked="1"', false)
            ->assertSee('+255', false)
            ->assertDontSee('🇹🇿 +255', false);
    }

    public function test_english_login_uses_lang_en(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('site.login'))
            ->assertOk()
            ->assertSee('lang="en"', false);
    }
}
