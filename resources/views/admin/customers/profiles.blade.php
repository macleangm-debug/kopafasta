<x-admin.layout title="Profile management" heading="Profiles" subheading="Operate incomplete customer profiles. Completion rules stay in Settings.">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        @foreach ([
            'attention' => 'Needs attention',
            'incomplete' => 'Incomplete',
            'documents' => 'Missing documents',
            'complete' => 'Complete',
        ] as $key => $label)
            <a href="{{ route('admin.customers.profiles', ['bucket' => $key, 'q' => $q]) }}"
               class="rounded-2xl bg-white ring-1 {{ $bucket === $key ? 'ring-brand' : 'ring-brand/10' }} p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($counts[$key] ?? 0) }}</p>
            </a>
        @endforeach
    </div>

    <form method="get" class="mb-4 flex flex-col sm:flex-row gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Name, phone or customer number"
               class="flex-1 rounded-xl border-gray-300 text-sm px-3.5 py-2.5">
        <input type="hidden" name="bucket" value="{{ $bucket }}">
        <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Search</button>
    </form>

    @if ($focused)
        @php $customer = $focused['customer']; @endphp
        <section class="rounded-2xl bg-white ring-1 ring-brand/20 p-5 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Profile queue</p>
                    <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $customer->full_name }}</h2>
                    <p class="text-xs text-gray-500">{{ $customer->customer_number }} · {{ $customer->phone }}</p>
                </div>
                <p class="text-2xl font-bold tabular-nums text-brand">{{ $focused['percent'] }}%</p>
            </div>
            <ul class="mt-4 grid sm:grid-cols-2 gap-2 text-sm">
                @forelse ($focused['tabs'] as $key => $tab)
                    <li class="rounded-xl px-3 py-2 ring-1 {{ ($tab['complete'] ?? false) ? 'ring-emerald-100 bg-emerald-50 text-emerald-900' : 'ring-amber-100 bg-amber-50 text-amber-950' }}">
                        {{ $tab['label'] ?? $key }} · {{ ($tab['complete'] ?? false) ? 'Done' : 'Needs work' }}
                    </li>
                @empty
                    @foreach ($focused['missing'] as $label)
                        <li class="rounded-xl px-3 py-2 ring-1 ring-amber-100 bg-amber-50 text-amber-950">{{ $label }}</li>
                    @endforeach
                @endforelse
            </ul>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2">Open customer file</a>
                <a href="{{ route('admin.customers.profiles', ['bucket' => $bucket, 'q' => $q]) }}" class="inline-flex rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold px-4 py-2">Close panel</a>
            </div>
            <p class="mt-3 text-xs text-gray-500">Same ProfileCompletionService as the borrower hub. This queue does not invent another profile model.</p>
        </section>
    @endif

    <div class="hidden md:block overflow-x-auto rounded-2xl bg-white ring-1 ring-brand/10">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Profile</th>
                    <th class="px-4 py-3">Missing</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $customer = $row['customer']; @endphp
                    <tr class="border-t border-gray-100 {{ (int) ($focused['customer']->id ?? 0) === (int) $customer->id ? 'bg-brand-muted/30' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $customer->full_name }}</p>
                            <p class="text-xs text-gray-500">{{ $customer->customer_number }} · {{ $customer->phone }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-bold tabular-nums text-brand">{{ $row['percent'] }}%</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $row['missing'] ? implode(', ', array_slice($row['missing'], 0, 3)) : 'Nothing missing' }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.customers.profiles', ['bucket' => $bucket, 'q' => $q, 'focus' => $customer->id]) }}" class="text-brand font-semibold hover:underline">Work this profile</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                            @if ($q !== '')
                                No matching customers in this list.
                            @elseif ($bucket === 'complete')
                                No complete profiles in the current queue.
                            @else
                                Nobody currently needs profile attention in this queue.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($rows as $row)
            @php $customer = $row['customer']; @endphp
            <a href="{{ route('admin.customers.profiles', ['bucket' => $bucket, 'q' => $q, 'focus' => $customer->id]) }}"
               class="block rounded-2xl bg-white ring-1 ring-gray-200 p-4">
                <p class="font-semibold text-gray-900">{{ $customer->full_name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $customer->customer_number }}</p>
                <p class="mt-2 text-sm">Profile <strong class="tabular-nums">{{ $row['percent'] }}%</strong></p>
                <p class="text-sm text-gray-600 mt-1">Missing: {{ $row['missing'] ? implode(', ', array_slice($row['missing'], 0, 3)) : 'nothing' }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-500">
                @if ($q !== '')
                    No matching customers.
                @else
                    Nobody currently needs profile attention.
                @endif
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $page->links() }}</div>
</x-admin.layout>
