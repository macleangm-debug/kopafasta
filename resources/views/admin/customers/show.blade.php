@php
    $customer = $dossier['customer'];
    $profile = $dossier['profile'];
    $standing = $dossier['repayment_standing'] ?? [];
    $crb = $dossier['crb'] ?? [];
    $initials = strtoupper(substr($customer->first_name ?? '?', 0, 1).substr($customer->last_name ?? '', 0, 1));
    $activeLoans = $dossier['loans']->whereIn('status', ['active', 'arrears', 'disbursed', 'restructuring']);
    $outstanding = (float) $activeLoans->sum(fn ($l) => (float) ($l->outstanding_balance ?? 0));

    $tabs = [
        ['overview', 'Overview'],
        ['about', 'About you'],
        ['residence', 'Where you live'],
        ['activity', 'What you do'],
        ['payment', 'Payment account'],
        ['assets', 'Assets'],
        ['applications', 'Applications'],
        ['loans', 'Loans'],
        ['payments', 'Payments'],
    ];
    $tab = request('tab', 'overview');
    if ($tab === 'signature') {
        $tab = 'about'; // Signature lives under About you
    }
    if (! in_array($tab, array_column($tabs, 0), true)) {
        $tab = 'overview';
    }
    $tabUrl = fn (string $key) => route('admin.customers.show', ['customer' => $customer, 'tab' => $key]).'#member-file';
@endphp

<x-admin.layout
    :title="$customer->full_name"
    heading=""
    :backUrl="route('admin.customers.index')"
    backLabel="All customers">

@if (filled($customer->merged_into_customer_id))
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Merged / inactive duplicate</p>
            <p class="mt-1">
                This member file was merged into
                <a href="{{ route('admin.customers.show', $customer->merged_into_customer_id) }}" class="font-semibold underline">
                    member #{{ $customer->merged_into_customer_id }}
                </a>
                @if ($customer->merged_at)
                    on {{ $customer->merged_at->format('d M Y H:i') }}
                @endif
                @if ($customer->merge_reason)
                    · {{ $customer->merge_reason }}
                @endif
                Financial and application history was moved or left on file — nothing was hard-deleted.
            </p>
        </div>
    @endif

@if ($customer->status === 'pending')
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Incomplete registration — not a Member yet</p>
            <p class="mt-1">
                They can resume with the phone already on file.
                @if ($customer->phone)
                    Contact: <span class="font-semibold tabular-nums">{{ $customer->phone }}</span>
                    · {{ $customer->full_name }}
                @endif
                Encouraging them to finish registration does not activate membership until they complete it themselves.
            </p>
        </div>
    @endif

