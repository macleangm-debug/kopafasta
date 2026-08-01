@php
    $customer = $dossier['customer'];
    $profile = $dossier['profile'];
    $initials = strtoupper(substr($customer->first_name ?? '?', 0, 1).substr($customer->last_name ?? '', 0, 1));
@endphp

<x-admin.layout
    :title="$customer->full_name"
    :heading="$customer->full_name"
    :subheading="$customer->customer_number"
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

    {{-- Hero --}}
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white shadow-lg overflow-hidden">
        <div class="p-6 sm:p-8 flex flex-col lg:flex-row lg:items-center gap-6">
            <div class="size-20 rounded-2xl bg-brand-gold text-brand font-bold text-2xl grid place-items-center shadow-lg shrink-0">
                {{ $initials }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded">{{ $customer->customer_number }}</span>
                    @if ($customer->member_no)
                        <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded">Member {{ $customer->member_no }}</span>
                    @endif
                    <span class="text-xs font-semibold uppercase tracking-widest px-2 py-0.5 rounded-full {{ $customer->status === 'active' ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10' }}">
                        {{ ucfirst($customer->status ?? 'unknown') }}
                    </span>
                </div>
                <p class="text-sm text-gray-300">{{ $customer->phone }} · {{ $customer->email ?: 'No email' }}</p>
                <p class="text-sm text-gray-400 mt-1">{{ $customer->branch?->name ?? 'No branch assigned' }}</p>
            </div>
            <div class="flex flex-wrap gap-2 lg:flex-col lg:items-end">
                <a href="{{ route('admin.loan-applications.create') }}?customer={{ $customer->id }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold bg-brand-gold hover:brightness-95 text-brand px-4 py-2 rounded-lg">
                    New application
                </a>
                @if ($customer->face_verification_status === 'pending')
                    <a href="{{ route('admin.face-verifications.show', $customer) }}"
                       class="inline-flex text-sm font-semibold bg-white/10 hover:bg-white/15 px-4 py-2 rounded-lg">
                        Review face verification
                    </a>
                @endif
                <a href="{{ route('admin.customers.edit', $customer) }}"
                   class="inline-flex text-sm font-medium text-gray-300 hover:text-white px-2 py-1">
                    Advanced edit →
                </a>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 border-t border-white/10 bg-black/20">
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">Profile complete</p>
                <p class="text-2xl font-bold mt-1">{{ $profile['percent'] }}%</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">NIDA</p>
                <p class="text-sm font-semibold mt-2">{{ $dossier['nida_verified'] ? '✓ Verified' : 'Pending' }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">Face</p>
                <p class="text-sm font-semibold mt-2">{{ display_label($customer->face_verification_status ?? 'none', 'face_verification_status') }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">Documents</p>
                <p class="text-sm font-semibold mt-2">{{ $dossier['documents']->count() }} on file
                    @if ($dossier['pending_documents'] > 0)
                        <span class="text-amber-300">· {{ $dossier['pending_documents'] }} pending</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    @include('admin.customers.dossier._nav')

    <div class="space-y-6">
        @include('admin.customers.dossier._overview')
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
