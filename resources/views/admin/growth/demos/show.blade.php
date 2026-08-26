<x-admin.layout title="Demo ready" heading="Demo ready">
    @php $payload = $demo->payload ?? []; @endphp
    <div class="max-w-xl space-y-4">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-6 space-y-4">
            <p class="text-xl font-bold">{{ $demo->display_name }}</p>
            <p class="text-sm text-gray-600">{{ strtoupper((string) ($payload['grade'] ?? '')) }}{{ !empty($payload['plus']) ? ' · Plus' : '' }}</p>
            <p class="text-sm text-gray-600">{{ $payload['scenario_label'] ?? $demo->scenario_key }}@if(!empty($payload['amount'])) · TZS {{ \App\Support\MoneyFormat::compact($payload['amount']) }}@endif</p>
            <p class="text-sm">Expires in <strong>{{ $demo->isLive() ? gmdate('i:s', $demo->remainingSeconds()) : 'ended' }}</strong></p>
            <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-xl px-3 py-2">This identity is not a customer. It cannot pay, disburse, SMS, or post to the ledger.</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $playUrl }}" class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2">Open Demo</a>
                <a href="{{ $playUrl }}&presentation=1" class="rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2">Presentation Mode</a>
                <button type="button" class="rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2" onclick="navigator.clipboard.writeText(@js($playUrl)); window.showAdminFeedback({ tone: 'success', message: 'Temporary link copied' })">Copy temporary link</button>
                @if ($demo->isLive())
                    <form method="post" action="{{ route('admin.growth.demos.end', $demo) }}" onsubmit="event.preventDefault(); confirmForm(this, { title: 'End demo?', message: 'The session will be archived, not deleted.' })">
                        @csrf
                        <button class="rounded-xl bg-red-600 text-white text-sm font-semibold px-4 py-2">End Demo</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($demo->isLive())
            @can('marketing.demos.create')
                <form method="post" action="{{ route('admin.growth.demos.customize', $demo) }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-6 space-y-3">
                    @csrf
                    <h2 class="font-semibold">Edit presentation values</h2>
                    <x-admin.input name="display_name" label="Name" :value="$demo->display_name" />
                    <x-admin.input name="amount" label="Amount (TZS)" :money="true" :value="$payload['amount'] ?? null" />
                    <x-admin.select name="grade" label="Grade" :options="['bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum']" :value="$payload['grade'] ?? 'gold'" />
                    <x-admin.input name="trust" label="Trust" type="number" :value="$payload['trust'] ?? 70" />
                    <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2">Save presentation</button>
                    <p class="text-xs text-gray-500">Saves JSON on this demo only. DemoGuard still blocks money movement.</p>
                </form>
            @endcan
        @endif
    </div>
</x-admin.layout>
