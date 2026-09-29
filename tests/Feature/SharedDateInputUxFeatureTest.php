<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0: shared date-input must not force users through 1940 when entering DOB.
 */
class SharedDateInputUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_date_input_has_no_global_1940_default(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/date-input.blade.php'));

        $this->assertStringNotContainsString("\$min ?: '1940-01-01'", $blade);
        $this->assertStringNotContainsString('$minDate = $min ?: \'1940-01-01\'', $blade);
        // Blank open must not prefer min over fallback/default.
        $this->assertStringNotContainsString('this.value || this.min || this.fallback', $blade);
        $this->assertStringContainsString('openAnchor()', $blade);
        $this->assertStringContainsString('maxY - 120', $blade);
    }

    public function test_dob_surfaces_do_not_pass_1940_min_to_picker(): void
    {
        $affiliate = file_get_contents(resource_path('views/site/affiliate/apply.blade.php'));
        $personal = file_get_contents(resource_path('views/site/borrower/profile/personal.blade.php'));
        $forgot = file_get_contents(resource_path('views/site/auth/forgot-pin.blade.php'));

        foreach ([$affiliate, $personal, $forgot] as $blade) {
            $this->assertStringNotContainsString('1940-01-01', $blade);
            $this->assertStringContainsString('subYears(18)', $blade);
        }
    }

    public function test_affiliate_apply_renders_blank_dob_without_1940_value(): void
    {
        $html = $this->get(route('site.affiliate.apply'))->assertOk()->getContent();

        $this->assertStringNotContainsString('value="1940-01-01"', $html);
        $this->assertStringNotContainsString("value: '1940-01-01'", $html);
        $this->assertStringNotContainsString('value: "1940-01-01"', $html);
        $this->assertStringNotContainsString("min: '1940-01-01'", $html);
        $this->assertStringNotContainsString('min: "1940-01-01"', $html);
        // Alpine seed: blank value, open-anchor default ~25 years (not 1940).
        $this->assertMatchesRegularExpression("/value:\\s*''/", $html);
        $this->assertDoesNotMatchRegularExpression("/min:\\s*'1940/", $html);
        $yearsAgo25 = now()->subYears(25)->format('Y-m-d');
        $this->assertStringContainsString($yearsAgo25, $html);
    }

    public function test_server_dob_age_floor_still_documented_for_profile_validation(): void
    {
        // Validation floor remains a server boundary — must not drive the picker UI.
        $service = file_get_contents(app_path('Services/ProfileValidationService.php'));
        $this->assertStringContainsString('1940-01-01', $service);
        $this->assertGreaterThanOrEqual(18, (new \App\Services\ProfileValidationService)->minAge());
    }
}
