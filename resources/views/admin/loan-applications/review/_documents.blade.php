<x-admin.review-section id="review-documents" title="Document review" subtitle="Product requirements and uploaded files for this application">
    <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
        <div class="text-sm text-gray-600">
            <span class="font-semibold text-gray-900">{{ $review['satisfied_docs'] }}</span> of
            <span class="font-semibold text-gray-900">{{ $review['required_docs'] }}</span> required documents verified
        </div>
        <div class="h-2 w-48 max-w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-amber-500 transition-all" style="width: {{ $review['document_progress'] }}%"></div>
        </div>
    </div>

    @if ($review['requirements']->isEmpty())
        <p class="text-sm text-gray-500">No document requirements configured for this loan product.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-widest text-gray-500 border-b border-gray-100">
                        <th class="pb-3 pr-4 font-semibold">Document</th>
                        <th class="pb-3 pr-4 font-semibold">Required</th>
                        <th class="pb-3 pr-4 font-semibold">Status</th>
                        <th class="pb-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($review['requirements'] as $req)
                        @php
                            $upload = $review['uploads']->get($req->id);
                            $isApproved = $upload && in_array($upload->status, ['verified', 'approved'], true);
                            $badgeMap = match (true) {
                                $isApproved => 'bg-emerald-100 text-emerald-800',
                                $upload && $upload->status === 'rejected' => 'bg-red-100 text-red-800',
                                (bool) $upload => 'bg-amber-100 text-amber-800',
                                default => 'bg-gray-100 text-gray-600',
                            };
                            $statusLabel = $upload
                                ? display_label($upload->status, 'document_status')
                                : ($req->is_required ? 'Missing' : 'Optional');
                        @endphp
                        <tr>
                            <td class="py-3 pr-4">
                                <p class="font-semibold text-gray-900">{{ $req->name }}</p>
                                @if ($req->description)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $req->description }}</p>
                                @endif
                            </td>
                            <td class="py-3 pr-4">{{ $req->is_required ? 'Yes' : 'No' }}</td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $badgeMap }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="py-3">
                                @if ($upload?->file_path)
                                    <a href="{{ asset('storage/'.$upload->file_path) }}" target="_blank"
                                       class="text-xs font-semibold text-amber-700 hover:text-amber-800">View file</a>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin.review-section>

@if (($review['kyc_documents'] ?? collect())->isNotEmpty())
    <x-admin.review-section id="review-kyc-documents" title="Borrower document library" subtitle="All KYC and supporting documents on the customer profile">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-widest text-gray-500 border-b border-gray-100">
                        <th class="pb-3 pr-4 font-semibold">Document</th>
                        <th class="pb-3 pr-4 font-semibold">Category</th>
                        <th class="pb-3 pr-4 font-semibold">Status</th>
                        <th class="pb-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($review['kyc_documents'] as $doc)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-gray-900">{{ $doc->documentType?->name ?? 'Supporting document' }}</td>
                            <td class="py-3 pr-4 capitalize">{{ $doc->documentType?->category ?? 'kyc' }}</td>
                            <td class="py-3 pr-4">{{ display_label($doc->status, 'document_status') }}</td>
                            <td class="py-3">
                                @if ($doc->file_path)
                                    <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank" class="text-xs font-semibold text-amber-700 hover:text-amber-800">View / download</a>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.review-section>
@endif
