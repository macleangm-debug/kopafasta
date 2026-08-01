<x-admin.layout
    :title="$record->application_number"
    heading=""
    :backUrl="route('admin.loan-applications.index')"
    backLabel="Back to applications">

    @php
        $customer = $review['customer'];
        $product = $review['product'];
    @endphp

    {{-- Credit file letterhead --}}
    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                        <x-site.brand-mark size="sm" variant="light" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Credit file</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $record->application_number }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $customer->full_name }}
                            @if ($customer->member_no)
                                <span class="text-white/50">·</span> Member {{ $customer->member_no }}
                            @endif
                            @if ($product)
                                <span class="text-white/50">·</span> {{ $product->name }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                        {{ display_label($record->status, 'application_status') }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                        {{ $workflow->stageLabel($record->current_stage ?? 'submitted') }}
                    </span>
                    @if ($record->assignedAnalyst)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                            Analyst: {{ $record->assignedAnalyst->name }}
                        </span>
                    @endif
                    <a href="{{ route('admin.loan-applications.edit', $record) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                        Edit application
                    </a>
                </div>
            </div>
            @if ($record->status === 'pending_documents')
                <p class="mt-3 text-xs font-semibold text-white/85">Awaiting borrower documents</p>
            @elseif ($record->status === 'awaiting_offer' || $record->offer_status === 'pending_borrower')
                <p class="mt-3 text-xs font-semibold text-brand-gold">Awaiting borrower on offer</p>
            @elseif (app(\App\Services\ApplicationOfferService::class)->offerDeclinedByBorrower($record))
                <p class="mt-3 text-xs font-semibold text-rose-200">Offer declined by borrower</p>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.loan-applications.assign-analyst', $record) }}"
          class="mb-4 bg-white rounded-xl shadow-sm ring-1 ring-brand/15 px-4 py-3 flex flex-col sm:flex-row sm:items-end gap-3">
        @csrf
        <div class="flex-1 min-w-0">
            <label class="block text-[10px] uppercase tracking-widest text-brand font-semibold mb-1">Assign credit analyst</label>
            <select name="assigned_analyst_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                <option value="">Unassigned</option>
                @foreach ($assignableAnalysts ?? [] as $analyst)
                    <option value="{{ $analyst->id }}" @selected((int) $record->assigned_analyst_id === (int) $analyst->id)>
                        {{ $analyst->name }} ({{ display_label($analyst->role, 'role') }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex justify-center bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm shrink-0">
            Save assignment
        </button>
        <a href="{{ route('admin.credit-team.index') }}" class="inline-flex justify-center text-xs font-semibold text-brand hover:underline self-center shrink-0">
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
                        :class="tab === '{{ $key }}' ? 'bg-brand-gold text-brand ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40'"
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
