<x-admin.review-section id="customer-documents" title="Documents" subtitle="Upload KYC and income files on behalf of the borrower">
    <x-slot:actions>
        <span class="text-xs text-gray-500">{{ $dossier['documents']->count() }} file(s)</span>
    </x-slot:actions>

    <form method="POST" action="{{ route('admin.customers.documents.store', $customer) }}" enctype="multipart/form-data"
          class="grid md:grid-cols-3 gap-4 mb-8 pb-8 border-b border-gray-100">
        @csrf
        <x-admin.select name="document_type_id" label="Document type"
                        :options="$dossier['document_types']->pluck('name', 'id')->all()"
                        required placeholder="— Select type —" />
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">File</label>
            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf"
                   class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-800 file:font-semibold">
            <p class="text-[11px] text-gray-500 mt-1">PDF or image, max 5 MB</p>
        </div>
        <x-admin.input name="notes" label="Notes (optional)" :value="old('notes')" />
        <div class="md:col-span-3 flex justify-end">
            <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2 rounded-lg">Upload document</button>
        </div>
    </form>

    @if ($dossier['documents']->isEmpty())
        <p class="text-sm text-gray-500 py-8 text-center">No documents uploaded yet. Use the form above to add ID, income statements, or other KYC files.</p>
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
                    <div class="px-4 pb-4 flex flex-wrap gap-2">
                        <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank"
                           class="text-xs font-semibold text-brand hover:text-brand-light bg-white ring-1 ring-gray-200 px-3 py-1.5 rounded-lg">
                            View file
                        </a>
                        @if (! in_array($doc->status, ['verified', 'approved'], true))
                            <form method="POST" action="{{ route('admin.customers.documents.verify', [$customer, $doc]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100">
                                    Verify
                                </button>
                            </form>
                        @endif
                        @if ($doc->status !== 'rejected')
                            <form method="POST" action="{{ route('admin.customers.documents.reject', [$customer, $doc]) }}" class="inline"
                                  onsubmit="return confirm('Reject this document?');">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-red-700 bg-red-50 ring-1 ring-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100">
                                    Reject
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.review-section>
