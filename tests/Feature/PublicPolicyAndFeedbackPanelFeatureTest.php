<?php

namespace Tests\Feature;

use App\Services\PublicPolicyService;
use Database\Seeders\PublicPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPolicyAndFeedbackPanelFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_policies_render_full_content_en_and_sw(): void
    {
        $this->seed(PublicPolicySeeder::class);

        foreach (['responsible_lending', 'complaints', 'aml', 'kyc'] as $key) {
            $route = match ($key) {
                'responsible_lending' => route('site.responsible-lending'),
                'complaints' => route('site.legal.complaints'),
                'aml' => route('site.legal.aml'),
                'kyc' => route('site.legal.kyc'),
            };

            $en = $this->withSession(['locale' => 'en'])->get($route);
            $en->assertOk();
            $doc = app(PublicPolicyService::class)->localized($key, 'en');
            $this->assertNotEmpty($doc['body'] ?? null);
            $en->assertSee(e($doc['title']), false);
            $en->assertSee('Purpose', false);
            $en->assertDontSee('Identity and source-of-funds checks required by law.', false);

            $sw = $this->withSession(['locale' => 'sw'])->get($route);
            $sw->assertOk();
        }

        $this->get('/legal/aml-kyc')->assertRedirect(route('site.legal.aml'));
    }

    public function test_feedback_page_embeds_complete_self_contained_form(): void
    {
        $html = $this->get(route('site.feedback', ['open' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="subject"', $html);
        $this->assertStringContainsString('name="message"', $html);
        $this->assertStringContainsString('phone_local', $html);
        $this->assertStringContainsString('compliment', $html);
        $this->assertStringNotContainsString('investment_inquiry', $html);
        $this->assertStringNotContainsString('Investment inquiry', $html);
    }

    public function test_swahili_tagline_uses_corrected_copy(): void
    {
        $this->assertSame('Mtaji unaotembea kwa kasi yako.', __('site.brand.tagline', [], 'sw'));
        $this->assertSame('Mtaji unaotembea kwa kasi yako.', __('site.footer.tagline', [], 'sw'));
        $this->assertStringNotContainsString('unaosogea', __('site.brand.tagline', [], 'sw'));
    }
}
