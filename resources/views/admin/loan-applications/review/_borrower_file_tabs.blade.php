@php
    $defaultTab = request('tab', 'personal');
    $allowedTabs = ['personal', 'face', 'residence', 'activity', 'documents', 'guarantor'];
    if ($groupReview ?? null) {
        $allowedTabs[] = 'group';
    }
    if (! in_array($defaultTab, $allowedTabs, true)) {
        $defaultTab = 'personal';
    }
    $profileTabs = [
        ['personal', 'Personal'],
        ['face', 'Face'],
        ['residence', 'Residence'],
        ['activity', 'Activity'],
        ['documents', 'Documents'],
        ['guarantor', 'Guarantor'],
    ];
    if ($groupReview ?? null) {
        $profileTabs[] = ['group', 'Group'];
    }
    $guarantorCount = is_countable($review['guarantors'] ?? null)
        ? count($review['guarantors'])
        : 0;
    if ($guarantorCount === 0 && isset($record) && method_exists($record, 'customerGuarantors')) {
        $guarantorCount = $record->relationLoaded('customerGuarantors')
            ? $record->customerGuarantors->count()
            : $record->customerGuarantors()->count();
    }
    $openDocRequestCount = 0;
    if (isset($groupedDocumentRequests) && is_array($groupedDocumentRequests)) {
        $openDocRequestCount = collect($groupedDocumentRequests['pending'] ?? [])->count()
            + collect($groupedDocumentRequests['uploaded'] ?? [])->count();
    }
    $tabUrl = function (string $key) use ($record) {
        return route('admin.loan-applications.show', [
            'loan_application' => $record,
            'tab' => $key,
        ]).'#borrower-file';
    };
@endphp

{{-- Server-rendered tabs (no Alpine/x-cloak) so panels always show content --}}
<section id="borrower-file" class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden scroll-mt-24">
    <div class="px-5 pt-5 pb-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Borrower file</p>
        <h3 class="text-base font-bold text-gray-900 mt-0.5">Profile sections</h3>
        <p class="text-xs text-gray-500 mt-0.5">
            Open one section at a time.
            @if ($guarantorCount > 0)
                Guarantor on file — open the Guarantor tab to review exposure.
            @endif
        </p>
    </div>

    <div class="px-3 pt-3 flex gap-1.5 overflow-x-auto border-b border-gray-100" role="tablist">
        @foreach ($profileTabs as [$key, $label])
            <a href="{{ $tabUrl($key) }}"
               role="tab"
               aria-selected="{{ $defaultTab === $key ? 'true' : 'false' }}"
               @class([
                   'shrink-0 rounded-xl px-4 py-2.5 text-xs font-semibold transition inline-flex items-center gap-1.5',
                   'bg-brand text-white shadow-sm' => $defaultTab === $key,
                   'bg-transparent text-gray-600 hover:bg-brand-muted/50' => $defaultTab !== $key,
               ])>
                {{ $label }}
                @if ($key === 'guarantor' && $guarantorCount > 0)
                    <span @class([
                        'inline-flex min-w-[1.25rem] justify-center rounded-full text-[10px] font-bold px-1.5 py-0.5',
                        'bg-white/20 text-white' => $defaultTab === $key,
                        'bg-brand-gold/90 text-brand' => $defaultTab !== $key,
                    ])>{{ $guarantorCount }}</span>
                @elseif ($key === 'documents' && $openDocRequestCount > 0)
                    <span @class([
                        'inline-flex min-w-[1.25rem] justify-center rounded-full text-[10px] font-bold px-1.5 py-0.5',
                        'bg-white/20 text-white' => $defaultTab === $key,
                        'bg-amber-100 text-amber-900' => $defaultTab !== $key,
                    ])>{{ $openDocRequestCount }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="p-5">
        @if ($defaultTab === 'personal')
            <div class="space-y-5">
                @include('admin.loan-applications.review._profile_personal')
            </div>
        @elseif ($defaultTab === 'face')
            @include('admin.loan-applications.review._verification')
        @elseif ($defaultTab === 'residence')
            @include('admin.loan-applications.review._profile_residence')
        @elseif ($defaultTab === 'activity')
            @include('admin.loan-applications.review._profile_activity')
        @elseif ($defaultTab === 'documents')
            <div class="space-y-5">
                @include('admin.loan-applications.review._document-requests')
                @include('admin.loan-applications.review._documents')
                @include('admin.loan-applications._asset-backed')
                @include('admin.loan-applications._asset-lending')
                @include('admin.loan-applications.review._asset')
            </div>
        @elseif ($defaultTab === 'guarantor')
            @include('admin.loan-applications.review._guarantors')
        @elseif ($defaultTab === 'group' && ($groupReview ?? null))
            @include('admin.loan-applications.review._group')
        @endif
    </div>
</section>
