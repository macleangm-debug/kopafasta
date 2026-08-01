<x-admin.layout title="Marketing attribution" heading="Affiliate marketing attribution" subheading="UTM and campaign performance from referral events">

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2 rounded-lg">Apply</button>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ([
            ['Clicks', $report['totals']['clicks']],
            ['Registrations', $report['totals']['registrations']],
            ['Applications', $report['totals']['applications']],
            ['Click → Reg %', number_format($report['funnel']['click_to_registration'], 1).'%'],
        ] as [$label, $value])
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                <p class="text-[10px] uppercase text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        @foreach ([
            'UTM sources' => $report['by_source'],
            'Campaigns' => $report['by_campaign'],
            'UTM medium' => $report['by_medium'],
            'Devices' => $report['by_device'],
        ] as $title => $rows)
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ $title }}</h3>
                @forelse ($rows as $label => $count)
                    <div class="flex justify-between text-sm py-1 border-b border-gray-50 last:border-0">
                        <span class="text-gray-700">{{ $label }}</span>
                        <span class="font-semibold">{{ format_number($count) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No data in range.</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold">Daily funnel trend</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Day</th>
                    <th class="px-5 py-3">Clicks</th>
                    <th class="px-5 py-3">Registrations</th>
                    <th class="px-5 py-3">Applications</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($report['daily'] as $day)
                    <tr>
                        <td class="px-5 py-3">{{ $day['day'] }}</td>
                        <td class="px-5 py-3">{{ $day['clicks'] }}</td>
                        <td class="px-5 py-3">{{ $day['registrations'] }}</td>
                        <td class="px-5 py-3">{{ $day['applications'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">No events in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
