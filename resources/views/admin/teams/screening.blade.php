<x-admin.layout title="Credit screening" heading="Credit screening team" subheading="Documents, face/ID, affordability, and recommendations before committee">

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Screening</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['screening'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Credit appraisal</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['appraisal'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-brand/20 bg-brand-muted/20 p-5">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Assigned to me</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['mine'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.loan-applications.pipeline.under-review') }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">
            Open screening queue
        </a>
        <a href="{{ route('admin.credit-team.index') }}"
           class="inline-flex text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            Team roster →
        </a>
    </div>

</x-admin.layout>
