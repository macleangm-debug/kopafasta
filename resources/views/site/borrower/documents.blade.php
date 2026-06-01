<x-site.borrower-layout :title="brand_title('Documents')" active="documents">

    <h1 class="text-2xl font-bold mb-1">My documents</h1>
    <p class="text-sm text-gray-500 mb-6">Upload required documents. JPG, PNG or PDF (max 5 MB).</p>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Upload --}}
        <form method="POST" action="{{ route('site.borrower.documents.store') }}" enctype="multipart/form-data"
              class="lg:col-span-1 bg-white rounded-2xl border border-gray-200 p-6">
            @csrf
            <h2 class="font-semibold mb-3">Upload a document</h2>

            <label class="block text-xs font-medium text-gray-600 mb-1">Document type</label>
            <select name="document_type_id" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm mb-4">
                @foreach ($types as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>

            <label class="block text-xs font-medium text-gray-600 mb-1">File</label>
            <input type="file" name="file" accept="image/*,application/pdf" required class="w-full text-sm mb-5">

            <button class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Upload</button>
        </form>

        {{-- List --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold">Uploaded documents</h2>
                <span class="text-xs text-gray-500">{{ $documents->count() }}</span>
            </div>
            @if ($documents->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">No documents yet.</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($documents as $doc)
                        @php
                            $color = match ($doc->status) {
                                'verified','approved' => 'bg-emerald-100 text-emerald-700',
                                'rejected'            => 'bg-red-100 text-red-700',
                                default               => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <li class="px-5 py-4 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm">{{ $doc->documentType->name ?? 'Document' }}</p>
                                <p class="text-xs text-gray-500 truncate">Uploaded {{ \Carbon\Carbon::parse($doc->created_at)->format('d M Y') }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ ucfirst($doc->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</x-site.borrower-layout>
