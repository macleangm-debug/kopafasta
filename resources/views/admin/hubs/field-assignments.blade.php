<x-admin.layout title="Recovery & Partner Assignments" heading="" subheading="">

    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Field &amp; recovery</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Recovery &amp; Partner Assignments</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Recovery cases plus valuer, insurance, and GPS partner work — not capital partners.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Recovery open</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['recovery_open']) }}</p>
                    @if ($counts['recovery_sla'] > 0)
                        <p class="text-xs text-rose-700 mt-2 font-medium">{{ number_format($counts['recovery_sla']) }} past SLA</p>
                    @endif
                </div>
                <div class="rounded-xl bg-violet-50 ring-1 ring-violet-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-violet-800 font-semibold">Valuer tasks</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['valuer_open']) }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">GPS partner</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['gps_open']) }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Insurance partner</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['insurance_open']) }}</p>
                </div>
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4 sm:col-span-2 lg:col-span-2">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">All open partner tasks</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['partner_tasks_open']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <a href="{{ route('admin.recovery.assignments.index') }}"
           class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 hover:ring-brand/25 transition">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Recovery</p>
            <p class="text-lg font-bold text-gray-900 mt-1">Recovery assignments</p>
            <p class="text-sm text-gray-600 mt-1">Field recovery cases and SLA tracking.</p>
        </a>
        <a href="{{ route('admin.partners.tasks') }}"
           class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 hover:ring-brand/25 transition">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Partner work</p>
            <p class="text-lg font-bold text-gray-900 mt-1">Partner tasks</p>
            <p class="text-sm text-gray-600 mt-1">Valuer, GPS, insurance, and other field tasks.</p>
        </a>
        <a href="{{ route('admin.partners.valuers') }}"
           class="rounded-2xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-brand/20 transition">
            <p class="text-sm font-bold text-gray-900">Valuers</p>
            <p class="text-xs text-gray-500 mt-1">Partner directory · valuer role</p>
        </a>
        <a href="{{ route('admin.partners.gps-installers') }}"
           class="rounded-2xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-brand/20 transition">
            <p class="text-sm font-bold text-gray-900">GPS partners</p>
            <p class="text-xs text-gray-500 mt-1">Partner directory · GPS installers</p>
        </a>
        <a href="{{ route('admin.partners.insurance-providers') }}"
           class="rounded-2xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-brand/20 transition">
            <p class="text-sm font-bold text-gray-900">Insurance partners</p>
            <p class="text-xs text-gray-500 mt-1">Partner directory · insurers</p>
        </a>
        <a href="{{ route('admin.recovery.partners.index') }}"
           class="rounded-2xl bg-white ring-1 ring-gray-200 shadow-sm p-5 hover:ring-brand/20 transition">
            <p class="text-sm font-bold text-gray-900">Recovery partners</p>
            <p class="text-xs text-gray-500 mt-1">Partners assigned to recovery work</p>
        </a>
    </div>

</x-admin.layout>
