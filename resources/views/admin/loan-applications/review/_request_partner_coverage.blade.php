@php
    $coverageApplication = $coverageApplication ?? $record ?? $application ?? null;
    $coverageCategory = $coverageCategory ?? 'valuer';
    $coverageRegion = $coverageRegion ?? ($coverageApplication?->customer?->region ?? null);
    $enrollLabel = $enrollLabel ?? 'Add partner';
    $enrollClass = $enrollClass ?? 'inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl';
    $canEnrollPartners = auth()->user()?->can('create', \App\Models\Vendor::class);
    $openCoverage = $coverageApplication
        ? app(\App\Services\PartnerCoverageRequestService::class)->openRequest($coverageApplication, $coverageCategory)
        : null;
    $categoryLabel = app(\App\Services\PartnerCoverageRequestService::class)->categoryLabel($coverageCategory);
    $createQuery = array_filter([
        'category' => $coverageCategory === 'any' ? null : $coverageCategory,
        'region' => $coverageRegion,
    ]);
@endphp

@if ($coverageApplication)
    @if ($canEnrollPartners)
        @if ($openCoverage)
            <a href="{{ route('admin.partners.coverage-request', $coverageApplication) }}" class="{{ $enrollClass }}">Review coverage request</a>
        @else
            <a href="{{ route('admin.partners.create', $createQuery) }}" class="{{ $enrollClass }}">{{ $enrollLabel }}</a>
        @endif
    @elseif ($openCoverage)
        <p class="text-sm text-emerald-900">
            Asked Partners Management to add a {{ $categoryLabel }}{{ filled($coverageRegion) ? ' covering '.$coverageRegion : '' }}.
            They can add the region on an existing partner or enroll a new one. Waiting files auto-match after coverage is in place.
        </p>
    @else
        <form method="POST" action="{{ route('admin.loan-applications.request-partner-coverage', $coverageApplication) }}" class="space-y-1.5">
            @csrf
            <input type="hidden" name="category" value="{{ $coverageCategory }}">
            <button type="submit" class="{{ $enrollClass }}">
                Ask Partners team to add a {{ $categoryLabel }}
            </button>
            <p class="text-xs text-gray-600">
                Screening does not enroll partners. Partners Management or an admin will add coverage{{ filled($coverageRegion) ? ' for '.$coverageRegion : '' }}.
            </p>
        </form>
    @endif
@endif
