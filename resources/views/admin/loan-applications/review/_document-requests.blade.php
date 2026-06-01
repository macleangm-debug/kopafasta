@perm('applications.request_documents')
<x-admin.review-section title="Request additional documents" subtitle="Ask the borrower for ad-hoc uploads or clarifications">
    <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="grid md:grid-cols-2 gap-4 mb-6 pb-6 border-b border-gray-100">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Type</label>
            <select name="type" class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
                <option value="document">Document upload</option>
                <option value="clarification">Clarification</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Label</label>
            <input type="text" name="label" required maxlength="120" placeholder="e.g. Bank statement (3 months)"
                   class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Instructions</label>
            <textarea name="instructions" rows="2" maxlength="2000" placeholder="Tell the borrower exactly what you need…"
                      class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Due date (optional)</label>
            <input type="date" name="due_at" class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
                Request document
            </button>
        </div>
    </form>

    @if ($documentRequests->isEmpty())
        <p class="text-sm text-gray-500">No ad-hoc requests yet.</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($documentRequests as $docReq)
                @php
                    $statusClass = match ($docReq->status) {
                        'satisfied' => 'bg-emerald-100 text-emerald-700',
                        'uploaded'  => 'bg-amber-100 text-amber-700',
                        'rejected'  => 'bg-red-100 text-red-700',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <li class="py-4 first:pt-0">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ ucfirst($docReq->type) }}
                                @if ($docReq->due_at) · Due {{ $docReq->due_at->format('d M Y') }} @endif
                                @if ($docReq->requester) · by {{ $docReq->requester->name }} @endif
                            </p>
                            @if ($docReq->instructions)
                                <p class="text-sm text-gray-600 mt-2">{{ $docReq->instructions }}</p>
                            @endif
                            @if ($docReq->borrower_response)
                                <p class="text-sm text-sky-800 bg-sky-50 ring-1 ring-sky-200 rounded-lg px-3 py-2 mt-2">
                                    <span class="font-semibold">Borrower response:</span> {{ $docReq->borrower_response }}
                                </p>
                            @endif
                            @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                <p class="text-sm text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mt-2">{{ $docReq->admin_notes }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusClass }}">{{ ucfirst($docReq->status) }}</span>
                    </div>

                    @if ($docReq->uploads->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($docReq->uploads as $upload)
                                <a href="{{ asset('storage/'.$upload->file_path) }}" target="_blank"
                                   class="text-xs font-semibold text-amber-700 bg-amber-50 ring-1 ring-amber-200 px-3 py-1.5 rounded-lg">
                                    View file · {{ display_label($upload->status, 'document_status') }}
                                </a>
                            @endforeach
                        </ul>
                    @endif

                    @if ($docReq->status === 'uploaded')
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.loan-application-document-requests.satisfy', $docReq) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-3 py-1.5 rounded-lg">
                                    Approve document
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.loan-application-document-requests.reject', $docReq) }}" class="flex items-center gap-2 flex-wrap">
                                @csrf
                                <input type="text" name="notes" required maxlength="500" placeholder="Reason for rejection"
                                       class="rounded-lg border-gray-300 text-xs ring-1 ring-gray-200 px-3 py-2 w-48 max-w-full">
                                <button type="submit" class="text-xs font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-3 py-1.5 rounded-lg">
                                    Reject document
                                </button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-admin.review-section>
@endperm
