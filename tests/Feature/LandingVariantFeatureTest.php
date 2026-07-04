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
