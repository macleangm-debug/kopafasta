<x-site.borrower-layout :title="brand_title($asset['title'])" active="marketplace">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace') }}" class="text-xs text-gray-500 hover:text-gray-700">← Back to marketplace</a>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            @if (! empty($asset['photos']))
                <div class="space-y-3">
                    @foreach ($asset['photos'] as $photo)
                        <img src="{{ Storage::url($photo) }}" alt="" class="w-full rounded-2xl aspect-[4/3] object-cover bg-slate-100">
                    @endforeach
                </div>
            @else
                <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-6xl">
                    @switch($asset['category'])
                        @case('vehicles') 🚗 @break
                        @case('motorcycles') 🏍️ @break
                        @case('equipment') 🧰 @break
                        @default 🏭
                    @endswitch
                </div>
            @endif
        </div>

        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
            <h1 class="text-2xl font-bold mt-1">{{ $asset['title'] }}</h1>
            <p class="text-sm text-gray-500 mt-2">Supplier: {{ $asset['vendor'] }}</p>
            <p class="text-sm text-gray-600 mt-4">{{ $asset['description'] }}</p>

            <div class="grid grid-cols-2 gap-3 mt-6">
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">Asset value</p>
                    <p class="text-lg font-bold">TZS {{ number_format($asset['asset_value'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">Remaining loan</p>
                    <p class="text-lg font-bold">TZS {{ number_format($asset['remaining_loan'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">Customer deposit</p>
                    <p class="text-lg font-bold">TZS {{ number_format($asset['deposit']) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">Weekly instalment</p>
                    <p class="text-lg font-bold">TZS {{ number_format($asset['weekly_installment']) }}</p>
                </div>
                @if (! empty($asset['max_tenure_months']))
                    <div class="rounded-xl bg-gray-50 p-4 col-span-2">
                        <p class="text-[10px] uppercase text-gray-400">Max tenure</p>
                        <p class="text-lg font-bold">{{ $asset['max_tenure_months'] }} months</p>
                    </div>
                @endif
            </div>

            <p class="mt-4 text-xs text-gray-500">{{ config('asset_marketplace.ownership_note') }}</p>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="#apply" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Apply for asset</a>
                <a href="{{ $applyUrl ?? route('site.borrower.apply') }}" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Apply for asset loan</a>
                @if ($reservation)
                    <a href="{{ route('site.borrower.marketplace.reserve', $asset['id']) }}" class="text-sm font-semibold text-emerald-700">Continue application →</a>
                @endif
            </div>
        </div>
    </div>

    <div id="apply" class="mt-10 bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-semibold text-lg">Apply for this asset</h2>
        <p class="text-sm text-gray-500 mt-1">Schedule a viewing → confirm interest → pay application fee → pay deposit → submit loan application.</p>

        <form method="POST" action="{{ route('site.borrower.marketplace.reserve.post', $asset['id']) }}" class="mt-5 grid sm:grid-cols-2 gap-4 max-w-xl"
              @submit.prevent="window.confirmForm($el, { title: 'Apply for this asset?', message: 'You will schedule a viewing with the supplier next.', confirmLabel: 'Apply', confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Preferred viewing date</label>
                <input type="date" name="viewing_date" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Preferred time</label>
                <select name="viewing_time" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    <option value="09:00">09:00</option>
                    <option value="11:00">11:00</option>
                    <option value="14:00">14:00</option>
                    <option value="16:00">16:00</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Apply for asset</button>
            </div>
        </form>
    </div>

</x-site.borrower-layout>
