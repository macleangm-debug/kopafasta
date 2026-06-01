<x-admin.layout title="Identity Verification" heading="Identity Verification" subheading="NIDA mismatch protection and lockout rules">
    @include('admin.settings._tabs', ['active' => 'identity'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.identity.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">When a borrower’s registration name does not match NIDA records, escalate warnings and temporarily lock verification after repeated mismatches.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="max_mismatch_attempts" label="Max name mismatch attempts before lock" type="number" min="1" max="10" :value="$values['max_mismatch_attempts'] ?? 3" required />
            <x-admin.input name="lock_hours" label="Lock duration (hours)" type="number" min="1" max="168" :value="$values['lock_hours'] ?? 24" required />
        </div>

        <div class="rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            <strong>Flow:</strong> Attempt 1 — warning · Attempt 2 — stronger warning · Attempt 3+ — account locked for the configured hours.
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save identity rules</button>
        </div>
    </form>
</x-admin.layout>
