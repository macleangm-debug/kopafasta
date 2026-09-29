/**
 * Shared date-input draft/view rules.
 *
 * openAnchor / default / min / max never become the selected value unless the
 * user explicitly picks a day/year/month (or Confirm after such a pick).
 */

export function clampDate(str, min, max) {
    if (! str) {
        return str;
    }
    if (min && str < min) {
        return min;
    }
    if (max && str > max) {
        return max;
    }

    return str;
}

export function daysInMonth(year, monthIndex) {
    return new Date(year, monthIndex + 1, 0).getDate();
}

export function formatYmd(year, monthIndex, day) {
    const m = String(monthIndex + 1).padStart(2, '0');
    const d = String(day).padStart(2, '0');

    return `${year}-${m}-${d}`;
}

export function parseYmd(str) {
    const [y, m, d] = String(str || '').split('-').map(Number);
    if (! y || ! m || ! d) {
        return null;
    }

    return { year: y, monthIndex: m - 1, day: d };
}

/**
 * Calendar open position only — never written to value on its own.
 */
export function openAnchorDate({ fallback, min, max, today }) {
    const base = fallback || today;
    return clampDate(base, min || '', max || '');
}

/**
 * Blank fields keep draft empty. Existing values keep their own draft.
 * View uses openAnchor only for navigation when blank.
 */
export function prepareOpenState({ value, fallback, min, max, today }) {
    const anchor = openAnchorDate({ fallback, min, max, today });
    const selected = value ? clampDate(value, min || '', max || '') : '';
    const viewSource = selected || anchor;
    const parsed = parseYmd(viewSource) || parseYmd(today);

    return {
        draft: selected,
        viewYear: parsed.year,
        viewMonth: parsed.monthIndex,
    };
}

/**
 * Sync draft to the calendar view year/month after the user picks year or month.
 * Day is preserved from draft → value → openAnchor when possible.
 */
export function draftFromView({
    viewYear,
    viewMonth,
    draft,
    value,
    fallback,
    min,
    max,
    today,
}) {
    const anchor = openAnchorDate({ fallback, min, max, today });
    const base = draft || value || anchor;
    const parsed = parseYmd(base);
    let day = parsed?.day || 1;
    day = Math.min(day, daysInMonth(viewYear, viewMonth));

    return clampDate(formatYmd(viewYear, viewMonth, day), min || '', max || '');
}

export function confirmDraft(draft, min, max) {
    if (! draft) {
        return null;
    }

    return clampDate(draft, min || '', max || '');
}

/**
 * Regression helper — pick year then confirm must keep that year.
 */
export function selectYearAndConfirm({
    year,
    monthIndex = 0,
    day = 15,
    value = '',
    fallback,
    min,
    max,
    today,
}) {
    const open = prepareOpenState({ value, fallback, min, max, today });
    const draft = draftFromView({
        viewYear: year,
        viewMonth: monthIndex ?? open.viewMonth,
        draft: open.draft,
        value,
        fallback,
        min,
        max,
        today,
    });
    // Prefer explicit day when provided and in-range.
    const withDay = clampDate(
        formatYmd(year, monthIndex, Math.min(day, daysInMonth(year, monthIndex))),
        min || '',
        max || '',
    );

    return confirmDraft(withDay || draft, min, max);
}
