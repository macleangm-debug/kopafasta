<x-admin.review-section id="customer-documents" title="Documents" subtitle="On-file documents — verify and request docs on the loan application (screening)">
    <x-slot:actions>
        <span class="text-xs text-gray-500">{{ $dossier['documents']->count() }} file(s)</span>
    </x-slot:actions>

    @if ($dossier['documents']->isEmpty())
        <p class="text-sm text-gray-500 py-8 text-center">No documents on file. Document requests are made from the loan application under screening.</p>
    @else
        <div class="grid md:grid-cols-2 gap-4">
            @foreach ($dossier['documents'] as $doc)
                <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50/50">
                    <div class="p-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-gray-900">{{ $doc->documentType?->name ?? 'Document' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $doc->created_at?->format('d M Y, H:i') }}</p>
                            @if ($doc->notes)
                                <p class="text-xs text-gray-600 mt-1">{{ $doc->notes }}</p>
                            @endif
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
                    <div class="px-4 pb-4">
                        <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank"
                           class="text-xs font-semibold text-brand hover:text-brand-light bg-white ring-1 ring-gray-200 px-3 py-1.5 rounded-lg">
                            View file
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.review-section>
