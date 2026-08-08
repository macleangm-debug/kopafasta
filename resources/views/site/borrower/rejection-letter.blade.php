<x-site.borrower-layout :title="__('borrower.rejection_letter.notify_title')">
    <div class="max-w-3xl mx-auto space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ brand('legal_name') }}</p>
                <h1 class="text-2xl font-extrabold text-gray-900 mt-1">{{ __('borrower.rejection_letter.notify_title') }}</h1>
                <p class="text-sm text-gray-600 mt-1">{{ $application->application_number }} · {{ $agreement->reference }}</p>
            </div>
            <a href="{{ route('site.borrower.application', $application) }}" class="text-sm font-semibold text-brand hover:underline">
                ← {{ __('borrower.loan_profile.back_to_application') }}
            </a>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-800">{{ __('borrower.rejection_letter.pdf.pill') }}</p>
                <a href="{{ route('site.borrower.application.rejection-letter.download', $application) }}"
                   class="inline-flex font-extrabold px-4 py-2 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand">
                    {{ __('borrower.rejection_letter.preview') }}
                </a>
            </div>
            <iframe src="{{ route('site.borrower.application.rejection-letter.download', $application) }}"
                    class="w-full min-h-[70vh] bg-gray-50" title="{{ __('borrower.rejection_letter.notify_title') }}"></iframe>
        </div>
    </div>
</x-site.borrower-layout>
