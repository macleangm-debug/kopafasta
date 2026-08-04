<x-site.borrower-layout :title="brand_title(__('borrower.application.page_title', ['number' => $application->application_number]))" active="loans" content-width="wide">

    @php
        $statusBadge = match (true) {
            $application->status === 'rejected' => 'bg-red-100 text-red-700',
            in_array($application->status, ['approved','disbursement','disbursed']) => 'bg-emerald-100 text-emerald-700',
            $application->status === 'submitted' => 'bg-amber-100 text-amber-700',
            default => 'bg-sky-100 text-sky-700',
        };
        $progress = $requiredCount > 0 ? round(($satisfiedCount / $requiredCount) * 100) : 100;
        $requestTypes = __('borrower.application.request_types');
        $requestStatuses = __('borrower.application.request_statuses');
        $uploadStatuses = __('borrower.application.upload_statuses');
        $guarantorLinkStatuses = __('borrower.application.guarantor_link_statuses');
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans') }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('borrower.application.back') }}</a>
    </div>

    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.application.label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold font-mono">{{ $application->application_number }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $application->product->name ?? '—' }}</p>
        </div>
        <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ app(\App\Services\BorrowerApplicationsDashboardService::class)->borrowerStatusLabel($application->status, $application->current_stage) }}</span>
    </div>


    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    {{-- Application summary --}}
    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.application.requested_amount') }}</p>
            <p class="text-lg font-bold">{{ format_money($application->requested_amount) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.application.tenure') }}</p>
            <p class="text-lg font-bold">{{ __('borrower.application.tenure_months', ['count' => $application->requested_tenure_months]) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.application.submitted') }}</p>
            <p class="text-lg font-bold">{{ optional($application->submitted_at)->format('d M Y') ?? '—' }}</p>
        </div>
    </div>

    @if (($application->product?->requires_guarantor ?? false) && (($guarantorInvitations ?? collect())->isNotEmpty() || ($application->customerGuarantors ?? collect())->isNotEmpty()))
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="font-semibold">{{ __('borrower.application.guarantor_section') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.application.guarantor_section_hint') }}</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($guarantorInvitations as $invite)
                    @php
                        $gBadge = match ($invite->status) {
                            'approved', 'accepted' => 'bg-emerald-100 text-emerald-700',
                            'declined', 'rejected' => 'bg-red-100 text-red-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                        $gLabel = match ($invite->status) {
                            'approved', 'accepted' => __('borrower.application.guarantor_accepted'),
                            'declined', 'rejected' => __('borrower.application.guarantor_declined'),
                            default => __('borrower.application.guarantor_pending'),
                        };
                    @endphp
                    <li class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $invite->invitee_name ?? __('borrower.application.guarantor_external') }}</p>
                            <p class="text-xs text-gray-500">{{ $requestTypes[$invite->type ?? 'guarantor'] ?? ucfirst($invite->type ?? 'guarantor') }} · {{ $invite->contact }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $gBadge }}">{{ $gLabel }}</span>
                    </li>
                @endforeach
                @foreach ($application->customerGuarantors as $link)
                    @if ($guarantorInvitations->contains('customer_guarantor_id', $link->id))
                        @continue
                    @endif
                    @php
                        $gBadge = match ($link->status) {
                            'approved' => 'bg-emerald-100 text-emerald-700',
                            'declined', 'rejected' => 'bg-red-100 text-red-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                    @endphp
                    <li class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $link->displayName() }}</p>
                            <p class="text-xs text-gray-500">{{ __('borrower.application.guarantor_internal') }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $gBadge }}">{{ $guarantorLinkStatuses[$link->status] ?? ucfirst($link->status) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $offer = \App\Models\LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')->latest('id')->first();
    @endphp
    @if ($offer)
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <p class="text-sm font-semibold text-amber-900">
                    @if ($offer->isSigned())
                        {{ __('borrower.application.offer_signed') }}
                    @else
                        {{ __('borrower.application.offer_ready') }}
                    @endif
                </p>
                <p class="text-xs text-amber-800 mt-0.5">{{ __('borrower.application.offer_reference') }} <span class="font-mono">{{ $offer->reference }}</span></p>
            </div>
            <a href="{{ route('site.borrower.application.agreement', $application) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
                {{ $offer->isSigned() ? __('borrower.application.view_agreement') : __('borrower.application.review_sign') }} →
            </a>
        </div>
    @endif

    @php
        $pendingUploads = $requirements->where('is_required', true)->filter(fn ($r) => ! ($uploads[$r->id] ?? collect())->contains(fn ($u) => in_array($u->status, ['verified', 'approved', 'pending_review'], true)))->count();
        $underReview = $requirements->where('is_required', true)->filter(fn ($r) => ($uploads[$r->id] ?? collect())->contains(fn ($u) => in_array($u->status, ['pending_review', 'uploaded'], true)))->count();
        $openUnderwriting = $documentRequests->filter(fn ($r) => $r->needsBorrowerAction())->count();
    @endphp

    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.application.pending_uploads') }}</p>
            <p class="text-2xl font-bold mt-1">{{ $pendingUploads }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.application.pending_uploads_hint') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.application.under_review') }}</p>
            <p class="text-2xl font-bold mt-1">{{ $underReview }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.application.under_review_hint') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.application.underwriting_requests') }}</p>
            <p class="text-2xl font-bold mt-1">{{ $openUnderwriting }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.application.underwriting_requests_hint') }}</p>
        </div>
    </div>

    {{-- Ad-hoc document requests from underwriting --}}
    @if ($documentRequests->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="font-semibold">{{ __('borrower.application.requested_documents') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.application.requested_documents_hint') }}</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($documentRequests as $docReq)
                    @php
                        $reqBadge = match ($docReq->status) {
                            'satisfied' => 'bg-emerald-100 text-emerald-700',
                            'uploaded'  => 'bg-amber-100 text-amber-700',
                            'rejected'  => 'bg-red-100 text-red-700',
                            default     => 'bg-sky-100 text-sky-700',
                        };
                    @endphp
                    <li class="p-5">
                        <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $requestTypes[$docReq->type] ?? ucfirst($docReq->type) }}
                                    @if ($docReq->due_at) · {{ __('borrower.application.due_date', ['date' => $docReq->due_at->format('d M Y')]) }} @endif
                                </p>
                                @if ($docReq->instructions)
                                    <p class="text-sm text-gray-600 mt-2">{{ $docReq->instructions }}</p>
                                @endif
                                @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                    <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mt-2">{{ $docReq->admin_notes }}</p>
                                @endif
                                @if ($docReq->borrower_response && $docReq->status !== 'pending')
                                    <p class="text-xs text-gray-500 mt-2">{{ __('borrower.application.your_response') }} {{ $docReq->borrower_response }}</p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $reqBadge }}">{{ $requestStatuses[$docReq->status] ?? ucfirst($docReq->status) }}</span>
                        </div>

                        @if ($docReq->uploads->isNotEmpty())
                            <div class="flex flex-wrap gap-2 mb-3">
                                @foreach ($docReq->uploads as $upload)
                                    <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                @endforeach
                            </div>
                        @endif

                        @if ($docReq->needsBorrowerAction())
                            <x-site.document-upload
                                :action="route('site.borrower.application.document-requests.store', [$application, $docReq])"
                                :show-clarification="$docReq->type === 'clarification'"
                                :multiple="true"
                            />
                        @elseif ($docReq->status === 'uploaded')
                            <p class="text-sm text-amber-700">{{ __('borrower.application.submitted_awaiting') }}</p>
                        @elseif ($docReq->status === 'satisfied')
                            <p class="text-sm text-emerald-700">{{ __('borrower.application.satisfied') }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Requirements checklist --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold">{{ __('borrower.application.required_documents') }}</h2>
                <p class="text-xs text-gray-500">{{ __('borrower.application.required_documents_hint') }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">{{ __('borrower.application.required_progress', ['satisfied' => $satisfiedCount, 'total' => $requiredCount]) }}</p>
                <div class="mt-1 h-1.5 w-40 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </div>

        @if ($requirements->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">
                {{ __('borrower.application.no_requirements') }}
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
                            ? ($uploadStatuses[$latest->status] ?? ucfirst(str_replace('_', ' ', $latest->status)))
                            : ($req->is_required ? __('borrower.application.status_required') : __('borrower.application.status_optional'));
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
                                {{ __('borrower.application.last_uploaded', ['time' => \Carbon\Carbon::parse($latest->created_at)->diffForHumans()]) }}
                                @if ($latest->file_path)
                                    · <x-site.document-view-button :url="asset('storage/'.$latest->file_path)" :label="__('borrower.application.view_file')" />
                                @endif
                            </div>
                            @if ($isRejected && $latest->notes)
                                <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mb-2">{{ $latest->notes }}</p>
                            @endif
                        @endif

                        @if (!$isApproved)
                            <x-site.document-upload
                                :action="route('site.borrower.application.documents.store', $application->id)"
                                :multiple="false"
                            >
                                <input type="hidden" name="loan_product_requirement_id" value="{{ $req->id }}">
                            </x-site.document-upload>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="text-xs text-gray-500 mt-4 text-center">
        {{ __('borrower.application.accepted_formats_note') }}
    </p>

</x-site.borrower-layout>
