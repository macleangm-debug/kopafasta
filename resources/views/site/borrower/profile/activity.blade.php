<x-site.borrower-layout title="Profile — Activity — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Profile</h1>
        <p class="text-sm text-gray-500 mb-6">Tell us what you do and your income range.</p>

        @include('site.borrower.profile._tabs', ['active' => 'activity'])

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}" class="bg-white rounded-2xl border border-gray-200 p-6">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-4">Activity information</h2>
            <x-site.activity-fields
                :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                :activity-details="old('activity_details', $customer->activity_details ?? [])"
                :income-range="old('income_range', $customer->income_range)"
            />

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Save activity information</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
