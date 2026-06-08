@php
    $requirements = $profile['product_requirements'] ?? collect();
    $uploads = $profile['requirement_uploads'] ?? collect();
    $documentRequests = $profile['document_requests'] ?? collect();
    $guarantorInvitations = $profile['guarantor_invitations'] ?? collect();
    $offer = $profile['offer'] ?? null;
    $loan = $profile['loan'] ?? null;
    $nextDue = $profile['next_due'] ?? null;
    $requiredCount = $requirements->where('is_required', true)->count();
    $satisfiedCount = $requirements->where('is_required', true)
        ->filter(fn ($r) => ($uploads[$r->id] ?? collect())->contains(fn ($u) => in_array($u->status, ['verified', 'approved', 'pending_review', 'pending'], true)))
        ->count();
    $docProgress = $requiredCount > 0 ? round(($satisfiedCount / $requiredCount) * 100) : 100;
@endphp

@if ($loan && ($application->status ?? '') === 'disbursed')
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6">
        <h2 class="font-semibold text-emerald-900 mb-2">{{ __('borrower.loan_profile.disbursed_title') }}</h2>
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_profile.loan_number') }}</p>
                <p class="font-mono font-semibold text-emerald-900">{{ $loan->loan_number }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_profile.outstanding') }}</p>
                <p class="font-semibold text-emerald-900">{{ format_money($loan->outstanding_balance ?? $loan->principal_amount) }}</p>
            </div>
            @if ($nextDue)
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loans_page.next_payment') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($nextDue->due_date)->format('d M Y') }} · {{ format_money($nextDue->total_due) }}</p>
                </div>
            @endif
        </div>
    </div>
@endif

@if ($offer)
    <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <p class="text-sm font-semibold text-amber-900">
                {{ $offer->isSigned() ? __('borrower.application.offer_signed') : __('borrower.application.offer_ready') }}
            </p>
            <p class="text-xs text-amber-800 mt-0.5">Reference: <span class="font-mono">{{ $offer->reference }}</span></p>
        </div>
        <a href="{{ route('site.borrower.application.agreement', $application) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
            {{ $offer->isSigned() ? __('borrower.application.view_agreement') : __('borrower.application.review_sign') }} →
        </a>
    </div>
@endif

@if (($application->product?->requires_guarantor ?? false) && ($guarantorInvitations->isNotEmpty() || ($application->customerGuarantors ?? collect())->isNotEmpty()))
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="font-semibold">{{ __('borrower.application.guarantor_section') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.application.guarantor_section_hint') }}</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($guarantorInvitations as $invite)
                @php
                    $status = app(\App\Services\GuarantorInvitationService::class)->invitationWorkflowStatusLabel($invite);
                    $gBadge = match (true) {
                        str_contains(strtolower($status), 'accepted') => 'bg-emerald-100 text-emerald-700',
                        str_contains(strtolower($status), 'rejected') => 'bg-red-100 text-red-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <li class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $invite->invitee_name ?? __('borrower.application.guarantor_external') }}</p>
                        <p class="text-xs text-gray-500">{{ ucfirst($invite->type ?? 'guarantor') }} · {{ $invite->contact }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $gBadge }}">{{ $status }}</span>
                </li>
            @endforeach
            @foreach ($application->customerGuarantors as $link)
                @if ($guarantorInvitations->contains('customer_guarantor_id', $link->id))
                    @continue
                @endif
                @php
                    $status = app(\App\Services\GuarantorInvitationService::class)->guarantorLinkStatusLabel($link);
                    $gBadge = match (true) {
                        str_contains(strtolower($status), 'accepted') => 'bg-emerald-100 text-emerald-700',
                        str_contains(strtolower($status), 'rejected') => 'bg-red-100 text-red-700',
                        default => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <li class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $link->displayName() }}</p>
                        <p class="text-xs text-gray-500">{{ __('borrower.application.guarantor_internal') }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $gBadge }}">{{ $status }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($documentRequests->isNotEmpty())
    <div id="documents" class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
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
                    $reqLabel = match ($docReq->status) {
                        'satisfied' => __('borrower.application.request_status_completed'),
                        'uploaded'  => __('borrower.application.request_status_uploaded'),
                        'rejected'  => __('borrower.application.request_status_pending'),
                        default     => __('borrower.application.request_status_pending'),
                    };
                @endphp
                <li id="request-{{ $docReq->id }}" class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ ucfirst($docReq->type) }}
                                @if ($docReq->due_at) · Due {{ $docReq->due_at->format('d M Y') }} @endif
                            </p>
                            @if ($docReq->instructions)
                                <p class="text-sm text-gray-600 mt-2">{{ $docReq->instructions }}</p>
                            @endif
                            @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mt-2">{{ $docReq->admin_notes }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $reqBadge }}">{{ $reqLabel }}</span>
                    </div>

                    @if ($docReq->uploads->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach ($docReq->uploads as $upload)
                                <a href="{{ asset('storage/'.$upload->file_path) }}" target="_blank"
                                   class="text-xs font-semibold text-amber-700 hover:underline">
                                    {{ __('borrower.application.view_upload') }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($docReq->needsBorrowerAction())
                        <x-site.document-upload
                            :action="route('site.borrower.application.document-requests.store', [$application, $docReq])"
                            :show-clarification="$docReq->type === 'clarification'"
                            :multiple="true"
                        />
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($requirements->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold">{{ __('borrower.application.required_documents') }}</h2>
                <p class="text-xs text-gray-500">{{ __('borrower.application.required_documents_hint') }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">{{ __('borrower.application.required_progress', ['satisfied' => $satisfiedCount, 'total' => $requiredCount]) }}</p>
                <div class="mt-1 h-1.5 w-40 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500" style="width: {{ $docProgress }}%"></div>
                </div>
            </div>
        </div>
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
                <li id="requirement-{{ $req->id }}" class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $req->name }}</p>
                            @if ($req->description)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $req->description }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $label }}</span>
                    </div>

                    @if ($latest && $latest->file_path)
                        <div class="text-xs text-gray-500 mb-2">
                            <a href="{{ asset('storage/'.$latest->file_path) }}" target="_blank" class="text-amber-600 hover:underline">
                                {{ __('borrower.application.view_upload') }}
                            </a>
                        </div>
                    @endif

                    @if (! $isApproved)
                        <form method="POST" action="{{ route('site.borrower.application.documents.store', $application->id) }}" enctype="multipart/form-data" class="grid sm:grid-cols-[1fr_auto] gap-2 items-end">
                            @csrf
                            <input type="hidden" name="loan_product_requirement_id" value="{{ $req->id }}">
                            <input type="file" name="file" accept="image/*,application/pdf" required class="w-full text-sm">
                            <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2 rounded-full text-sm whitespace-nowrap">
                                {{ $latest ? __('borrower.loan_profile.reupload') : __('borrower.loan_profile.upload') }}
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
