<x-admin.layout title="Recovery Partners" heading="" subheading="">
    <x-admin.letterhead kicker="Recovery" title="Recovery partners" subtitle="Call center, debt collector (incl. repossession), auctioneer, legal, and GPS partners" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-3xl">
            Assign recovery partners to collection cases with SLA tracking, commission from original outstanding, and portal access for case updates.
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.recovery.assignments.index') }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800 font-semibold px-4 py-2 rounded-lg text-sm">
                Recovery assignments
            </a>
            <a href="{{ route('admin.settings.recovery') }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800 font-semibold px-4 py-2 rounded-lg text-sm">
                Recovery policy
            </a>
        </div>
    </div>

    <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 p-5">
        <h2 class="text-sm font-semibold text-amber-900">How recovery ops fit together</h2>
        <ul class="mt-3 grid sm:grid-cols-2 gap-3 text-sm text-amber-900/90">
            <li class="flex gap-2">
                <span class="font-semibold shrink-0">Recovery partners</span>
                <span>— this directory, organized by partner type (call center, debt collector, auctioneer, legal, GPS).</span>
            </li>
            <li class="flex gap-2">
                <span class="font-semibold shrink-0">Recovery assignments</span>
                <span>— individual collection cases assigned to a partner, tracked against SLA deadlines.</span>
            </li>
            <li class="flex gap-2">
                <span class="font-semibold shrink-0">Recovery policy</span>
                <span>— escalation rules that decide when and to whom a case is escalated.</span>
            </li>
            <li class="flex gap-2">
                <span class="font-semibold shrink-0">Escalation path</span>
                <span>— GPS partners locate the asset first; debt collection partners escalate cases to them when tracking is needed.</span>
            </li>
        </ul>
        <p class="mt-3 text-xs text-amber-800/80">
            Roles aren't exclusive — a single partner can hold multiple roles at once (e.g. a debt collector can also be assigned the auctioneer role).
        </p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($summary as $row)
            <a href="{{ route('admin.recovery.partners.type', $row['type']) }}"
               class="rounded-xl bg-white ring-1 ring-gray-200 p-5 hover:ring-amber-300 transition">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ $row['label'] }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $row['count'] }}</p>
                <p class="mt-1 text-xs text-amber-700 font-semibold">Manage partners →</p>
            </a>
        @endforeach
    </div>
</x-admin.layout>