@if ($customer->nida_locked_until && $customer->nida_locked_until->isFuture())
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold">NIDA verification locked</p>
                <p class="mt-1">Locked until {{ $customer->nida_locked_until->format('d M Y H:i') }} · {{ (int) $customer->nida_mismatch_attempts }} mismatch attempt(s)</p>
            </div>
            <form method="POST" action="{{ route('admin.customers.nida.unlock', $customer) }}">
                @csrf
                <button type="submit" class="inline-flex text-sm font-semibold bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                    Unlock identity
                </button>
            </form>
        </div>
    @endif

    {{-- Letterhead --}}
    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="shrink-0 size-16 sm:size-20 rounded-2xl overflow-hidden ring-2 ring-white/25 bg-white/10 grid place-items-center">
                        @if ($dossier['face_photo_url'] ?? null)
                            <img src="{{ $dossier['face_photo_url'] }}" alt="{{ $customer->full_name }}" class="size-full object-cover">
                        @else
                            <span class="text-xl font-bold text-brand-gold">{{ $initials }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Member profile</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $customer->full_name }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $customer->customer_number }}
                            @if ($customer->member_no)
                                <span class="text-white/50">·</span> Member {{ $customer->member_no }}
                            @endif
                            @if ($customer->branch?->name)
                                <span class="text-white/50">·</span> {{ $customer->branch->name }}
                            @endif
                        </p>
                        <p class="text-xs text-white/70 mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                            <span>DOB {{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</span>
                            <span>{{ ucfirst($customer->gender ?? '—') }}</span>
                            @if ($customer->phone)<span>{{ $customer->phone }}</span>@endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                        {{ ucfirst($customer->status ?? 'unknown') }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                        Read-only
                    </span>
                </div>
            </div>
            @if ($dossier['profile_incomplete'] ?? false)
                <p class="mt-3 text-xs font-semibold text-amber-100">
                    Incomplete profile ({{ $profile['percent'] }}%) —
                    @php
                        $gapBits = collect($dossier['incomplete_sections'] ?? [])
                            ->flatMap(fn ($s) => $s['gap_labels'] ?? [])
                            ->filter()
                            ->unique()
                            ->take(6)
                            ->values();
                    @endphp
                    {{ $gapBits->isNotEmpty() ? $gapBits->implode(', ') : collect($dossier['incomplete_sections'] ?? [])->pluck('label')->filter()->take(4)->implode(', ') }}
                </p>
            @endif
        </div>
    </div>

    {{-- Top cards (screening-style decision deck) --}}
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div @class([
            'rounded-2xl p-5 text-white shadow-sm bg-gradient-to-br',
            'from-amber-500 to-amber-700' => $dossier['profile_incomplete'] ?? false,
            'from-emerald-600 to-emerald-800' => ! ($dossier['profile_incomplete'] ?? false),
        ])>
            <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Profile</p>
            <p class="text-3xl font-bold mt-2 tabular-nums">{{ $profile['percent'] }}%</p>
            <p class="text-sm text-white/85 mt-2">{{ ($dossier['profile_incomplete'] ?? false) ? 'Incomplete' : 'Complete' }}</p>
        </div>
        <div class="rounded-2xl p-5 text-white shadow-sm bg-gradient-to-br from-brand to-brand-light">
            <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Trust · repayment</p>
            <p class="text-3xl font-bold mt-2 tabular-nums">{{ $standing['trust_percent'] ?? 0 }}%</p>
            <p class="text-sm text-white/85 mt-2">{{ $standing['label'] ?? '—' }} · streak {{ $standing['streak'] ?? 0 }}</p>
        </div>
        <div @class([
            'rounded-2xl p-5 text-white shadow-sm bg-gradient-to-br',
            'from-emerald-600 to-emerald-800' => $customer->isMembershipActive() || $customer->isMembershipInGrace(),
            'from-rose-600 to-rose-800' => ! ($customer->isMembershipActive() || $customer->isMembershipInGrace()),
        ])>
            <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Membership</p>
            <p class="text-2xl font-bold mt-2">{{ $customer->isMembershipActive() ? 'Active' : ($customer->isMembershipInGrace() ? 'Grace' : 'Inactive') }}</p>
            <p class="text-sm text-white/85 mt-2">
                {{ optional($customer->membership_expires_at)->format('d M Y') ?? 'No expiry on file' }}
            </p>
        </div>
        <div class="rounded-2xl p-5 shadow-sm ring-1 ring-brand/10 bg-white">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Portfolio</p>
            <p class="text-3xl font-bold mt-2 text-gray-900 tabular-nums">{{ $activeLoans->count() }}</p>
            <p class="text-sm text-gray-500 mt-2">Active loans · {{ format_money($outstanding) }} out</p>
            @if ($crb['available'] ?? false)
                <p class="text-xs text-gray-500 mt-2">CRB {{ $crb['score'] ?? '—' }} · {{ ($crb['fresh'] ?? false) ? 'Fresh' : 'Stale' }}</p>
            @endif
        </div>
    </div>

    {{-- Tabbed member file --}}
    <section id="member-file" class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden scroll-mt-24">
        <div class="px-5 pt-5 pb-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white">
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Member file</p>
            <h3 class="text-base font-bold text-gray-900 mt-0.5">Member 360</h3>
            <p class="text-xs text-gray-500 mt-0.5">Staff view of what Kopafasta knows about this member — organized like the borrower Profile.</p>

            <div class="mt-4 flex gap-1 overflow-x-auto pb-1" role="tablist">
                @foreach ($tabs as [$key, $label])
                    <a href="{{ $tabUrl($key) }}"
                       @class([
                           'shrink-0 px-3 py-2 text-xs font-semibold rounded-lg transition ring-1',
                           'bg-brand text-white ring-brand shadow-sm' => $tab === $key,
                           'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40' => $tab !== $key,
                       ])>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="p-5 sm:p-6">
            @include('admin.customers.dossier._tab-'.$tab)
        </div>
    </section>

    @if (empty($customer->merged_into_customer_id) && auth('admin')->user()?->can('delete', $customer))
        @php $duplicateImpact = app(\App\Services\DuplicateMemberResolutionService::class)->impact($customer); @endphp
        <section class="mt-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6">
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Resolve duplicate account</p>
            <p class="mt-1 text-sm text-gray-600">
                Choose the canonical member. Empty accounts can be retired. Accounts with payments, applications, or documents are merged — history stays, this member ID remains as Merged into the canonical file.
            </p>
            <p class="mt-2 text-xs text-gray-500">
                This file: {{ $duplicateImpact['payments'] }} payments
                ({{ $duplicateImpact['settled_payments'] }} settled) ·
                {{ $duplicateImpact['applications'] }} applications ·
                {{ $duplicateImpact['drafts'] }} drafts ·
                planned action: <span class="font-semibold">{{ $duplicateImpact['action'] === 'delete_empty' ? 'Retire empty' : 'Merge history' }}</span>
            </p>
            <form method="POST"
                  action="{{ route('admin.customers.resolve-duplicate', $customer) }}"
                  class="mt-4 grid sm:grid-cols-2 gap-3"
                  @submit.prevent="window.confirmForm($el, {
                      title: {{ \Illuminate\Support\Js::from($duplicateImpact['action'] === 'delete_empty' ? 'Retire this empty duplicate?' : 'Merge this account into the canonical member?') }},
                      message: {{ \Illuminate\Support\Js::from($duplicateImpact['action'] === 'delete_empty'
                          ? 'This account has no payments or applications. It will be marked inactive and linked to the canonical member. Financial history is not deleted because none exists.'
                          : 'Payments, applications, and documents move to the canonical member. This member ID stays on file as Merged. Nothing is hard-deleted.') }},
                      confirmLabel: {{ \Illuminate\Support\Js::from($duplicateImpact['action'] === 'delete_empty' ? 'Retire empty account' : 'Merge into canonical') }},
                  })">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Canonical member ID</label>
                    <input type="number" name="canonical_customer_id" required min="1"
                           class="w-full rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                           placeholder="e.g. 256">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Reason</label>
                    <input type="text" name="reason" required maxlength="180"
                           class="w-full rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                           placeholder="Same person, different phone">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex rounded-xl bg-brand text-white font-semibold px-4 py-2.5 text-sm">
                        Review and resolve
                    </button>
                </div>
            </form>
        </section>
    @elseif (filled($customer->merged_into_customer_id))
        <section class="mt-5 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
            Merged into
            <a href="{{ route('admin.customers.show', $customer->merged_into_customer_id) }}" class="font-semibold underline">
                member #{{ $customer->merged_into_customer_id }}
            </a>
            @if ($customer->merged_at)
                on {{ $customer->merged_at->format('d M Y H:i') }}
            @endif
            @if ($customer->merge_reason)
                · {{ $customer->merge_reason }}
            @endif
        </section>
    @endif
</x-admin.layout>
