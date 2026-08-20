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
    $coverageFormId = $coverageApplication
        ? 'kf-partner-coverage-'.$coverageApplication->id.'-'.str_replace('_', '-', $coverageCategory)
        : null;
    $coverageReviewUrl = $coverageApplication
        ? route('admin.partners.coverage-request', $coverageApplication).'?'.http_build_query(array_filter([
            'category' => $coverageCategory,
            'ask' => $openCoverage ? null : 1,
        ]))
        : null;
    if ($openCoverage) {
        $coverButtonLabel = 'Open valuer coverage';
        if ($coverageCategory !== 'valuer') {
            $coverButtonLabel = 'Open coverage request';
        }
        if (filled($coverageRegion)) {
            $coverButtonLabel .= ' · '.$coverageRegion;
        }
    } elseif ($canEnrollPartners) {
        $coverButtonLabel = $coverageCategory === 'valuer' ? 'Open valuer coverage' : 'Open partner coverage';
        if (filled($coverageRegion)) {
            $coverButtonLabel .= ' · '.$coverageRegion;
        }
    } else {
        $coverButtonLabel = 'Ask Partners team to add a '.$categoryLabel;
    }
@endphp

@if ($coverageApplication)
    @if ($canEnrollPartners)
        <a href="{{ $coverageReviewUrl }}" class="{{ $enrollClass }}">{{ $coverButtonLabel }}</a>
        <p class="text-xs text-gray-600">
            Opens existing {{ $categoryLabel }}s first. New partners are enrolled from that page, not from screening.
        </p>
    @elseif ($openCoverage)
        <p class="text-sm text-emerald-900">
            Asked Partners Management to add a {{ $categoryLabel }}{{ $regionBit }}.
            They see it under Alerts (bell, top right) and can add the region on an existing partner or enroll a new one. Waiting files auto-match after coverage is in place.
        </p>
    @else
        {{-- form= points at a form pushed outside this page's checklist <form>, so this click cannot save Pass/Fail or jump to Decision. --}}
        <button type="submit" form="{{ $coverageFormId }}" class="{{ $enrollClass }}">
            {{ $coverButtonLabel }}
        </button>
        <p class="text-xs text-gray-600">
            Screening does not enroll partners. Partners Management or an admin will add coverage{{ $regionBit }}. They see the request under Alerts (bell, top right).
        </p>
        @pushOnce('scripts', $coverageFormId)
            <form id="{{ $coverageFormId }}" method="POST" action="{{ route('admin.loan-applications.request-partner-coverage', $coverageApplication) }}" class="hidden">
                @csrf
                <input type="hidden" name="category" value="{{ $coverageCategory }}">
            </form>
        @endpushOnce
    @endif
@endif
