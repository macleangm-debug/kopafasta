<x-admin.layout title="Identity Verification" heading="Identity Verification" subheading="NIDA verification attempts, suspension, and DOB matching">
    @include('admin.settings._tabs', ['active' => 'identity'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.identity.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">Borrowers must provide National ID and date of birth at registration. NIDA verification compares both against bureau records. After repeated failures, identity verification is blocked temporarily.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="max_mismatch_attempts" label="Maximum verification attempts" type="number" min="1" max="10" :value="$values['max_mismatch_attempts'] ?? 3" required />
            @php
                $lockDays = (int) ($values['lock_days'] ?? (isset($values['lock_hours']) ? max(1, (int) ceil($values['lock_hours'] / 24)) : 30));
            @endphp
            <x-admin.input name="lock_days" label="Temporary suspension period (days)" type="number" min="1" max="365" :value="$lockDays" required />
        </div>

        <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 max-w-md">
            <input type="hidden" name="require_dob" value="0">
            <input type="checkbox" name="require_dob" value="1" @checked(!empty($values['require_dob']) || !isset($values['require_dob'])) class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
            <span class="text-gray-800">DOB verification required against NIDA</span>
        </label>

        <div class="rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            <strong>Flow:</strong> Attempt 1 — warning · Attempt 2 — final warning · Attempt 3 — status set to <em>Identity verification failed</em> and account suspended for the configured period.
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save identity rules</button>
        </div>
    </form>
</x-admin.layout>
