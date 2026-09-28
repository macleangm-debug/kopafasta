<x-site.borrower-layout :title="__('borrower.rejection_letter.notify_title')">
    <div class="max-w-3xl mx-auto space-y-5">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ brand('legal_name') }}</p>
            <h1 class="text-2xl font-extrabold text-gray-900 mt-1">{{ __('borrower.rejection_letter.notify_title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ $application->application_number }} · {{ $agreement->reference }}</p>
        </div>

        <x-admin.document-holder
            :expanded="true"
            :fit-page="true"
            :items="[[
                'key' => 'rejection',
                'label' => __('borrower.rejection_letter.pdf.pill'),
                'eyebrow' => __('borrower.rejection_letter.pdf.pill'),
                'reference' => $agreement->reference,
                'url' => route('site.borrower.application.rejection-letter.download', $application),
                'preview_label' => __('borrower.rejection_letter.preview'),
                'use_admin_preview' => false,
                'kind' => 'pdf',
            ]]"
        />
    </div>
</x-site.borrower-layout>
