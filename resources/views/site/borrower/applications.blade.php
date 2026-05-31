<x-site.borrower-layout title="Applications — Kopafasta" active="applications">

    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold">My applications</h1>
            <p class="text-sm text-gray-500">Track every loan request and its status.</p>
        </div>
        <a href="{{ route('site.borrower.apply') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">+ New application</a>
    </div>

    @if ($applications->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-gray-500">You haven't applied yet.</p>
            <a href="{{ route('site.borrower.apply') }}" class="mt-4 inline-block bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Start your first application</a>
        </div>
    @else
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach ($applications as $app)
                @php
                    $stages = ['submitted','screening','credit_appraisal','pre_approval','approval','disbursement','disbursed'];
                    $idx = array_search($app->status, $stages);
                    $pct = $idx === false ? 10 : (($idx + 1) / count($stages)) * 100;
                    $isRejected = $app->status === 'rejected';
                    $badge = match (true) {
                        $isRejected => 'bg-red-100 text-red-700',
                        in_array($app->status, ['approved','disbursement','disbursed']) => 'bg-emerald-100 text-emerald-700',
                        $app->status === 'submitted' => 'bg-amber-100 text-amber-700',
                        default => 'bg-sky-100 text-sky-700',
                    };
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="font-mono font-semibold text-sm">{{ $app->application_number }}</p>
                            <p class="text-xs text-gray-500">{{ $app->product->name ?? '—' }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ ucfirst(str_replace('_',' ', $app->status)) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">Requested</p>
                            <p class="font-semibold">TZS {{ number_format($app->requested_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">Tenure</p>
                            <p class="font-semibold">{{ $app->requested_tenure_months }} months</p>
                        </div>
                    </div>
                    @if (! $isRejected)
                        <div class="mb-3">
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1">Stage: {{ ucfirst(str_replace('_',' ', $app->current_stage ?? $app->status)) }}</p>
                        </div>
                    @else
                        <p class="text-xs text-red-600 mb-3">{{ $app->rejection_reason ?? 'Application was declined.' }}</p>
                    @endif
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('site.borrower.application', $app->id) }}" class="text-amber-600 font-medium hover:underline">Open & upload documents →</a>
                        <span class="text-gray-300">·</span>
                        <a href="{{ route('site.apply.success', $app->id) }}" class="text-gray-500 hover:text-gray-700">Receipt</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-site.borrower-layout>
