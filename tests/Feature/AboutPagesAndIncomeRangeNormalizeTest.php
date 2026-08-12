<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPagesAndIncomeRangeNormalizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_subpages_render(): void
    {
        foreach ([
            'site.about',
            'site.about.founding',
            'site.about.trust',
            'site.about.impact',
            'site.about.roadmap',
        ] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee(__('site.about.nav.founding'), false);
        }

        $this->get(route('site.about.founding'))
            ->assertSee('TACIP', false)
            ->assertSee('DataVision International', false)
            ->assertSee('100,000', false)
            ->assertSee('Prof. Henry Mwakyebe', false)
            ->assertSee('kopafasta', false);
    }

    public function test_legacy_income_range_keys_normalize_to_selectable_values(): void
    {
        $this->assertSame('1m_5m', normalize_income_range_key('1m_plus'));
        $this->assertSame('1m_5m', normalize_income_range_key('above_1m'));
        $this->assertSame('100k_300k', normalize_income_range_key('below_100k'));
        $this->assertSame('500k_1m', normalize_income_range_key('500k_1m'));
        $this->assertArrayHasKey('1m_5m', income_range_select_options());
        $this->assertArrayNotHasKey('1m_plus', income_range_select_options());
    }
}
