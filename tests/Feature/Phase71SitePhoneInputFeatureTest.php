<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase71SitePhoneInputFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_start_page_renders_phone_prefix_input(): void
    {
        $this->get(route('site.partner.start'))
            ->assertOk()
            ->assertSee(__('site.feedback.phone'), false)
            ->assertSee('+255', false)
            ->assertSee('data-phone-locked="1"', false)
            ->assertSee(__('borrower.register.mobile_hint'), false);
    }

    public function test_borrower_registration_page_renders_country_phone_fields(): void
    {
        $this->get(route('site.register.borrower'))
            ->assertOk()
            ->assertSee(__('borrower.register.country'), false)
            ->assertSee(__('borrower.register.mobile'), false)
            ->assertSee(__('borrower.register.mobile_hint'), false);
    }

    public function test_vendor_registration_page_renders_phone_prefix_input(): void
    {
        $this->get(route('site.register.vendor'))
            ->assertOk()
            ->assertSee('Business phone', false)
            ->assertSee('+255', false);
    }

    public function test_investor_registration_page_renders_phone_prefix_input(): void
    {
        $this->get(route('site.register.investor'))
            ->assertOk()
            ->assertSee('Phone', false)
            ->assertSee('+255', false)
            ->assertSee('data-phone-locked="1"', false)
            ->assertSee(__('borrower.register.mobile_hint'), false);
    }

    public function test_capital_partner_registration_page_renders_phone_prefix_input(): void
    {
        $this->get(route('site.register.capital'))
            ->assertOk()
            ->assertSee('Phone', false)
            ->assertSee('+255', false);
    }

    public function test_affiliate_apply_page_renders_phone_prefix_input(): void
    {
        $this->get(route('site.affiliate.apply'))
            ->assertOk()
            ->assertSee('+255', false)
            ->assertSee('data-phone-locked="1"', false)
            ->assertSee(__('borrower.register.mobile_hint'), false);
    }
}
