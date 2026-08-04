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
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($docs as $doc)
                    <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50/50">
                        <div class="p-4 flex items-start gap-3">
                            @if ($doc->file_path)
                                <x-admin.document-preview
                                    :url="asset('storage/'.$doc->file_path)"
                                    label="View"
                                    variant="thumbnail" />
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-gray-900">{{ $doc->documentType?->name ?? 'Document' }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            {{ display_label($doc->status, 'document_status') ?: ucfirst((string) $doc->status) }}
                                            @if ($doc->created_at)
                                                · {{ $doc->created_at->format('d M Y') }}
                                            @endif
                                        </p>
                                    </div>
                                    <x-admin.badge :value="$doc->status ?? 'pending'" group="document_status"
                                        :map="[
                                            'verified' => 'bg-emerald-100 text-emerald-800',
                                            'approved' => 'bg-emerald-100 text-emerald-800',
                                            'pending_review' => 'bg-amber-100 text-amber-800',
                                            'pending' => 'bg-amber-100 text-amber-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                        ]" />
                                </div>
                                @if ($doc->file_path)
                                    <div class="mt-3">
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$doc->file_path)"
                                            label="Open" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
