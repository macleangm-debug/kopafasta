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
            $this->assertNotEmpty(__('borrower.apply.review_step.page_schedule', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.deal_snapshot', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.review_step.pages_nav', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.quote.change_purpose', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.submit_step.group_signatures_title', [], $locale));
            $this->assertNotEmpty(__('borrower.apply.submit_step.guarantor_pending_hint', [], $locale));
            $this->assertSame(
                $locale === 'en' ? 'Complete profile before submission' : 'Kamilisha wasifu kabla ya kuwasilisha',
                __('borrower.apply.complete_profile_to_submit', [], $locale),
            );
            $this->assertNotEmpty(__('borrower.apply.success.celebration_eyebrow', [], $locale));
            $this->assertNotSame(
                'borrower.apply.review_step.page_overview',
                __('borrower.apply.review_step.page_overview', [], $locale),
            );
        }
    }

    public function test_review_overview_uses_single_premium_card_and_installment_helper(): void
    {
        $review = file_get_contents(resource_path('views/site/apply/_review-step.blade.php'));
        $js = file_get_contents(resource_path('js/apply-wizard.js'));
        $sheet = file_get_contents(resource_path('views/components/site/sheet-select.blade.php'));
        $quote = file_get_contents(resource_path('views/site/apply/_quote-step.blade.php'));
        $submit = file_get_contents(resource_path('views/site/apply/_submit-step.blade.php'));
        $footer = file_get_contents(resource_path('views/components/site/wizard-footer.blade.php'));
        $pad = file_get_contents(resource_path('views/components/site/signature-pad.blade.php'));
        $payment = file_get_contents(resource_path('views/site/borrower/profile/payment.blade.php'));

        $this->assertNotFalse($review);
        $this->assertNotFalse($js);
        $this->assertNotFalse($sheet);
        $this->assertNotFalse($quote);
        $this->assertNotFalse($submit);
        $this->assertNotFalse($footer);
        $this->assertNotFalse($pad);
        $this->assertNotFalse($payment);

        $this->assertStringContainsString('displayInstallmentAmount()', $review);
        $this->assertStringNotContainsString('reviewSummary.installment_amount ?? quote.primary', $review);
        $this->assertStringNotContainsString('borrower_section', $review);
        $this->assertStringNotContainsString('loan_section', $review);
        $this->assertStringContainsString('guarantor_section', $review);
        $this->assertStringContainsString('reviewPage === 2', $review);
        $this->assertStringNotContainsString('reviewPage === 3', $review);
        $this->assertStringContainsString('reviewPageCount: 2', $js);
        $this->assertStringContainsString('displayInstallmentAmount()', $js);
        $this->assertStringContainsString('purposeEditing', $js);
        $this->assertStringContainsString('setLoanPurpose', $js);
        $this->assertStringContainsString('syncPurposeHidden', $js);
        $this->assertStringContainsString('setter="setLoanPurpose"', $quote);
        $this->assertStringContainsString('choose(val)', $sheet);
        $this->assertStringContainsString('optionEntries', $sheet);
        $this->assertStringContainsString('change_purpose', $quote);
        $this->assertStringContainsString('purposeEditing', $quote);
        $this->assertStringContainsString('x-show="!form.purpose || purposeEditing"', $quote);
        $this->assertStringNotContainsString('template x-if="!form.purpose || purposeEditing"', $quote);
        $this->assertStringContainsString('guarantor_hold_title', $submit);
        $this->assertStringContainsString('resigningOnSubmit', $submit);
        $this->assertStringContainsString('group_signatures_title', $submit);
        $this->assertStringNotContainsString('signed_hint_short', $submit);
        $this->assertStringNotContainsString("submit_step.reference", $submit);
        $this->assertStringContainsString('hide-clear', $submit);
        $this->assertStringContainsString("'hideClear'", $pad);
        $this->assertStringContainsString("stepKey !== 'submit'", $footer);
        $this->assertStringContainsString('complete_profile_to_submit', $footer);
        $this->assertStringContainsString('showCompleteTick', $payment);
        $this->assertStringContainsString('add_details', $payment);
        $this->assertStringContainsString('showCompleteTick', file_get_contents(resource_path('views/components/site/profile-section-card.blade.php')));
        $card = file_get_contents(resource_path('views/components/site/profile-section-card.blade.php'));
        $this->assertStringContainsString("'stale'", $card);
        $this->assertStringContainsString('section_needs_update', $card);
        $activity = file_get_contents(resource_path('views/site/borrower/profile/activity.blade.php'));
        $this->assertStringContainsString(':stale="$activityStale"', $activity);
        $this->assertStringContainsString(':complete="$activityComplete"', $activity);
        $this->assertStringNotContainsString('summary_title', $submit);
        $this->assertStringNotContainsString('edit_quote', $submit);
        $this->assertStringNotContainsString('view_guarantor', $submit);
        $this->assertStringContainsString('resigningOnSubmit: false', $js);
        $this->assertStringContainsString('startResignOnSubmit()', $js);
    }
}
