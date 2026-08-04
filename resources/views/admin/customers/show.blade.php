@php
    $customer = $dossier['customer'];
    $profile = $dossier['profile'];
    $engagement = $dossier['engagement'] ?? [];
    $standing = $dossier['repayment_standing'] ?? [];
    $crb = $dossier['crb'] ?? [];
    $initials = strtoupper(substr($customer->first_name ?? '?', 0, 1).substr($customer->last_name ?? '', 0, 1));
@endphp

<x-admin.layout
    :title="$customer->full_name"
    heading=""
    :backUrl="route('admin.customers.index')"
    backLabel="All customers">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
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

    {{-- Premium member letterhead (mirrors screening credit-file layout) --}}
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
                            <span>Gender {{ ucfirst($customer->gender ?? '—') }}</span>
                            @if ($customer->phone)
                                <span>{{ $customer->phone }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                        {{ ucfirst($customer->status ?? 'unknown') }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ ($dossier['profile_incomplete'] ?? false) ? 'bg-amber-400/20 text-amber-100 ring-1 ring-amber-300/40' : 'bg-emerald-400/20 text-emerald-100 ring-1 ring-emerald-300/40' }}">
                        Profile {{ $profile['percent'] }}%
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                        Read-only
                    </span>
                </div>
            </div>
            @if ($dossier['profile_incomplete'] ?? false)
                <p class="mt-3 text-xs font-semibold text-amber-100">
                    Incomplete profile — missing
                    {{ collect($dossier['incomplete_sections'] ?? [])->pluck('label')->filter()->take(4)->implode(', ') ?: 'required sections' }}.
                    The borrower updates this on their side.
                </p>
            @endif
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 bg-white border-t border-brand/10">
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">NIDA</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ $dossier['nida_verified'] ? 'Verified' : 'Pending' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Face</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ display_label($customer->face_verification_status ?? 'none', 'face_verification_status') }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Trust</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ $standing['trust_stars'] ?? 0 }}/{{ $standing['trust_max'] ?? 5 }} · {{ $standing['label'] ?? '—' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Loyalty points</p>
                <p class="text-sm font-bold text-gray-900 mt-1 tabular-nums">{{ number_format((int) ($standing['loyalty_points'] ?? 0)) }}</p>
            </div>
        </div>
    </div>

    @include('admin.customers.dossier._nav')

    <div class="space-y-6">
        @include('admin.customers.dossier._overview')
        @include('admin.customers.dossier._standing')
        @include('admin.customers.dossier._personal')
        @include('admin.customers.dossier._residence')
        @include('admin.customers.dossier._activity')
        @include('admin.customers.dossier._kin')
        @include('admin.customers.dossier._documents')
        @include('admin.customers.dossier._applications')
        @include('admin.customers.dossier._loans')
        @include('admin.customers.dossier._payments')
        @include('admin.customers.dossier._notifications')
        @include('admin.customers.dossier._guarantor_requests')
    </div>
</x-admin.layout>
