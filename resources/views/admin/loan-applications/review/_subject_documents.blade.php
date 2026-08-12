@php
    $customer = $review['customer'] ?? null;
    $docs = collect($review['profile_documents'] ?? $review['kyc_documents'] ?? []);
    $docsByCategory = $docs
        ->groupBy(fn ($doc) => strtolower((string) ($doc->documentType?->category ?: 'kyc')))
        ->sortKeys();
    $docSubjectLabel = match (true) {
        (bool) ($review['is_member_subject'] ?? false) => 'Member documents',
        (bool) ($review['is_guarantor_subject'] ?? false) => 'Guarantor documents',
        default => 'Subject documents',
    };
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden shadow-sm">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand via-brand-light to-brand text-white">
        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ $docSubjectLabel }}</p>
        <h2 class="text-sm font-semibold text-white mt-0.5">Uploaded documents</h2>
        <p class="text-xs text-white/75 mt-0.5">
            Identity and profile documents
            @if ($customer)
                for {{ $customer->full_name ?? 'this subject' }}
            @endif
            — grouped by category.
        </p>
    </div>
    <div class="p-5 space-y-4">
        @if (! $customer)
            <p class="text-sm text-gray-500">Profile documents unlock after this subject finishes onboarding.</p>
        @elseif ($docs->isEmpty())
            <p class="text-sm text-gray-500">No documents on this profile yet.</p>
        @else
            @foreach ($docsByCategory as $cat => $catDocs)
                <div class="rounded-xl ring-1 ring-brand/10 overflow-hidden">
                    <div class="px-3.5 py-2 bg-gradient-to-r from-brand-muted/60 to-white border-b border-brand/10">
                        <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ $cat }}</p>
                    </div>
                    <div class="p-3 grid md:grid-cols-2 gap-3">
                        @foreach ($catDocs as $doc)
                            <div class="rounded-xl ring-1 ring-brand/10 overflow-hidden bg-white p-3 flex items-start gap-3">
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
                                            <p class="text-[11px] text-gray-500 mt-0.5">
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
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</section>
