<x-admin.layout title="Partner support" heading="" subheading="">
    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Partners desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Partner support</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Regional coverage, enrollment, and portal access for valuers, GPS, insurance, and other partners. Screening asks; this team acts.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-3 gap-4">
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Coverage gaps</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['gaps'] }}</p>
                </div>
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">On this team</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $members->count() }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Active partners</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['partners'] }}</p>
                </div>
            </div>
        </div>
    </section>

    @include('admin.partners._support_duties')

    <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.partners.index') }}"
           class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
            Open partners hub
        </a>
        <a href="{{ route('admin.partners.tasks') }}"
           class="inline-flex items-center gap-2 bg-white hover:bg-brand-muted/40 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl ring-1 ring-brand/20">
            Partner tasks
        </a>
        @if (auth()->user()?->hasPermission('users.manage'))
            <a href="{{ route('admin.users.create', ['role' => 'partner_support']) }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
                Add Partner support user →
            </a>
        @endif
    </div>

    @if ($coverageAlerts->isNotEmpty())
        <div class="mt-6 space-y-2">
            @foreach ($coverageAlerts as $alert)
                <a href="{{ $alert['url'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 hover:ring-amber-300">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Coverage gap</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $alert['label'] }}</p>
                    </div>
                    <span class="text-sm font-semibold text-brand">Review →</span>
                </a>
            @endforeach
        </div>
    @endif

    <section class="mt-6 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Roster</p>
            <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Partner support team</h2>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse ($members as $user)
                <li class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $user->email }} · {{ display_label($user->role, 'role') }}</p>
                    </div>
                    @if (auth()->user()?->hasPermission('users.manage'))
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Edit</a>
                    @endif
                </li>
            @empty
                <li class="px-5 py-8 text-sm text-gray-500 text-center">No Partner support users yet. Create one with role Partner support (PRT is assigned automatically).</li>
            @endforelse
        </ul>
    </section>
</x-admin.layout>
