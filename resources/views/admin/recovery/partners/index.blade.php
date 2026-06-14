<x-admin.layout title="Recovery Partners" heading="Recovery Partners" subheading="External partners for collections, repossession, auction, legal, and GPS recovery">

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
