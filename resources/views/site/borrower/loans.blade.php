<x-site.borrower-layout :title="brand_title(__('borrower.loans_page.title'))" active="loans">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.loans_page.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.loans_page.subtitle_simplified') }}</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Apply for new loan CTA --}}
    <div class="mb-8 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.loans_page.apply_new_title') }}</p>
            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.loans_page.apply_new_hint') }}</p>
        </div>
        <a href="{{ route('site.borrower.apply') }}" class="inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm shrink-0">
            {{ __('borrower.loans_page.apply_new_cta') }}
        </a>
    </div>

    @include('site.borrower.loans._section-applications', ['rows' => $applicationRows ?? []])

    @include('site.borrower.loans._section-active-loans', ['loans' => $loans ?? collect()])

</x-site.borrower-layout>
