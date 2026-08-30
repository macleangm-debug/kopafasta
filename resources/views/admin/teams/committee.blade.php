@php
    $guided = app(\App\Services\GuidedApprovalService::class);
    $open = \App\Models\LoanApplication::query()
        ->with(['customer', 'product'])
        ->where(function ($q) {
            $q->where('current_stage', 'pre_approval')
                ->orWhere(function ($inner) {
                    $inner->where('current_stage', 'screening')
                        ->whereNotNull('screening_payload->guided->committee_clarification');
                });
        })
        ->orderByDesc('updated_at')
        ->limit(80)
        ->get();
    $queue = $guided->committeeQueue($open);
    $tab = request('bucket', 'do_now');
    if (! in_array($tab, ['do_now', 'waiting', 'completed'], true)) {
        $tab = 'do_now';
    }
@endphp
<x-admin.layout title="Credit committee" heading="" subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Credit committee</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Screening already verified the file. Scan exceptions, then approve, return for clarification, or reject.
                </p>
            </div>
            <div class="bg-white px-4 sm:px-6 py-4 flex flex-wrap gap-2">
                @foreach ([
                    'do_now' => 'Do now · '.count($queue['do_now']),
                    'waiting' => 'Waiting · '.count($queue['waiting']),
                    'completed' => 'Completed · '.count($queue['completed']),
                ] as $key => $label)
                    <a href="{{ route('admin.teams.committee', ['bucket' => $key]) }}"
                       @class([
                           'inline-flex rounded-xl px-4 py-2 text-sm font-bold ring-1',
                           'bg-brand text-white ring-brand' => $tab === $key,
                           'bg-white text-slate-800 ring-slate-200' => $tab !== $key,
                       ])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <div class="space-y-3">
        @forelse ($queue[$tab] as $row)
            @php $next = $row['next']; $app = $row['application']; @endphp
            <a href="{{ $next['href'] }}"
               class="block rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4 hover:ring-brand/30">
                <p class="text-sm font-bold text-slate-900">{{ $app->application_number }}</p>
                <p class="text-xs text-slate-600 mt-0.5">{{ $app->partyLabel() }} · {{ format_money((float) $app->requested_amount) }}</p>
                <p class="text-sm text-slate-800 mt-2">{{ $next['what_happens_next'] }}</p>
                <p class="text-xs font-bold text-brand mt-2">{{ $next['cta'] }}</p>
            </a>
        @empty
            <p class="text-sm text-slate-600">Nothing in this list.</p>
        @endforelse
    </div>

    <p class="mt-6">
        <a href="{{ route('admin.loan-applications.pre-approvals') }}" class="text-xs font-semibold text-slate-600 underline">Classic committee queue</a>
    </p>

</x-admin.layout>
