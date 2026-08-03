@php
    $customer = $review['customer'];
    $docs = collect($review['profile_documents'] ?? $review['kyc_documents'] ?? []);
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Guarantor documents</p>
        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Uploaded documents</h2>
        <p class="text-xs text-gray-500 mt-0.5">Identity and profile documents for this guarantor — shown first so you can review what is already on file.</p>
    </div>
    <div class="p-5">
        @if ($docs->isEmpty())
            <p class="text-sm text-gray-500">No documents on this guarantor’s profile yet.</p>
        @else
            <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                @foreach ($docs as $doc)
                    <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-3">
                            @if ($doc->file_path)
                                <x-admin.document-preview
                                    :url="asset('storage/'.$doc->file_path)"
                                    label="View"
                                    variant="thumbnail" />
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $doc->documentType?->name ?? 'Document' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ display_label($doc->status, 'document_status') ?: ucfirst((string) $doc->status) }}
                                    @if ($doc->created_at)
                                        · {{ $doc->created_at->format('d M Y') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if ($doc->file_path)
                            <x-admin.document-preview
                                :url="asset('storage/'.$doc->file_path)"
                                label="Open" />
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
