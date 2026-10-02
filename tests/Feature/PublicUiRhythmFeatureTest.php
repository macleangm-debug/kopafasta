<?php

namespace Tests\Feature;

use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUiRhythmFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_how_it_works_copy_and_step_four_are_decision_not_disbursement(): void
    {
        $this->assertSame('Hatua rahisi kutoka kuchagua hadi kuomba.', __('site.how_it_works.headline', [], 'sw'));
        $this->assertSame(
            'Chagua suluhisho linalokufaa, jaza maelezo yako na fuatilia ombi lako moja kwa moja kutoka kwenye simu yako.',
            __('site.how_it_works.subtitle', [], 'sw')
        );
        $this->assertSame('Pokea uamuzi', __('site.how_it_works.steps.3.title', [], 'sw'));
        $this->assertSame('Receive a decision', __('site.how_it_works.steps.3.title', [], 'en'));
        $this->assertStringNotContainsString('Pata fedha', __('site.how_it_works.steps.3.title', [], 'sw'));
        $this->assertStringNotContainsString('Get funded', __('site.how_it_works.steps.3.title', [], 'en'));

        $this->seed(PublicLoanProductsSeeder::class);
        $html = $this->withSession(['locale' => 'sw'])->get(route('site.home'))->assertOk()->getContent();

        $this->assertStringContainsString('Hatua rahisi kutoka kuchagua hadi kuomba.', $html);
        $this->assertStringContainsString('Pokea uamuzi', $html);
        $this->assertStringNotContainsString('Pata fedha', $html);
        $this->assertStringNotContainsString('1 Jisajili → 2 Chagua bidhaa → 3 Omba mtandaoni → 4', $html);
    }

    public function test_homepage_sections_share_public_section_grammar_and_carousel_controls(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);

        $html = $this->withSession(['locale' => 'sw'])->get(route('site.home'))->assertOk()->getContent();

        $this->assertStringContainsString('kf-public-section', $html);
        $this->assertStringContainsString('kf-public-section__eyebrow', $html);
        $this->assertStringContainsString('kf-public-section__title', $html);
        $this->assertStringContainsString('kf-carousel-ctrl', $html);
        $this->assertStringContainsString('Bidhaa zote za mkopo →', $html);
        $this->assertStringContainsString('Tazama mali zote →', $html);
        $this->assertStringContainsString('Tunakufahamu vizuri zaidi.', $html);
        $this->assertStringNotContainsString('rounded-full bg-brand text-white shadow-md', $html);

        // Premium glass + glare remain on hero / Plus featured surfaces only.
        $this->assertStringContainsString('kf-glass-hero', $html);
        $this->assertStringContainsString('data-kf-glass-hero', $html);
        $this->assertStringContainsString('kf-glass-hero--glare', $html);
        $this->assertStringContainsString('kf-glass-hero__surface', $html);
        $this->assertStringContainsString('kf-hero-enter', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data-kf-glass-hero'));

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('padding-block: 2rem', $css);
        $this->assertStringContainsString('padding-block: 3rem', $css);
        $this->assertStringNotContainsString('padding-block: 5rem', $css);
        $this->assertStringContainsString('kf-glass-glare', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    public function test_english_how_it_works_and_products_copy(): void
    {
        $this->assertSame('Simple steps from choosing to applying.', __('site.how_it_works.headline', [], 'en'));
        $this->assertSame(
            'Choose a solution that fits, complete your details and track your application directly from your phone.',
            __('site.how_it_works.subtitle', [], 'en')
        );
        $this->assertSame('All loan products →', __('site.products.view_all', [], 'en'));
        $this->assertSame('View all assets →', __('site.marketplace.view_all', [], 'en'));
    }
}
