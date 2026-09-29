<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0: user-selected DOB year must never be replaced by openAnchor/default/max.
 */
class SharedDateInputUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_date_input_uses_alpine_factory_not_inline_year_bug(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/date-input.blade.php'));
        $state = file_get_contents(resource_path('js/date-input-state.js'));
        $draft = file_get_contents(resource_path('js/date-input-draft.js'));
        $init = file_get_contents(resource_path('js/alpine-init.js'));

        $this->assertStringContainsString('kfDateInput(', $blade);
        $this->assertStringContainsString("registerDateInput", $init);
        $this->assertStringContainsString('syncDraftFromView', $state);
        $this->assertStringContainsString('pickYear(y)', $state);
        // Year pick must update draft — not only viewYear.
        $this->assertStringContainsString('this.syncDraftFromView()', $state);
        // Blank open must not seed draft from openAnchor.
        $this->assertStringContainsString('draft: selected', $draft);
        $this->assertStringContainsString('confirmDraft', $state);
        $this->assertStringNotContainsString("\$min ?: '1940-01-01'", $blade);
        $this->assertStringNotContainsString('this.value || this.min || this.fallback', $blade.$state);
    }

    public function test_dob_surfaces_do_not_pass_1940_min_to_picker(): void
    {
        foreach ([
            resource_path('views/site/affiliate/apply.blade.php'),
            resource_path('views/site/borrower/profile/personal.blade.php'),
            resource_path('views/site/auth/forgot-pin.blade.php'),
        ] as $path) {
            $blade = file_get_contents($path);
            $this->assertStringNotContainsString('1940-01-01', $blade);
            $this->assertStringContainsString('subYears(18)', $blade);
        }
    }

    public function test_year_selection_never_replaced_by_open_anchor_or_max(): void
    {
        $script = <<<'JS'
import {
  prepareOpenState,
  draftFromView,
  confirmDraft,
  selectYearAndConfirm,
} from './date-input-draft.js';

const today = '2026-09-29';
const max = '2008-09-29'; // today - 18y
const fallback = '2001-09-29'; // ~25y default — must NEVER replace user year
const min = '';

const years = [1980, 1989, 1995, 2001, 2007, 2008];
const failures = [];

for (const year of years) {
  // Blank → pick year → confirm
  const open = prepareOpenState({ value: '', fallback, min, max, today });
  if (open.draft !== '') failures.push(`blank open seeded draft=${open.draft}`);

  const afterYear = draftFromView({
    viewYear: year,
    viewMonth: 5,
    draft: open.draft,
    value: '',
    fallback,
    min,
    max,
    today,
  });
  const y1 = Number(String(afterYear).slice(0, 4));
  if (y1 !== year) failures.push(`pick ${year} produced ${afterYear}`);

  const committed = confirmDraft(afterYear, min, max);
  if (!committed || Number(committed.slice(0, 4)) !== year) {
    failures.push(`confirm ${year} → ${committed}`);
  }
  if (year !== 2001 && committed === fallback) {
    failures.push(`${year} collapsed to fallback ${committed}`);
  }
  if (year !== 2008 && committed?.startsWith('2008')) {
    failures.push(`${year} collapsed to max boundary ${committed}`);
  }

  // Reopen must preserve
  const reopen = prepareOpenState({ value: committed, fallback, min, max, today });
  if (reopen.draft !== committed) failures.push(`reopen lost ${committed} got ${reopen.draft}`);

  // Also via selectYearAndConfirm helper
  const via = selectYearAndConfirm({
    year, monthIndex: 5, day: 15, value: '', fallback, min, max, today,
  });
  if (!via || Number(via.slice(0, 4)) !== year) failures.push(`helper ${year} → ${via}`);
}

// 2008 → 1989 must stick
let cur = selectYearAndConfirm({
  year: 2008, monthIndex: 8, day: 29, value: '', fallback, min, max, today,
});
cur = selectYearAndConfirm({
  year: 1989, monthIndex: 2, day: 10, value: cur, fallback, min, max, today,
});
if (!cur?.startsWith('1989')) failures.push(`2008→1989 got ${cur}`);

// 1989 → 1995
cur = selectYearAndConfirm({
  year: 1995, monthIndex: 0, day: 1, value: cur, fallback, min, max, today,
});
if (!cur?.startsWith('1995')) failures.push(`1989→1995 got ${cur}`);

// Confirm with empty draft must not invent a date
if (confirmDraft('', min, max) !== null) failures.push('empty confirm should be null');
if (confirmDraft(null, min, max) !== null) failures.push('null confirm should be null');

if (failures.length) {
  console.error(JSON.stringify(failures, null, 2));
  process.exit(1);
}
console.log('ok');
JS;

        $tmp = base_path('resources/js/_test-date-input-draft.mjs');
        file_put_contents($tmp, $script);
        $cmd = 'cd '.escapeshellarg(base_path('resources/js')).' && node '.escapeshellarg($tmp).' 2>&1';
        exec($cmd, $out, $code);
        @unlink($tmp);

        $this->assertSame(0, $code, implode("\n", $out));
        $this->assertStringContainsString('ok', implode("\n", $out));
    }

    public function test_affiliate_apply_renders_blank_dob_without_anchor_as_value(): void
    {
        $html = $this->get(route('site.affiliate.apply'))->assertOk()->getContent();

        $this->assertStringNotContainsString('value="1940-01-01"', $html);
        $this->assertStringNotContainsString("min: '1940-01-01'", $html);
        $this->assertStringContainsString('kfDateInput(', $html);
        $yearsAgo25 = now()->subYears(25)->format('Y-m-d');
        // fallback may appear as openAnchor config, but value must start empty
        $this->assertMatchesRegularExpression("/value:\\s*''/", $html);
        $this->assertStringContainsString($yearsAgo25, $html);
    }

    public function test_server_dob_age_floor_still_documented_for_profile_validation(): void
    {
        $service = file_get_contents(app_path('Services/ProfileValidationService.php'));
        $this->assertStringContainsString('1940-01-01', $service);
        $this->assertGreaterThanOrEqual(18, (new \App\Services\ProfileValidationService)->minAge());
    }
}
