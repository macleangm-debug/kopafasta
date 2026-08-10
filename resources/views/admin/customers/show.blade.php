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
        ['personal', 'Personal'],
        ['face', 'Face'],
        ['residence', 'Residence'],
        ['activity', 'Activity'],
        ['kin', 'Next of kin'],
        ['documents', 'Documents'],
        ['loans', 'Loans'],
        ['payments', 'Payments'],
        ['guarantors', 'Guarantors'],
    ];
    $tab = request('tab', 'overview');
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
                    Incomplete profile ({{ $profile['percent'] }}%) — missing
                    {{ collect($dossier['incomplete_sections'] ?? [])->pluck('label')->filter()->take(4)->implode(', ') ?: 'required sections' }}.
                    Borrower updates this in the app.
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
            <h3 class="text-base font-bold text-gray-900 mt-0.5">Profile sections</h3>
            <p class="text-xs text-gray-500 mt-0.5">Read-only reference — KYC and document requests happen on the loan application.</p>

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
</x-admin.layout>
