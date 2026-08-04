<p class="text-sm text-gray-500 mb-4">On-file documents only. Request or verify from the loan application (screening).</p>

@if ($dossier['documents']->isEmpty())
    <p class="text-sm text-gray-500 py-8 text-center">No documents on file.</p>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($dossier['documents'] as $doc)
            @php
                $url = $doc->file_path ? asset('storage/'.$doc->file_path) : null;
                $ext = strtolower(pathinfo((string) $doc->file_path, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                $kind = $isImage ? 'image' : 'pdf';
            @endphp
            <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50">
                <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-800 truncate">{{ $doc->documentType?->name ?? 'Document' }}</p>
                    <x-admin.badge :value="$doc->status ?? 'pending'" group="document_status"
                        :map="[
                            'verified' => 'bg-emerald-100 text-emerald-800',
                            'approved' => 'bg-emerald-100 text-emerald-800',
                            'pending_review' => 'bg-amber-100 text-amber-800',
                            'pending' => 'bg-amber-100 text-amber-800',
                            'rejected' => 'bg-red-100 text-red-800',
                        ]" />
                </div>
                @if ($url && $isImage)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js($url), @js($doc->documentType?->name ?? 'Document'), 'image')"
                            class="block w-full text-left group">
                        <img src="{{ $url }}" alt="" class="w-full h-44 object-cover group-hover:opacity-95 transition cursor-zoom-in">
                    </button>
                @elseif ($url)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js($url), @js($doc->documentType?->name ?? 'Document'), @js($kind))"
                            class="h-44 w-full grid place-items-center text-sm font-semibold text-brand hover:bg-brand-muted/30 transition">
                        Open {{ strtoupper($ext ?: 'file') }} preview
                    </button>
                @else
                    <div class="h-44 grid place-items-center text-sm text-gray-400">No file</div>
                @endif
                <p class="px-3 py-2 text-[11px] text-gray-500">{{ $doc->created_at?->format('d M Y') }}</p>
            </div>
        @endforeach
    </div>
@endif
