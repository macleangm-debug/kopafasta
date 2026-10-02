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
        $this->assertStringContainsString(route('site.support'), $html);
    }

    public function test_swahili_tagline_uses_corrected_copy(): void
    {
        $this->assertSame('Mtaji unaotembea kwa kasi yako.', __('site.brand.tagline', [], 'sw'));
        $this->assertSame('Mtaji unaotembea kwa kasi yako.', __('site.footer.tagline', [], 'sw'));
        $this->assertStringNotContainsString('unaosogea', __('site.brand.tagline', [], 'sw'));
    }

    public function test_homepage_hero_copy_frozen_en_and_sw(): void
    {
        $this->assertSame('Suluhisho la mikopo', __('site.hero.badge', [], 'sw'));
        $this->assertSame('Mtaji unaotembea kwa kasi yako.', __('site.hero.title', [], 'sw'));
        $this->assertSame(
            'Bidhaa mbalimbali za mikopo kwa mahitaji tofauti — chagua inayokufaa na uombe popote, moja kwa moja kutoka kwenye simu yako.',
            __('site.hero.subtitle', [], 'sw')
        );
        $this->assertSame('Anza Sasa', __('site.hero.get_started', [], 'sw'));
        $this->assertSame('Ingia', __('site.hero.sign_in', [], 'sw'));

        $this->assertSame('Loan solutions', __('site.hero.badge', [], 'en'));
        $this->assertSame('Capital that moves at your pace.', __('site.hero.title', [], 'en'));
        $this->assertSame(
            'A range of loans for different needs — choose what works for you and apply from anywhere, directly from your phone.',
            __('site.hero.subtitle', [], 'en')
        );
        $this->assertSame('Get Started', __('site.hero.get_started', [], 'en'));
        $this->assertSame('Sign In', __('site.hero.sign_in', [], 'en'));

        $home = $this->withSession(['locale' => 'sw'])->get(route('site.home'));
        $home->assertOk();
        $home->assertSee('Mtaji unaotembea kwa kasi yako.', false);
        $home->assertSee('Bidhaa mbalimbali za mikopo kwa mahitaji tofauti', false);
        $home->assertDontSee('Mikopo ya simu', false);
        $home->assertDontSee('omba kwa dakika', false);
        $home->assertSee('data-kf-glass-hero', false);
        $home->assertSee('data-kf-reveal', false);
    }

    public function test_session_status_does_not_open_feedback_success_panel(): void
    {
        app()->setLocale('sw');
        $expired = __('site.auth.session_expired');

        $html = $this->withSession([
            'status' => $expired,
            'locale' => 'sw',
        ])->get(route('site.login'))->assertOk()->getContent();

        $this->assertStringContainsString($expired, $html);
        // Feedback form panel must not treat generic status as Asante success.
        $this->assertStringNotContainsString('phase: "done"', $html);
        $this->assertStringNotContainsString("phase: 'done'", $html);
    }

    public function test_feedback_success_uses_dedicated_flash_key(): void
    {
        $response = $this->followingRedirects()->post(route('site.feedback.post'), [
            'category' => 'compliment',
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'phone' => '255712345678',
            'phone_local' => '712345678',
            'subject' => 'Great service',
            'message' => 'Thank you for helping me quickly.',
        ]);

        $response->assertOk();
        $html = $response->getContent();
        $this->assertTrue(
            str_contains($html, 'phase: "done"') || str_contains($html, "phase: 'done'"),
            'Feedback success panel should open in done phase'
        );
        $this->assertStringContainsString(__('site.feedback.success'), $html);
    }

    public function test_feedback_post_flashes_dedicated_key_not_status(): void
    {
        $response = $this->post(route('site.feedback.post'), [
            'category' => 'compliment',
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'phone' => '255712345678',
            'phone_local' => '712345678',
            'subject' => 'Great service',
            'message' => 'Thank you for helping me quickly.',
        ]);

        $response->assertRedirect(route('site.feedback', ['open' => 1]));
        $response->assertSessionHas('feedback_success', __('site.feedback.success'));
        $response->assertSessionMissing('status');
    }
}
