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
        @if ($record->assignedAnalyst)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-800 ring-1 ring-sky-200">
                Analyst: {{ $record->assignedAnalyst->name }}
            </span>
        @endif
        @if ($record->status === 'pending_documents')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 ring-1 ring-sky-200">
                Awaiting documents
            </span>
        @elseif ($record->status === 'awaiting_offer' || $record->offer_status === 'pending_borrower')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-900 ring-1 ring-amber-200">
                Awaiting borrower on offer
            </span>
        @elseif (app(\App\Services\ApplicationOfferService::class)->offerDeclinedByBorrower($record))
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 ring-1 ring-red-200">
                Offer declined by borrower
            </span>
        @endif
        <a href="{{ route('admin.loan-applications.edit', $record) }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-white bg-gray-900 hover:bg-gray-800 px-3 py-1.5 rounded-lg">
            Edit application
        </a>
    </div>

    <form method="POST" action="{{ route('admin.loan-applications.assign-analyst', $record) }}"
          class="mb-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 px-4 py-3 flex flex-col sm:flex-row sm:items-end gap-3">
        @csrf
        <div class="flex-1 min-w-0">
            <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Assign credit analyst</label>
            <select name="assigned_analyst_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Unassigned</option>
                @foreach ($assignableAnalysts ?? [] as $analyst)
                    <option value="{{ $analyst->id }}" @selected((int) $record->assigned_analyst_id === (int) $analyst->id)>
                        {{ $analyst->name }} ({{ display_label($analyst->role, 'role') }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm shrink-0">
            Save assignment
        </button>
        <a href="{{ route('admin.credit-team.index') }}" class="inline-flex justify-center text-xs font-semibold text-amber-700 hover:underline self-center shrink-0">
            Manage team →
        </a>
    </form>

    @include('admin.loan-applications.review._header')

    @include('admin.loan-applications.review._affordability-summary')

    <div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'borrower' }" class="space-y-4"
         @set-review-tab.window="tab = $event.detail">
        @include('admin.loan-applications.review._recommendation')

        <nav class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2" aria-label="Review sections">
            @php
                $reviewTabs = [
                    ['borrower', 'Borrower'],
                    ['documents', 'Documents'],
                    ['crb', 'CRB'],
                    ['guarantor', 'Guarantor'],
                ];
                if ($groupReview ?? null) {
                    $reviewTabs[] = ['group', 'Group loan'];
                }
                $reviewTabs[] = ['decision', 'Decision'];
            @endphp
            @foreach ($reviewTabs as [$key, $label])
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
            @include('admin.loan-applications._asset-backed')
            @include('admin.loan-applications._asset-lending')
        </div>

        <div x-show="tab === 'crb'" x-cloak class="space-y-6">
            @include('admin.loan-applications.review._crb')
        </div>

        <div x-show="tab === 'guarantor'" x-cloak class="space-y-6">
            @include('admin.loan-applications.review._guarantors')
        </div>

        @if ($groupReview ?? null)
            <div x-show="tab === 'group'" x-cloak class="space-y-6">
                @include('admin.loan-applications.review._group')
            </div>
        @endif

        <div id="decision-panel" x-show="tab === 'decision'" x-cloak class="space-y-6 scroll-mt-24">
            @include('admin.loan-applications._workflow')
            @include('admin.loan-applications._loan-link')
            @include('admin.loan-applications.review._contract')
        </div>
    </div>

</x-admin.layout>
