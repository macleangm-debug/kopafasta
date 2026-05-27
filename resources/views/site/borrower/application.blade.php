<x-site.borrower-layout title="Application {{ $application->application_number }} — Kopafasta" active="applications">

    @php
        $statusBadge = match (true) {
            $application->status === 'rejected' => 'bg-red-100 text-red-700',
            in_array($application->status, ['approved','disbursement','disbursed']) => 'bg-emerald-100 text-emerald-700',
            $application->status === 'submitted' => 'bg-amber-100 text-amber-700',
            default => 'bg-sky-100 text-sky-700',
        };
        $progress = $requiredCount > 0 ? round(($satisfiedCount / $requiredCount) * 100) : 100;
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.applications') }}" class="text-xs text-gray-500 hover:text-gray-700">← Back to applications</a>
    </div>

    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Application</p>
            <h1 class="text-2xl sm:text-3xl font-bold font-mono">{{ $application->application_number }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $application->product->name ?? '—' }}</p>
        </div>
        <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ ucfirst(str_replace('_',' ', $application->status)) }}</span>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    {{-- Application summary --}}
    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Requested amount</p>
            <p class="text-lg font-bold">TZS {{ number_format($application->requested_amount) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Tenure</p>
            <p class="text-lg font-bold">{{ $application->requested_tenure_months }} months</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Submitted</p>
            <p class="text-lg font-bold">{{ optional($application->submitted_at)->format('d M Y') ?? '—' }}</p>
        </div>
    </div>

    @php
        $offer = \App\Models\LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')->latest('id')->first();
    @endphp
    @if ($offer)
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <p class="text-sm font-semibold text-amber-900">
                    @if ($offer->isSigned())
                        Offer letter signed ✓
                    @else
                        Offer letter ready for your acceptance
                    @endif
                </p>
                <p class="text-xs text-amber-800 mt-0.5">Reference: <span class="font-mono">{{ $offer->reference }}</span></p>
            </div>
            <a href="{{ route('borrower.application.agreement', $application) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
                {{ $offer->isSigned() ? 'View signed agreement' : 'Review & sign' }} →
            </a>
        </div>
    @endif

    {{-- Requirements checklist --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold">Required documents</h2>
                <p class="text-xs text-gray-500">Upload every required document below so we can process your application.</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">{{ $satisfiedCount }} of {{ $requiredCount }} required complete</p>
                <div class="mt-1 h-1.5 w-40 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </div>

        @if ($requirements->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">
                This product has no document requirements configured. You're all set.
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($requirements as $req)
                    @php
                        $myUploads = $uploads[$req->id] ?? collect();
                        $latest = $myUploads->first();
                        $isApproved = $latest && in_array($latest->status, ['verified','approved']);
                        $isRejected = $latest && $latest->status === 'rejected';
                        $badge = match (true) {
                            $isApproved => 'bg-emerald-100 text-emerald-700',
                            $isRejected => 'bg-red-100 text-red-700',
                            $latest      => 'bg-amber-100 text-amber-700',
                            !$req->is_required => 'bg-gray-100 text-gray-500',
                            default      => 'bg-gray-100 text-gray-600',
                        };
                        $label = $latest
                            ? ucfirst(str_replace('_',' ', $latest->status))
                            : ($req->is_required ? 'Required' : 'Optional');
                    @endphp
                    <li class="p-5">
                        <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 flex items-center gap-2">
                                    @if ($isApproved)
                                        <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                    {{ $req->name }}
                                </p>
                                @if ($req->description)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $req->description }}</p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $label }}</span>
                        </div>

                        @if ($latest)
                            <div class="text-xs text-gray-500 mb-2">
                                Last uploaded {{ \Carbon\Carbon::parse($latest->created_at)->diffForHumans() }}
                                @if ($latest->file_path)
                                    · <a href="{{ asset('storage/'.$latest->file_path) }}" target="_blank" class="text-amber-600 hover:underline">View file</a>
                                @endif
                            </div>
                            @if ($isRejected && $latest->notes)
                                <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mb-2">{{ $latest->notes }}</p>
                            @endif
                        @endif

                        @if (!$isApproved)
                            <form method="POST" action="{{ route('site.borrower.application.documents.store', $application->id) }}" enctype="multipart/form-data" class="grid sm:grid-cols-[1fr_auto] gap-2 items-end">
                                @csrf
                                <input type="hidden" name="loan_product_requirement_id" value="{{ $req->id }}">
                                <input type="file" name="file" accept="image/*,application/pdf" required class="w-full text-sm">
                                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2 rounded-full text-sm whitespace-nowrap">
                                    {{ $latest ? 'Re-upload' : 'Upload' }}
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="text-xs text-gray-500 mt-4 text-center">
        Accepted formats: JPG, PNG, PDF · max 5 MB. Make sure the file is clear and readable.
    </p>

</x-site.borrower-layout>
