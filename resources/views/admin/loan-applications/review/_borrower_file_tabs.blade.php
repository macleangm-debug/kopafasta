@php
    $defaultTab = $defaultTab ?? request('tab', 'personal');
    $profileTabs = [
        ['personal', 'Personal'],
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
@endphp

{{-- ── Profile file tabs ─────────────────────────────────────────── --}}
<section
    class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
    x-data="{ tab: @js($defaultTab) }"
>
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
            <button type="button"
                    role="tab"
                    @click="tab = '{{ $key }}'"
                    :aria-selected="tab === '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'bg-brand text-white shadow-sm'
                        : 'bg-transparent text-gray-600 hover:bg-brand-muted/50'"
                    class="shrink-0 rounded-xl px-4 py-2.5 text-xs font-semibold transition inline-flex items-center gap-1.5">
                {{ $label }}
                @if ($key === 'guarantor' && $guarantorCount > 0)
                    <span class="inline-flex min-w-[1.25rem] justify-center rounded-full bg-brand-gold/90 text-brand text-[10px] font-bold px-1.5 py-0.5"
                          :class="tab === 'guarantor' ? 'bg-white/20 text-white' : ''">{{ $guarantorCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="p-5">
        <div x-show="tab === 'personal'" x-cloak class="space-y-5">
            @include('admin.loan-applications.review._profile_personal')
        </div>
        <div x-show="tab === 'residence'" x-cloak>
            @include('admin.loan-applications.review._profile_residence')
        </div>
        <div x-show="tab === 'activity'" x-cloak>
            @include('admin.loan-applications.review._profile_activity')
        </div>
        <div x-show="tab === 'documents'" x-cloak class="space-y-5">
            @include('admin.loan-applications.review._documents')
            @include('admin.loan-applications.review._document-requests')
            @include('admin.loan-applications._asset-backed')
            @include('admin.loan-applications._asset-lending')
            @include('admin.loan-applications.review._asset')
        </div>
        <div x-show="tab === 'guarantor'" x-cloak>
            @include('admin.loan-applications.review._guarantors')
        </div>
        @if ($groupReview ?? null)
            <div x-show="tab === 'group'" x-cloak>
                @include('admin.loan-applications.review._group')
            </div>
        @endif
    </div>
</section>
