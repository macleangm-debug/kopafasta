@props(['title', 'heading', 'description' => null])
<x-admin.layout :title="$title" :heading="$heading">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-8">
        <div class="max-w-2xl">
            <div class="size-12 rounded-lg bg-amber-100 text-amber-700 grid place-items-center mb-4">
                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 13l4 4L17 7M19 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h6"/>
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900">{{ $heading }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ $description ?? 'This report is being prepared. Charts and exportable data will appear here.' }}
            </p>
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="rounded-lg ring-1 ring-gray-200 p-4">
                    <div class="text-xs text-gray-500">Generated</div>
                    <div class="text-base font-semibold">{{ now()->format('Y-m-d') }}</div>
                </div>
                <div class="rounded-lg ring-1 ring-gray-200 p-4">
                    <div class="text-xs text-gray-500">Format</div>
                    <div class="text-base font-semibold">CSV / PDF</div>
                </div>
                <div class="rounded-lg ring-1 ring-gray-200 p-4">
                    <div class="text-xs text-gray-500">Status</div>
                    <div class="text-base font-semibold text-amber-700">Coming soon</div>
                </div>
            </div>
        </div>
    </div>
</x-admin.layout>
