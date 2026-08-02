<x-site.investor-layout title="Reports — Capital partner" active="documents">
    <x-site.borrower-page-header
        eyebrow="Capital partner"
        title="Reports & statements"
        subtitle="Download agreements, year-to-date statements and tax summaries."
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        @foreach ([
            ['Investor agreement', 'Master capital partner agreement', 'Download agreement', 'agreement'],
            ['Year-to-date', now()->year.' statement', 'Download statement', 'ytd'],
            ['Tax report', now()->subYear()->year.' tax summary', 'Download report', 'tax'],
        ] as [$eyebrow, $title, $cta, $kind])
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $eyebrow }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $title }}</p>
                <a href="{{ route('site.investor.documents.download', $kind) }}"
                   class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand px-3 py-1.5 text-sm font-semibold">{{ $cta }}</a>
            </div>
        @endforeach
    </div>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <div class="px-6 py-4 border-b border-brand/10 bg-brand-muted/20">
            <h2 class="font-bold text-gray-900">Statement history</h2>
        </div>
        @if ($statements->isEmpty())
            <div class="p-10 text-center text-gray-500 text-sm">
                No archived monthly statements yet. Use the downloads above for a live snapshot.
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-brand-muted/30 text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">Period</th>
                        <th class="text-right px-4 py-3 font-semibold">Opening</th>
                        <th class="text-right px-4 py-3 font-semibold">Investments</th>
                        <th class="text-right px-4 py-3 font-semibold">Returns</th>
                        <th class="text-right px-4 py-3 font-semibold">Withdrawals</th>
                        <th class="text-right px-4 py-3 font-semibold">Closing</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($statements as $s)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $s->period_start->format('d M') }} – {{ $s->period_end->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">TZS {{ $fmt($s->opening_balance) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">TZS {{ $fmt($s->investments_total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-brand">TZS {{ $fmt($s->returns_total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">TZS {{ $fmt($s->withdrawals_total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-bold">TZS {{ $fmt($s->closing_balance) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($s->file_path)
                                    <a href="{{ asset('storage/'.$s->file_path) }}" class="text-brand hover:underline text-xs font-semibold">Download</a>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $statements->links() }}</div>
</x-site.investor-layout>
