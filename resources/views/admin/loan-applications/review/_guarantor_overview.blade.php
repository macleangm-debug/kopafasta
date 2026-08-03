@php
    $canReview = auth()->user()?->hasPermission('applications.review')
        || auth()->user()?->hasPermission('applications.view');
    $guarantor = $guarantor ?? null;
    $rows = $guarantor ? collect([$guarantor]) : collect($review['guarantors'] ?? []);
    $supplementOpen = app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($record);
    $openRequest = app(\App\Services\GuarantorSupplementService::class)->openRequest($record);
@endphp

<div class="space-y-5">
    @unless ($single ?? false)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Guarantor desk</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">All guarantors on this file</h2>
            </div>
            <div class="p-5">
                @include('admin.loan-applications.review._guarantor_actions')
            </div>
        </div>
    @endunless

    @if ($single ?? false)
        @include('admin.loan-applications.review._guarantor_actions', ['compact' => true])
    @endif

    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500">
            @if ($review['product']?->requires_guarantor)
                Unusual at screening — files normally arrive only after the guarantor finishes. Check invitation status.
            @else
                This loan product does not require a guarantor.
            @endif
        </p>
    @else
        @foreach ($rows as $guarantor)
            @php
                $riskClass = match ($guarantor['risk_band'] ?? 'high') {
                    'low'    => 'bg-emerald-100 text-emerald-800',
                    'medium' => 'bg-amber-100 text-amber-800',
                    default  => 'bg-red-100 text-red-800',
                };
                $statusClass = match ($guarantor['status'] ?? '') {
                    'approved' => 'bg-emerald-100 text-emerald-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    default    => 'bg-amber-100 text-amber-800',
                };
                $crb = $guarantor['crb'] ?? [];
                $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));
                $crbTone = match ($crbRec) {
                    'approve' => 'from-emerald-600 to-emerald-800',
                    'refer' => 'from-amber-500 to-amber-700',
                    'reject' => 'from-rose-600 to-rose-800',
                    default => 'from-slate-600 to-slate-800',
                };
                $profileComplete = (bool) ($guarantor['profile_complete'] ?? false);
            @endphp

            <article class="rounded-2xl ring-1 ring-brand/10 overflow-hidden bg-white shadow-sm">
                <div class="px-5 py-4 bg-gradient-to-r from-brand-muted/50 to-white border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Overview</p>
                        <h3 class="text-base font-bold text-gray-900 mt-0.5 truncate">{{ $guarantor['name'] ?: '—' }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if ($guarantor['membership_no'] ?? null) Member {{ $guarantor['membership_no'] }} · @endif
                            {{ $guarantor['phone'] ?? '—' }}
                            @if ($guarantor['relationship'] ?? null)
                                · {{ ucfirst($guarantor['relationship']) }}
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusClass }}">{{ $guarantor['status_label'] ?? ucfirst($guarantor['status'] ?? '') }}</span>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $riskClass }}">{{ $guarantor['risk_label'] ?? '—' }}</span>
                    </div>
                </div>

                <div class="p-5 space-y-5">
                    <dl class="grid sm:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Active loans</dt>
                            <dd class="font-semibold mt-1">{{ $guarantor['active_loans'] ?? 0 }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Guarantees</dt>
                            <dd class="font-semibold mt-1">{{ $guarantor['guarantee_count'] ?? 0 }} / {{ $guarantor['guarantee_max'] ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Exposure</dt>
                            <dd class="font-semibold mt-1">{{ format_money($guarantor['guarantee_exposure'] ?? 0) }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Guaranteed loans</dt>
                            <dd class="font-semibold mt-1">{{ $guarantor['guaranteed_loans'] ?? 0 }}</dd>
                        </div>
                        @if (! empty($guarantor['affordability']))
                            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Capacity</dt>
                                <dd class="font-semibold mt-1 {{ ($guarantor['affordability']['verdict'] ?? '') === 'fail' ? 'text-red-700' : (($guarantor['affordability']['verdict'] ?? '') === 'warn' ? 'text-amber-700' : 'text-emerald-700') }}">
                                    {{ $guarantor['affordability']['status_label'] ?? '—' }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if (! $profileComplete)
                        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                            <p class="text-sm font-semibold text-amber-950">Waiting for guarantor profile</p>
                            <p class="text-xs text-amber-900/90 mt-1">
                                Full sections unlock after onboarding ({{ (int) ($guarantor['profile_percent'] ?? 0) }}% done). Unusual once a file is in screening.
                            </p>
                        </div>
                    @else
                        <div class="rounded-2xl overflow-hidden bg-gradient-to-br {{ $crbTone }} text-white shadow-sm">
                            <div class="px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">CRB · Guarantor</p>
                                        <p class="text-2xl font-bold mt-1 uppercase tracking-tight">{{ $crbRec !== '' ? $crbRec : '—' }}</p>
                                    </div>
                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 bg-white/15">
                                        Score {{ $crb['score'] ?? '—' }}
                                    </span>
                                </div>
                                <p class="text-sm text-white/85 mt-3 leading-relaxed">
                                    {{ $guarantor['crb_explanation']['summary'] ?? 'No CRB explanation available.' }}
                                </p>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl bg-white/10 px-2 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-white/60">Loans</p>
                                        <p class="text-sm font-bold">{{ $crb['existing_loans'] ?? 0 }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/10 px-2 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-white/60">Delinq.</p>
                                        <p class="text-sm font-bold">{{ $crb['delinquencies'] ?? 0 }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/10 px-2 py-2">
                                        <p class="text-[10px] uppercase tracking-wider text-white/60">Fresh</p>
                                        <p class="text-sm font-bold truncate">{{ $crb['freshness_label'] ?? '—' }}</p>
                                    </div>
                                </div>
                                @if (! empty($crb['submission_meta']['reused']) || ! empty($crb['is_fresh']))
                                    <p class="mt-3 text-[11px] text-white/70">Within freshness window — reused automatically (no manual refresh).</p>
                                @endif
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">
                            Use the section tabs above for Personal, Face, Residence, Activity and Documents — same checklist as the borrower.
                        </p>
                    @endif

                    @if ($canReview && ($guarantor['can_change'] ?? false))
                        <div class="flex flex-wrap gap-2 pt-1 border-t border-gray-100">
                            <button type="button"
                                    data-open-dialog="change-guarantor-{{ $guarantor['link_id'] }}"
                                    class="inline-flex items-center text-xs font-semibold text-rose-900 bg-rose-100 hover:bg-rose-200 px-3.5 py-2 rounded-lg ring-1 ring-rose-200">
                                {{ __('borrower.guarantor_supplement.change_admin_button') }}
                            </button>
                            <dialog id="change-guarantor-{{ $guarantor['link_id'] }}"
                                    class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-0 backdrop:bg-black/40 open:flex open:flex-col">
                                <form method="POST" action="{{ route('admin.loan-applications.guarantor-change', [$record, $guarantor['link_id']]) }}" class="p-6 space-y-4">
                                    @csrf
                                    <h4 class="font-semibold text-gray-900">Ask borrower to change guarantor</h4>
                                    <p class="text-sm text-gray-600">
                                        Declines <span class="font-semibold">{{ $guarantor['name'] }}</span> for this application only.
                                        Their membership and CRB stay reusable elsewhere within freshness.
                                    </p>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.guarantor_supplement.change_admin_notes') }}</label>
                                        <textarea name="notes" rows="3" maxlength="1000"
                                                  placeholder="e.g. Guarantor CRB shows high delinquency — please nominate someone else"
                                                  class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" data-close-dialog="change-guarantor-{{ $guarantor['link_id'] }}"
                                                class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                                        <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">
                                            Confirm change request
                                        </button>
                                    </div>
                                </form>
                            </dialog>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    @endif
</div>
