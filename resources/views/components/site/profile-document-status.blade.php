@props([
    'document' => null,
    'label' => '',
])

@if ($document)
    <div {{ $attributes->merge(['class' => 'mb-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2 text-xs text-emerald-900']) }}>
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <p class="font-semibold">✓ {{ $label ?: __('borrower.profile.document_uploaded') }}</p>
                <p class="text-emerald-800 mt-0.5">
                    {{ app(\App\Services\ProfileDocumentService::class)->statusLabel($document) }}
                    · {{ __('borrower.profile.uploaded_at', ['time' => $document->created_at?->diffForHumans() ?? '—']) }}
                </p>
            </div>
            @if ($document->file_path)
                <a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="font-semibold text-emerald-700 hover:underline shrink-0">
                    {{ __('borrower.profile.view_document') }}
                </a>
            @endif
        </div>
    </div>
@endif
