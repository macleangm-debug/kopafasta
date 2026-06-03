<x-admin.layout title="AML Reports" heading="AML Reports" subheading="Suspicious activity monitoring">

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('admin.compliance.large-transactions') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-3 py-1.5 rounded-lg">
            Large transactions
        </a>
        <a href="{{ route('admin.compliance.bot-portfolio-export') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-700 hover:bg-emerald-800 px-3 py-1.5 rounded-lg">
            Download BOT portfolio (Excel)
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Open',           $stats['open'],          'bg-amber-50 text-amber-700'],
                ['Investigating',  $stats['investigating'], 'bg-indigo-50 text-indigo-700'],
                ['Reported (FIU)', $stats['reported'],      'bg-rose-50 text-rose-700'],
                ['Closed',         $stats['closed'],        'bg-gray-50 text-gray-700'],
            ];
        @endphp
        @foreach ($cards as [$lbl, $val, $cls])
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ $lbl }}</div>
                <div class="mt-2 text-3xl font-bold {{ $cls }} inline-block rounded-md px-3">{{ format_number($val) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">By severity</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @foreach (['critical','high','medium','low'] as $sv)
                        <tr><td class="py-2 capitalize text-gray-600">{{ $sv }}</td><td class="py-2 text-right font-mono">{{ format_number($bySeverity[$sv] ?? 0) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 lg:col-span-2">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Recent activity ({{ $recent->count() }})</h3>
            <div class="overflow-x-auto -mx-2">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                        <tr><th class="px-2 py-2">Detected</th><th class="px-2 py-2">Activity</th><th class="px-2 py-2">Customer</th><th class="px-2 py-2">Severity</th><th class="px-2 py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recent as $r)
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 py-2 text-xs text-gray-500">{{ optional($r->detected_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-2 py-2"><a href="{{ route('admin.compliance.suspicious.show', $r) }}" class="hover:text-indigo-600">{{ $r->activity_type }}</a></td>
                                <td class="px-2 py-2">{{ $r->customer ? trim(($r->customer->first_name ?? '').' '.($r->customer->last_name ?? '')) : '—' }}</td>
                                <td class="px-2 py-2 capitalize">{{ $r->severity }}</td>
                                <td class="px-2 py-2 capitalize">{{ $r->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-2 py-6 text-center text-gray-500">No suspicious activity recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin.layout>
