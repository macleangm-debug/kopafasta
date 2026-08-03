<?php

namespace Tests\Feature;

use App\Support\Celebration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class ApplyReviewSubmitUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_celebration_with_flashes_loan_submitted(): void
    {
        $redirect = Celebration::with(redirect('/borrower/apply/success'), 'loan_submitted');

        $this->assertInstanceOf(RedirectResponse::class, $redirect);
        $this->assertSame(['loan_submitted'], $redirect->getSession()->get(Celebration::SESSION_KEY));
    }

    public function test_set_review_page_helper_does_not_force_scroll_in_source(): void
    {
        $source = file_get_contents(resource_path('js/apply-wizard.js'));
        $this->assertNotFalse($source);

        // Review tab swaps must stay in-place under the sticky rail.
        $this->assertMatchesRegularExpression(
            '/setReviewPage\(page\)\s*\{[^}]*loadRepaymentSchedule\(\);[^}]*\}/s',
            $source,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/setReviewPage\(page\)\s*\{[^}]*scrollWizardIntoView\(\)/s',
            $source,
        );
    }

    public function test_quote_step_fee_gate_uses_safe_alpine_data_access(): void
    {
        $source = file_get_contents(resource_path('js/apply-wizard.js'));
        $quote = file_get_contents(resource_path('views/site/apply/_quote-step.blade.php'));
        $fee = file_get_contents(resource_path('views/components/site/application-fee-step.blade.php'));
        $group = file_get_contents(resource_path('views/site/apply/_group-steps.blade.php'));

        $this->assertNotFalse($source);
        $this->assertNotFalse($quote);
        $this->assertNotFalse($fee);
        $this->assertNotFalse($group);

        // feeGateOpen must exist on the Alpine component; Blade must use $data.* so a
        // stale Vite bundle without the property cannot ReferenceError-blank the step.
        $this->assertStringContainsString('feeGateOpen: false', $source);
        $this->assertStringContainsString('$data.feeGateOpen', $quote);
        $this->assertStringContainsString('$data.feeGateOpen', $fee);
        $this->assertStringContainsString('$data.feeGateOpen', $group);
        $this->assertStringNotContainsString("&& !feeGateOpen", $quote);
        $this->assertStringNotContainsString('|| feeGateOpen', $fee);
    }

    public function test_review_and_submit_translation_keys_exist(): void
    {
        foreach (['en', 'sw'] as $locale) {
            $this->assertNotEmpty(__('borrower.apply.review_step.page_overview', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.page_terms', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.page_schedule', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.deal_snapshot', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.terms_hint', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.pages_nav', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.submit_step.summary_title', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.submit_step.guarantor_pending_hint', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.success.celebration_eyebrow', [], $locale));
            $this->assertNotSame(
                'borrower.apply.review_step.page_overview',
                __('borrower.apply.review_step.page_overview', [], $locale),
            );
        }
    }
}
