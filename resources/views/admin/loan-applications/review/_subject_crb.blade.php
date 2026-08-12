@php
    $customer = $review['customer'] ?? null;
    $isGuarantor = (bool) ($review['is_guarantor_subject'] ?? false);
    $isMember = (bool) ($review['is_member_subject'] ?? false);
    $crb = $isGuarantor
        ? ($review['guarantor_row']['crb'] ?? [])
        : ($review['crb'] ?? []);
    $explain = $isGuarantor
        ? ($review['guarantor_row']['crb_explanation'] ?? app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb))
        : app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $rec = strtolower((string) ($crb['recommendation'] ?? ''));
    $personal = $crb['personal'] ?? [];
    $detail = $crb['credit_detail'] ?? [];
    $meta = $crb['report_meta'] ?? [];
    $history = collect($crb['loan_history'] ?? $detail['loan_history'] ?? []);
    $externalLoans = (int) ($crb['existing_loans'] ?? 0);
    $outstanding = (float) ($crb['outstanding_balance'] ?? 0);
    $openAccounts = collect($detail['open_accounts'] ?? []);
    $closedAccounts = collect($detail['closed_accounts'] ?? []);
    $spouses = collect($personal['spouses'] ?? []);
    $related = collect($personal['related_persons'] ?? []);
    $addressHistory = collect($personal['address_history'] ?? []);
    $contactHistory = collect($personal['contact_history'] ?? []);
    $employmentHistory = collect($personal['employment_history'] ?? []);
    $ids = collect($personal['ids'] ?? []);
    $inquiries = collect($detail['inquiries'] ?? []);
    $inquirySummary = collect($detail['inquiries_summary'] ?? []);
    $buckets = collect($detail['overdue_buckets'] ?? []);
    $exposureProduct = collect($detail['exposure_by_product'] ?? []);
    $overview = $detail['overview'] ?? [];
    $crossCheck = $isGuarantor
        ? ($review['guarantor_row']['crb_cross_check'] ?? null)
        : ($review['crb_cross_check'] ?? null);
    if (! is_array($crossCheck)) {
        $crossCheck = null;
    }

    $kv = function (?string $label, mixed $value) {
        $display = filled($value) || $value === 0 || $value === '0' ? $value : '—';
        return [$label, $display];
    };
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $isGuarantor ? 'Guarantor' : ($isMember ? 'Member' : 'Borrower') }} · CRB</p>
            <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Credit bureau report</h2>
            <p class="text-xs text-gray-500 mt-0.5">Pulled after affordability / capacity pass — summaries first, then complete detail.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex text-xs font-bold rounded-full px-3 py-1 bg-brand-muted text-brand ring-1 ring-brand/15 uppercase">
                {{ $rec !== '' ? $rec : '—' }}
            </span>
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-gray-100 text-gray-700">
                Score {{ $crb['score'] ?? '—' }}
            </span>
            @if (! empty($crb['risk_grade']))
                <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-gray-100 text-gray-700 uppercase">
                    Grade {{ $crb['risk_grade'] }}
                </span>
            @endif
        </div>
    </div>

    <div class="p-5 space-y-8">
        <p class="text-sm text-gray-700">{{ $explain['summary'] ?? 'No CRB explanation available.' }}</p>

        {{-- Quick decision strip --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Other institutions</p>
                <p class="text-xl font-bold text-amber-950 mt-1">{{ $externalLoans }}</p>
                <p class="text-[11px] text-amber-900/80 mt-0.5">Active loans on CRB</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Outstanding</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ format_money($outstanding) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Delinquencies</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ $crb['delinquencies'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Freshness</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ $crb['freshness_label'] ?? '—' }}</p>
                @if ($crb['checked_at'] ?? null)
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $crb['checked_at']->diffForHumans() }}</p>
                @endif
            </div>
        </div>

        @php
            $identityFlags = collect(is_array($crossCheck) ? ($crossCheck['identity_flags'] ?? []) : []);
            $creditFlags = collect(is_array($crossCheck) ? ($crossCheck['credit_flags'] ?? []) : []);
            $allFlags = $identityFlags->merge($creditFlags);
            $matches = collect(is_array($crossCheck) ? ($crossCheck['matches'] ?? []) : []);
        @endphp

        @if ($allFlags->isNotEmpty() || $matches->isNotEmpty() || is_array($crossCheck))
            <div class="rounded-xl ring-1 ring-red-200 bg-red-50/60 px-4 py-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-red-800 font-semibold">Quick red flags</p>
                        <p class="text-sm font-semibold text-red-950 mt-0.5">Profile vs CRB · credit behaviour</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full px-2.5 py-1 bg-red-100 text-red-900 font-semibold">{{ (int) ($crossCheck['critical_count'] ?? $allFlags->where('severity', 'critical')->count()) }} critical</span>
                        <span class="rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 font-semibold">{{ (int) ($crossCheck['warning_count'] ?? $allFlags->where('severity', 'warning')->count()) }} warning</span>
                    </div>
                </div>
                <p class="text-xs text-red-900/80">{{ $crossCheck['photo_note'] ?? 'No portrait is returned from CRB — use borrower face / ID uploads.' }}</p>
                @if ($allFlags->isNotEmpty())
                    <ul class="space-y-2">
                        @foreach ($allFlags as $flag)
                            @php
                                $tone = match ($flag['severity'] ?? 'info') {
                                    'critical' => 'bg-red-100 text-red-900 ring-red-200',
                                    'warning' => 'bg-amber-100 text-amber-900 ring-amber-200',
                                    default => 'bg-white text-gray-800 ring-gray-200',
                                };
                            @endphp
                            <li class="rounded-lg ring-1 px-3 py-2 {{ $tone }}">
                                <p class="text-xs font-bold uppercase tracking-wide">{{ $flag['severity'] ?? 'info' }} · {{ $flag['title'] ?? 'Flag' }}</p>
                                <p class="text-sm mt-0.5">{{ $flag['detail'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-emerald-800 font-medium">No automatic red flags from profile cross-check or credit behaviour rules.</p>
                @endif
                @if ($matches->isNotEmpty())
                    <details class="rounded-lg bg-white/80 ring-1 ring-emerald-200 px-3 py-2">
                        <summary class="cursor-pointer text-xs font-semibold text-emerald-900">{{ $matches->count() }} field(s) matched profile</summary>
                        <ul class="mt-2 grid sm:grid-cols-2 gap-2 text-xs text-gray-700">
                            @foreach ($matches as $match)
                                <li><span class="font-semibold">{{ $match['label'] ?? $match['code'] }}:</span> {{ $match['profile'] ?? '—' }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        {{-- Personal / identity --}}
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Personal &amp; identity</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                @foreach ([
                    $kv('Full name', $personal['full_name'] ?? $crb['identity']['full_name'] ?? null),
                    $kv('First name', $personal['first_name'] ?? null),
                    $kv('Middle names', $personal['middle_names'] ?? null),
                    $kv('Surname', $personal['surname'] ?? null),
                    $kv('Gender', $personal['gender'] ?? $crb['identity']['gender'] ?? null),
                    $kv('Date of birth', $personal['date_of_birth'] ?? $crb['identity']['date_of_birth'] ?? null),
                    $kv('Nationality', $personal['nationality'] ?? null),
                    $kv('Country of birth', $personal['country_of_birth'] ?? null),
                    $kv('District of birth', $personal['district_of_birth'] ?? null),
                    $kv('Marital status', $personal['marital_status'] ?? null),
                    $kv('Number of spouses', $personal['number_of_spouses'] ?? null),
                    $kv('Number of children', $personal['number_of_children'] ?? null),
                    $kv('Education', $personal['education'] ?? null),
                    $kv('Profession', $personal['profession'] ?? null),
                    $kv('Employer', $personal['employer'] ?? null),
                    $kv('Mobile', $personal['mobile'] ?? null),
                    $kv('Current address', $personal['address'] ?? null),
                    $kv('NIDA (profile)', $crb['identity']['national_id'] ?? null),
                    $kv('Search score', $crb['search_score'] ?? $meta['search_score'] ?? null),
                    $kv('CRB RUID', $crb['crb_ruid'] ?? $meta['ruid'] ?? null),
                    $kv('CIR number', $meta['cir_number'] ?? null),
                ] as [$label, $value])
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ $label }}</p>
                        <p class="font-medium text-gray-900 mt-0.5 break-words">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if ($spouses->isNotEmpty() || $related->isNotEmpty())
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">Spouse &amp; related persons</p>
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($spouses as $spouse)
                            <li class="px-4 py-2.5 flex justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ $spouse['name'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500 uppercase">Spouse</span>
                            </li>
                        @endforeach
                        @foreach ($related as $person)
                            <li class="px-4 py-2.5 flex justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ $person['name'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500">{{ $person['relation'] ?? 'Related' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($ids->isNotEmpty())
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-700 mb-2">IDs on file</p>
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($ids as $id)
                            <li class="px-4 py-2.5 flex flex-wrap justify-between gap-2">
                                <span class="font-mono text-xs text-gray-900">{{ $id['id_number'] ?? '—' }}</span>
                                <span class="text-xs text-gray-500">{{ $id['id_type'] ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Address / contact / employment history --}}
        <div class="grid lg:grid-cols-3 gap-4">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Address history</h3>
                @if ($addressHistory->isEmpty())
                    <p class="text-sm text-gray-500">No address history on this pull.</p>
                @else
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($addressHistory as $row)
                            <li class="px-3 py-2.5">
                                <p class="font-medium text-gray-900">{{ $row['address'] ?? '—' }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['type'] ?? '—' }} · {{ $row['date_reported'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Contact history</h3>
                @if ($contactHistory->isEmpty())
                    <p class="text-sm text-gray-500">No contact history on this pull.</p>
                @else
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($contactHistory as $row)
                            <li class="px-3 py-2.5">
                                <p class="font-medium text-gray-900">{{ $row['detail'] ?? '—' }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['type'] ?? '—' }} · {{ $row['date_reported'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Employment history</h3>
                @if ($employmentHistory->isEmpty())
                    <p class="text-sm text-gray-500">No employment history on this pull.</p>
                @else
                    <ul class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white text-sm">
                        @foreach ($employmentHistory as $row)
                            <li class="px-3 py-2.5">
                                <p class="font-medium text-gray-900">{{ $row['employer'] ?? '—' }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['profession'] ?? '—' }} · {{ $row['date_reported'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Credit behaviour overview --}}
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Credit behaviour overview</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-4">
                @foreach ([
                    $kv('Accounts', $overview['accounts'] ?? $externalLoans),
                    $kv('Creditors', $overview['creditors'] ?? null),
                    $kv('Collateral count', $overview['collateral_count'] ?? null),
                    $kv('Most negative status', $overview['most_negative_status'] ?? $detail['most_negative_status'] ?? null),
                    $kv('Unpaid instal. 30d', $overview['unpaid_instal_30'] ?? null),
                    $kv('Unpaid instal. 60d', $overview['unpaid_instal_60'] ?? null),
                    $kv('Unpaid instal. 360d', $overview['unpaid_instal_360'] ?? null),
                    $kv('Loans guaranteed', $overview['loans_guaranteed'] ?? null),
                    $kv('Legal dispute accounts', $overview['legal_dispute_accounts'] ?? null),
                    $kv('Inquiries (FA)', $overview['inquiries_by_fa'] ?? null),
                ] as [$label, $value])
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ $label }}</p>
                        <p class="font-medium text-gray-900 mt-0.5">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if ($buckets->isNotEmpty())
                <p class="text-xs font-semibold text-gray-700 mb-2">Overdue aging buckets</p>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 mb-4">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Bucket</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($buckets as $bucket)
                                <tr>
                                    <td class="px-3 py-2">{{ $bucket['bucket'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($bucket['amount'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($exposureProduct->isNotEmpty())
                <p class="text-xs font-semibold text-gray-700 mb-2">Exposure by product</p>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Product</th>
                                <th class="px-3 py-2 text-left">Currency</th>
                                <th class="px-3 py-2 text-right">Not overdue</th>
                                <th class="px-3 py-2 text-right">Overdue</th>
                                <th class="px-3 py-2 text-right">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($exposureProduct as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['product'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $row['currency'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($row['not_overdue'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($row['amount_overdue'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $row['active_facilities'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Open / closed facilities --}}
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Open accounts</h3>
            @if ($openAccounts->isEmpty())
                <p class="text-sm text-gray-500 mb-4">No open accounts on this pull.</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 mb-6">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Lender</th>
                                <th class="px-3 py-2 text-left">Product</th>
                                <th class="px-3 py-2 text-right">Approved</th>
                                <th class="px-3 py-2 text-right">Outstanding</th>
                                <th class="px-3 py-2 text-right">Overdue</th>
                                <th class="px-3 py-2 text-right">Instalment</th>
                                <th class="px-3 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($openAccounts as $acc)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $acc['lender'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <p>{{ $acc['product'] ?? '—' }}</p>
                                        @if (! empty($acc['purpose']))
                                            <p class="text-[11px] text-gray-500">{{ $acc['purpose'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['approval_amount'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ format_money((float) ($acc['outstanding'] ?? $acc['balance'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['overdue'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['installment_amount'] ?? 0)) }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $acc['negative_status'] ?? 'open' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Closed accounts</h3>
            @if ($closedAccounts->isEmpty())
                <p class="text-sm text-gray-500">No closed accounts on this pull.</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Lender</th>
                                <th class="px-3 py-2 text-left">Product</th>
                                <th class="px-3 py-2 text-right">Sanctioned</th>
                                <th class="px-3 py-2 text-left">Activated</th>
                                <th class="px-3 py-2 text-left">Closed</th>
                                <th class="px-3 py-2 text-left">Phase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($closedAccounts as $acc)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $acc['lender'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $acc['product'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ format_money((float) ($acc['sanction_amount'] ?? 0)) }}</td>
                                    <td class="px-3 py-2">{{ $acc['activated_date'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $acc['closure_date'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $acc['phase'] ?? $acc['loan_status'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Inquiries --}}
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Inquiry history</h3>
            @if ($inquirySummary->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ($inquirySummary as $row)
                        <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-gray-100 text-gray-700">
                            {{ $row['institution_type'] ?? 'Institution' }}: {{ $row['count'] ?? 0 }}
                        </span>
                    @endforeach
                </div>
            @endif
            @if ($inquiries->isEmpty())
                <p class="text-sm text-gray-500">No inquiry details on this pull.</p>
            @else
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Purpose</th>
                                <th class="px-3 py-2 text-left">Institution type</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($inquiries as $inq)
                                <tr>
                                    <td class="px-3 py-2">{{ $inq['date'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $inq['purpose'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $inq['institution_type'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        @if (($inq['amount'] ?? 0) > 0)
                                            {{ format_money((float) $inq['amount']) }} {{ $inq['currency'] ?? '' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Compact loan history fallback --}}
        @if ($history->isNotEmpty() && $openAccounts->isEmpty())
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loans at other institutions</h3>
                <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                    @foreach ($history as $row)
                        <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $row['lender'] ?? $row['institution'] ?? 'Other lender' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 capitalize">{{ $row['status'] ?? '—' }} @if(!empty($row['product'])) · {{ $row['product'] }} @endif</p>
                            </div>
                            <p class="font-semibold text-gray-900">{{ format_money((float) ($row['balance'] ?? $row['outstanding'] ?? 0)) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
