@php
    $supplementOpen = app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($record);
    $openRequest = app(\App\Services\GuarantorSupplementService::class)->openRequest($record);
    $canReview = auth()->user()?->hasPermission('applications.review')
        || auth()->user()?->hasPermission('applications.view');
@endphp

<section id="review-guarantors" class="space-y-5">
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Guarantor desk</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Guarantor review</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Profile and CRB appear after the guarantor finishes onboarding. Declining here only affects this application — ask the borrower to choose someone else.
                </p>
            </div>
            @if ($supplementOpen)
                <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-amber-100 text-amber-950 ring-1 ring-amber-200">
                    {{ ($openRequest['kind'] ?? '') === 'change' ? 'Change request open' : 'Additional guarantor requested' }}
                </span>
            @endif
        </div>

        <div class="p-5 space-y-4">
            @if ($canReview)
                <details class="rounded-xl ring-1 ring-slate-200 bg-slate-50/80 overflow-hidden">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
                        <span>{{ __('borrower.guarantor_supplement.admin_button') }}</span>
                        <span class="text-xs font-normal text-gray-500">Keep current guarantor · add another</span>
                    </summary>
                    <form method="POST" action="{{ route('admin.loan-applications.request-guarantor-supplement', $record) }}" class="px-4 pb-4 space-y-3 border-t border-slate-200">
                        @csrf
                        <p class="text-xs text-gray-500 pt-3">Ask the borrower to add another guarantor without removing the current one.</p>
                        <label class="block text-xs font-medium text-gray-600">{{ __('borrower.guarantor_supplement.admin_notes') }}</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Optional note"></textarea>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2">
                            {{ __('borrower.guarantor_supplement.admin_button') }}
                        </button>
                    </form>
                </details>
            @endif

            @if ($review['guarantors']->isEmpty())
                <p class="text-sm text-gray-500">
                    @if ($review['product']?->requires_guarantor)
                        No guarantor linked yet — application may still be awaiting guarantor acceptance.
                    @else
                        This loan product does not require a guarantor.
                    @endif
                </p>
            @else
                <div class="space-y-5">
                    @foreach ($review['guarantors'] as $guarantor)
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
                                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Guarantor</p>
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
                                    @if (! $profileComplete)
                                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-slate-100 text-slate-700">
                                            Profile {{ (int) ($guarantor['profile_percent'] ?? 0) }}%
                                        </span>
                                    @endif
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
                                            Full personal details and CRB only appear after this guarantor completes onboarding ({{ (int) ($guarantor['profile_percent'] ?? 0) }}% done).
                                        </p>
                                    </div>
                                @else
                                    @php $p = $guarantor['profile'] ?? []; @endphp
                                    <div class="grid lg:grid-cols-2 gap-4">
                                        <div class="rounded-2xl ring-1 ring-brand/10 overflow-hidden">
                                            <div class="px-4 py-3 border-b border-gray-100 bg-brand-muted/30">
                                                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Profile</p>
                                                <p class="text-sm font-bold text-gray-900 mt-0.5">Personal information</p>
                                            </div>
                                            <dl class="p-4 grid sm:grid-cols-2 gap-3 text-sm">
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">Full name</dt><dd class="font-semibold mt-0.5">{{ $p['full_name'] ?? $guarantor['name'] }}</dd></div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">DOB</dt><dd class="font-semibold mt-0.5">{{ $p['date_of_birth'] ?? '—' }}</dd></div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">Gender</dt><dd class="font-semibold mt-0.5">{{ $p['gender'] ?? '—' }}</dd></div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">NIDA</dt><dd class="font-semibold mt-0.5 font-mono text-xs">{{ $p['national_id'] ?? '—' }}</dd></div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">NIDA status</dt><dd class="font-semibold mt-0.5">{{ $p['nida_status'] ?? '—' }}</dd></div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">Face</dt><dd class="font-semibold mt-0.5">{{ $p['face_status'] ?? '—' }}</dd></div>
                                                <div class="sm:col-span-2"><dt class="text-[10px] uppercase tracking-widest text-gray-500">Address</dt>
                                                    <dd class="font-semibold mt-0.5">
                                                        {{ collect([$p['street'] ?? null, $p['ward'] ?? null, $p['district'] ?? null, $p['region'] ?? null])->filter()->implode(', ') ?: '—' }}
                                                    </dd>
                                                </div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">Activity</dt><dd class="font-semibold mt-0.5">{{ $p['activity'] ?? '—' }}</dd></div>
                                                <div><dt class="text-[10px] uppercase tracking-widest text-gray-500">Income</dt><dd class="font-semibold mt-0.5">{{ $p['income_range'] ?? '—' }}</dd></div>
                                            </dl>
                                        </div>

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
                                                @if (! empty($guarantor['crb_explanation']['reasons']))
                                                    <ul class="mt-3 space-y-1 text-xs text-white/80">
                                                        @foreach (array_slice($guarantor['crb_explanation']['reasons'], 0, 3) as $reason)
                                                            <li>• {{ $reason }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
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
                                                @if (! empty($crb['submission_meta']['reused']))
                                                    <p class="mt-3 text-[11px] text-white/70">Reused within freshness window — no new CRB pull.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($canReview && (($guarantor['can_change'] ?? false) || ($guarantor['can_recall_crb'] ?? false)))
                                    <div class="flex flex-wrap gap-2 pt-1 border-t border-gray-100">
                                        @if ($guarantor['can_recall_crb'] ?? false)
                                            <form method="POST" action="{{ route('admin.loan-applications.guarantor-crb-refresh', [$record, $guarantor['link_id']]) }}"
                                                  onsubmit="return confirm('Force a fresh CRB pull for this guarantor? Reuse within the freshness window is preferred.');">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center text-xs font-semibold text-sky-900 bg-sky-100 hover:bg-sky-200 px-3.5 py-2 rounded-lg ring-1 ring-sky-200">
                                                    Recall CRB
                                                </button>
                                            </form>
                                        @endif
                                        @if ($guarantor['can_change'] ?? false)
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
                                                        Their membership and CRB stay reusable if they guarantee (or borrow) elsewhere within freshness.
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
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
