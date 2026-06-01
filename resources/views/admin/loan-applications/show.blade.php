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
        <a href="{{ route('admin.loan-applications.edit', $record) }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-white bg-gray-900 hover:bg-gray-800 px-3 py-1.5 rounded-lg">
            Edit application
        </a>
    </div>

    @include('admin.loan-applications.review._header')

    @include('admin.loan-applications.review._nav')

    @include('admin.loan-applications._workflow')

    @include('admin.loan-applications._loan-link')

    <div class="space-y-6">
        @include('admin.loan-applications.review._borrower')
        @include('admin.loan-applications.review._verification')
        @include('admin.loan-applications.review._documents')
        @include('admin.loan-applications.review._guarantors')
        @include('admin.loan-applications.review._crb')
        @include('admin.loan-applications.review._contract')
        @include('admin.loan-applications.review._document-requests')
    </div>

</x-admin.layout>
