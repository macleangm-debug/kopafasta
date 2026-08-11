@php
    $docs = $dossier['documents'] ?? collect();
    $verified = $docs->whereIn('status', ['verified', 'approved'])->count();
    $pending = $docs->whereIn('status', ['pending', 'pending_review'])->count();
    $rejected = $docs->where('status', 'rejected')->count();
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">KYC vault</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">Documents on file</h4>
            <p class="text-xs text-gray-500 mt-0.5">Read-only. Request or verify documents from the loan application (screening).</p>
        </div>
        @if ($docs->isNotEmpty())
            <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                <span class="rounded-full bg-gray-100 text-gray-700 px-2.5 py-1">{{ $docs->count() }} total</span>
                @if ($verified > 0)
                    <span class="rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-1">{{ $verified }} verified</span>
                @endif
                @if ($pending > 0)
                    <span class="rounded-full bg-amber-100 text-amber-900 px-2.5 py-1">{{ $pending }} pending</span>
                @endif
                @if ($rejected > 0)
                    <span class="rounded-full bg-red-100 text-red-800 px-2.5 py-1">{{ $rejected }} rejected</span>
                @endif
            </div>
        @endif
    </div>

    @if ($docs->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-5 py-12 text-center">
            <p class="text-sm font-semibold text-gray-700">No documents on file</p>
            <p class="text-xs text-gray-500 mt-1">When the borrower uploads KYC, files appear here.</p>
        </div>
    @else
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($docs as $doc)
                @php
                    $url = $doc->file_path ? asset('storage/'.$doc->file_path) : null;
                    $ext = strtolower(pathinfo((string) $doc->file_path, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                    $kind = $isImage ? 'image' : 'pdf';
                    $title = $doc->documentType?->name ?? 'Document';
                    $status = (string) ($doc->status ?? 'pending');
                @endphp
                <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10 flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-start justify-between gap-2 bg-gradient-to-r from-brand-muted/30 to-white">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 truncate">{{ $title }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                {{ $doc->created_at?->timezone(config('app.timezone'))->format('d M Y · H:i') ?? '—' }}
                            </p>
                        </div>
                        <x-admin.badge :value="$status" group="document_status"
                            :map="[
                                'verified' => 'bg-emerald-100 text-emerald-800',
                                'approved' => 'bg-emerald-100 text-emerald-800',
                                'pending_review' => 'bg-amber-100 text-amber-800',
                                'pending' => 'bg-amber-100 text-amber-800',
                                'rejected' => 'bg-red-100 text-red-800',
                            ]" />
                    </div>

                    <div class="relative bg-gray-50 flex-1">
                        @if ($url && $isImage)
                            <button type="button"
                                    onclick="window.kfOpenDocumentPreview(@js($url), @js($title), 'image')"
                                    class="block w-full text-left group">
                                <img src="{{ $url }}" alt=""
                                     class="h-48 w-full object-cover transition group-hover:opacity-95 cursor-zoom-in">
                            </button>
                        @elseif ($url)
                            <button type="button"
                                    onclick="window.kfOpenDocumentPreview(@js($url), @js($title), @js($kind))"
                                    class="h-48 w-full grid place-items-center gap-1 hover:bg-brand-muted/20 transition">
                                <span class="inline-flex items-center justify-center size-12 rounded-2xl bg-white ring-1 ring-brand/15 text-brand font-bold text-xs">
                                    {{ strtoupper($ext ?: 'FILE') }}
                                </span>
                                <span class="text-xs font-semibold text-brand">Open preview</span>
                            </button>
                        @else
                            <div class="h-48 grid place-items-center text-sm text-gray-400">No file attached</div>
                        @endif
                    </div>

                    @if ($doc->verifier)
                        <p class="px-4 py-2 text-[11px] text-gray-500 border-t border-gray-100">
                            Reviewed by {{ $doc->verifier->name ?? 'staff' }}
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
