<x-admin.layout title="Collections Performance" heading="Collections Performance" subheading="Arrears aging, defaults, and recovery">

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4"><p class="text-[10px] uppercase text-red-600">Open cases</p><p class="text-2xl font-bold">{{ $arrears->count() }}</p></div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4"><p class="text-[10px] uppercase text-gray-500">Defaulted loans</p><p class="text-2xl font-bold">{{ $defaulted }}</p></div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4"><p class="text-[10px] uppercase text-gray-500">Written off</p><p class="text-2xl font-bold">{{ $writtenOff }}</p></div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4"><p class="text-[10px] uppercase text-amber-700">90+ days</p><p class="text-2xl font-bold">{{ $aging['90plus'] }}</p></div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h2 class="text-sm font-semibold mb-4">Arrears aging</h2>
        <div class="grid sm:grid-cols-4 gap-4 text-sm">
            <div class="rounded-lg bg-gray-50 p-4"><p class="text-xs text-gray-500">1–30 days</p><p class="text-xl font-bold">{{ $aging['1_30'] }}</p></div>
            <div class="rounded-lg bg-gray-50 p-4"><p class="text-xs text-gray-500">31–60 days</p><p class="text-xl font-bold">{{ $aging['31_60'] }}</p></div>
            <div class="rounded-lg bg-gray-50 p-4"><p class="text-xs text-gray-500">61–90 days</p><p class="text-xl font-bold">{{ $aging['61_90'] }}</p></div>
            <div class="rounded-lg bg-red-50 p-4"><p class="text-xs text-red-600">90+ days</p><p class="text-xl font-bold text-red-800">{{ $aging['90plus'] }}</p></div>
        </div>
    </div>
</x-admin.layout>
