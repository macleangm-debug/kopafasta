<x-site.borrower-layout :title="__('borrower.rejection_letter.notify_title')">
    <div class="max-w-3xl mx-auto space-y-5">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ brand('legal_name') }}</p>
            <h1 class="text-2xl font-extrabold text-gray-900 mt-1">{{ __('borrower.rejection_letter.notify_title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ $application->application_number }}</p>
        </div>

        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">{{ __('borrower.rejection_letter.pending_title') }}</p>
            <p class="mt-1 text-amber-900/80">{{ __('borrower.rejection_letter.pending_body') }}</p>
        </div>
    </div>
</x-site.borrower-layout>
