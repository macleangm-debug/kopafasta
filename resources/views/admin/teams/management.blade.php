@php
    $guided = app(\App\Services\GuidedApprovalService::class);
    $open = \App\Models\LoanApplication::query()
        ->with(['customer', 'product', 'loan'])
        ->whereIn('current_stage', ['awaiting_management', 'approval', 'disbursement'])
        ->whereNotIn('status', ['rejected', 'withdrawn', 'cancelled', 'expired'])
        ->orderByDesc('updated_at')
        ->limit(80)
        ->get();
    $queue = $guided->managementQueue($open);
    $tab = request('bucket', 'do_now');
    if (! in_array($tab, ['do_now', 'waiting', 'ready', 'completed'], true)) {
        $tab = 'do_now';
    }
@endphp
<x-admin.layout title="Credit management" heading="" subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Post-approval</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    After Committee approval, complete offer, fees, conditions, contract, then disbursement. Do not re-screen the file.
                </p>
            </div>
            <div class="bg-white px-4 sm:px-6 py-4 flex flex-wrap gap-2">
                @foreach ([
                    'do_now' => 'Do now · '.count($queue['do_now']),
                    'waiting' => 'Waiting · '.count($queue['waiting']),
                    'ready' => 'Ready to disburse · '.count($queue['ready']),
                    'completed' => 'Completed · '.count($queue['completed']),
                ] as $key => $label)
                    <a href="{{ route('admin.teams.management', ['bucket' => $key]) }}"
                       @class([
                           'inline-flex rounded-xl px-4 py-2 text-sm font-bold ring-1',
                           'bg-brand text-white ring-brand' => $tab === $key,
                           'bg-white text-slate-800 ring-slate-200' => $tab !== $key,
                       ])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <div class="hidden lg:grid lg:grid-cols-12 gap-2 px-3 pb-2 text-[10px] font-bold uppercase tracking-wide text-slate-500">
        <div class="col-span-3">Application / customer</div>
        <div class="col-span-2">Product</div>
        <div class="col-span-1">Amount</div>
        <div class="col-span-3">Progress / waiting</div>
        <div class="col-span-3">Action</div>
    </div>

    <div class="space-y-3">
        @forelse ($queue[$tab] as $row)
            @php
                $next = $row['next'];
                $app = $row['application'];
                $percent = (int) ($next['percent'] ?? 0);
                $ctaHref = match ($next['cta_kind'] ?? '') {
                    'waiting' => $next['desk_href'] ?? $next['href'],
                    'disburse' => $next['desk_href'] ?? $next['href'],
                    default => $next['review_href'] ?? $next['href'],
                };
            @endphp
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
                <div class="lg:grid lg:grid-cols-12 lg:gap-3 lg:items-center">
                    <div class="col-span-3">
                        <a href="{{ $next['desk_href'] ?? $next['href'] }}" class="text-sm font-bold text-slate-900 hover:underline">{{ $app->application_number }}</a>
                        <p class="text-xs text-slate-600 mt-0.5">{{ $app->partyLabel() }}</p>
                    </div>
                    <div class="col-span-2 mt-2 lg:mt-0">
                        <p class="text-xs font-semibold text-slate-800">{{ $app->product?->name ?? 'Loan' }}</p>
                    </div>
                    <div class="col-span-1 mt-2 lg:mt-0">
                        <p class="text-sm font-semibold text-slate-900">{{ format_money((float) $app->requested_amount) }}</p>
                    </div>
                    <div class="col-span-3 mt-2 lg:mt-0">
                        <p class="text-sm font-bold text-slate-900">{{ $percent }}%</p>
                        <p class="text-xs text-slate-800 mt-0.5">{{ $next['what_happens_next'] }}</p>
                        <p class="text-[11px] text-slate-500 mt-1">{{ optional($app->updated_at)->diffForHumans() }}</p>
                    </div>
                    <div class="col-span-3 mt-3 lg:mt-0">
                        <a href="{{ $ctaHref }}" class="inline-flex rounded-xl bg-brand-gold px-3 py-1.5 text-xs font-bold text-brand">{{ $next['cta'] }}</a>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-600">Nothing in this list.</p>
        @endforelse
    </div>

    <p class="mt-6">
        <a href="{{ route('admin.loan-applications.pipeline.approved') }}" class="text-xs font-semibold text-slate-600 underline">Classic management queue</a>
    </p>

</x-admin.layout>
