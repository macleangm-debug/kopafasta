@php
    $coverageApplication = $coverageApplication ?? $record ?? $application ?? null;
    $coverageCategory = $coverageCategory ?? 'valuer';
    $coverageRegion = $coverageRegion ?? ($coverageApplication?->customer?->region ?? null);
    $enrollClass = $enrollClass ?? 'inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl';
    $canEnrollPartners = auth()->user()?->can('create', \App\Models\Vendor::class);
    $openCoverage = $coverageApplication
        ? app(\App\Services\PartnerCoverageRequestService::class)->openRequest($coverageApplication, $coverageCategory)
        : null;
    $categoryLabel = app(\App\Services\PartnerCoverageRequestService::class)->categoryLabel($coverageCategory);
    $regionBit = filled($coverageRegion) ? ' covering '.$coverageRegion : '';
    if ($openCoverage) {
        $coverButtonLabel = 'Review coverage request';
    } elseif ($canEnrollPartners) {
        $coverButtonLabel = filled($coverageRegion)
            ? 'Review '.$categoryLabel.'s for '.$coverageRegion
            : 'Review existing '.$categoryLabel.'s';
    } else {
        $coverButtonLabel = 'Ask Partners team to add a '.$categoryLabel;
    }
@endphp

@if ($coverageApplication)
    @if ($canEnrollPartners)
        @if ($openCoverage)
            <a href="{{ route('admin.partners.coverage-request', $coverageApplication) }}" class="{{ $enrollClass }}">{{ $coverButtonLabel }}</a>
        @else
            <form method="POST" action="{{ route('admin.loan-applications.request-partner-coverage', $coverageApplication) }}" class="space-y-1.5">
                @csrf
                <input type="hidden" name="category" value="{{ $coverageCategory }}">
                <button type="submit" class="{{ $enrollClass }}">{{ $coverButtonLabel }}</button>
                <p class="text-xs text-gray-600">
                    Screening does not enroll partners from this file. This opens existing {{ $categoryLabel }}s first and posts the request under Alerts (bell, top right).
                </p>
            </form>
        @endif
    @elseif ($openCoverage)
        <p class="text-sm text-emerald-900">
            Asked Partners Management to add a {{ $categoryLabel }}{{ $regionBit }}.
            They see it under Alerts (bell, top right) and can add the region on an existing partner or enroll a new one. Waiting files auto-match after coverage is in place.
        </p>
    @else
        <form method="POST" action="{{ route('admin.loan-applications.request-partner-coverage', $coverageApplication) }}" class="space-y-1.5">
            @csrf
            <input type="hidden" name="category" value="{{ $coverageCategory }}">
            <button type="submit" class="{{ $enrollClass }}">{{ $coverButtonLabel }}</button>
            <p class="text-xs text-gray-600">
                Screening does not enroll partners. Partners Management or an admin will add coverage{{ $regionBit }}. They see the request under Alerts (bell, top right).
            </p>
        </form>
    @endif
@endif
