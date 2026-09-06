<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFeedbackClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feedback_excludes_investment_inquiry_and_accepts_compliment(): void
    {
        $page = $this->get(route('site.feedback', ['open' => 1]));
        $page->assertOk();
        $page->assertSee(__('site.feedback.categories.compliment'), false);
        $page->assertDontSee('Investment inquiry', false);
        $page->assertDontSee('investment_inquiry', false);
        $page->assertDontSee(__('site.feedback.categories.investment_inquiry'), false);

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
        $ticket = SupportTicket::query()->latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertSame('compliment', $ticket->category);
        $this->assertStringContainsString('255712345678', (string) $ticket->description);
    }

    public function test_faq_links_to_opened_feedback_form(): void
    {
        $this->get(route('site.faq'))
            ->assertOk()
            ->assertSee(route('site.feedback', ['open' => 1]), false)
            ->assertDontSee('Investment inquiry', false);
    }

    public function test_capital_partner_private_register_route_still_exists(): void
    {
        $this->get(route('site.register.capital'))
            ->assertOk();
    }
}
