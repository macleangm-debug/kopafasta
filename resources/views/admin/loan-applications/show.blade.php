<x-admin.layout
    :title="$record->application_number"
    :heading="$record->application_number"
    :subheading="trim($review['customer']->full_name.' · '.($review['product']?->name ?? ''))"
    :backUrl="route('admin.loan-applications.index')"
    backLabel="Back to applications">

    <div class="flex flex-wrap items-center gap-2 -mt-2 mb-4">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
            {{ display_label($record->status, 'application_status') }}
        </span>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 ring-1 ring-amber-200">
            {{ $workflow->stageLabel($record->current_stage ?? 'submitted') }}
        </span>
        @if ($record->status === 'pending_documents')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 ring-1 ring-sky-200">
                Awaiting documents
            </span>
        @elseif ($record->status === 'awaiting_offer' || $record->offer_status === 'pending_borrower')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-900 ring-1 ring-amber-200">
                Awaiting borrower on offer
            </span>
        @endif
        <a href="{{ route('admin.loan-applications.edit', $record) }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-white bg-gray-900 hover:bg-gray-800 px-3 py-1.5 rounded-lg">
            Edit application
        </a>
    </div>

    @include('admin.loan-applications.review._header')

    @include('admin.loan-applications.review._affordability-summary')

    @include('admin.loan-applications.review._recommendation')

    <div x-data="{ tab: 'borrower' }" class="space-y-4">
        <nav class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2" aria-label="Review sections">
            @foreach ([
                ['borrower', 'Borrower'],
                ['documents', 'Documents'],
                ['crb', 'CRB'],
                ['guarantor', 'Guarantor'],
                ['decision', 'Decision'],
            ] as [$key, $label])
                <button type="button"
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-gray-900 text-white ring-gray-900' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                        class="rounded-xl px-3 py-2.5 text-xs font-semibold ring-1 transition text-left">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <div x-show="tab === 'borrower'" x-cloak class="space-y-6">
            @include('admin.loan-applications.review._borrower')
            @include('admin.loan-applications.review._verification')
        </div>

        <div x-show="tab === 'documents'" x-cloak class="space-y-6">
            @include('admin.loan-applications.review._documents')
            @include('admin.loan-applications.review._document-requests')
            @include('admin.loan-applications.review._asset')
        </div>

        <div x-show="tab === 'crb'" x-cloak class="space-y-6">
            @include('admin.loan-applications.review._crb')
        </div>

        <div x-show="tab === 'guarantor'" x-cloak class="space-y-6">
            @include('admin.loan-applications.review._guarantors')
        </div>

        <div x-show="tab === 'decision'" x-cloak class="space-y-6">
            @include('admin.loan-applications._workflow')
            @include('admin.loan-applications._loan-link')
            @include('admin.loan-applications.review._contract')
        </div>
    </div>

</x-admin.layout>
