<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingVariantFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_defaults_to_variant_a(): void
    {
        $response = $this->get(route('site.home'));

        $response->assertOk();
        $response->assertSee(__('site.hero.title'), false);
    }

    public function test_homepage_does_not_show_demo_screens_as_a_mobile_carousel(): void
    {
        $html = $this->get(route('site.home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('● ○ ○ ○', $html);
        $this->assertStringNotContainsString('w-[min(100%,20rem)]', $html);
        $this->assertStringContainsString('hidden lg:block', $html);
        $this->assertStringContainsString(__('site.hero.get_started'), $html);
    }

    public function test_landing_query_param_switches_to_variant_b(): void
    {
        $response = $this->get(route('site.home', ['landing' => 'b']));

        $response->assertOk();
        $response->assertSee(__('site.hero.variant_b_title'), false);
    }

    public function test_landing_variant_persists_in_session(): void
    {
        $this->get(route('site.home', ['landing' => 'b']))->assertOk();

        $response = $this->get(route('site.home'));

        $response->assertOk();
        $response->assertSee(__('site.hero.variant_b_title'), false);
    }
}
