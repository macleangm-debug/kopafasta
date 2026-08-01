<x-admin.layout title="Identity Verification" heading="Identity Verification" subheading="NIDA verification attempts, suspension, and DOB matching">
    @include('admin.settings._tabs', ['active' => 'identity'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.identity.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">Borrowers must provide National ID and date of birth at registration. NIDA verification compares both against bureau records. After repeated failures, <strong>identity verification is paused</strong> for the configured period — the borrower can still sign in and complete other profile sections, but cannot verify NIDA or apply for loans until the pause ends or an admin clears the case.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="max_mismatch_attempts" label="Maximum verification attempts" type="number" min="1" max="10" :value="$values['max_mismatch_attempts'] ?? 3" required />
            @php
                $lockDays = (int) ($values['lock_days'] ?? (isset($values['lock_hours']) ? max(1, (int) ceil($values['lock_hours'] / 24)) : 1));
            @endphp
            <x-admin.input name="lock_days" label="Verification pause period (days)" type="number" min="1" max="365" :value="$lockDays" required />
        </div>

        <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 max-w-md">
            <input type="hidden" name="require_dob" value="0">
            <input type="checkbox" name="require_dob" value="1" @checked(!empty($values['require_dob']) || !isset($values['require_dob'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
            <span class="text-gray-800">DOB verification required against NIDA</span>
        </label>

        <div class="border-t border-gray-100 pt-6 mt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">When identity verification is required</h3>
            <p class="text-xs text-gray-500 mb-4">Control whether facial and NIDA checks block profile completion or are deferred until loan underwriting.</p>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Verification stage</label>
                    <select name="verification_stage" class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
                        <option value="underwriting" @selected(($values['verification_stage'] ?? 'underwriting') === 'underwriting')>During loan underwriting (default)</option>
                        <option value="profile_creation" @selected(($values['verification_stage'] ?? '') === 'profile_creation')>During profile creation</option>
                    </select>
                </div>
            </div>
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <input type="hidden" name="require_facial" value="0">
                    <input type="checkbox" name="require_facial" value="1" @checked(! isset($values['require_facial']) || ! empty($values['require_facial'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span class="text-gray-800">Require facial verification</span>
                </label>
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <input type="hidden" name="require_nida" value="0">
                    <input type="checkbox" name="require_nida" value="1" @checked(! isset($values['require_nida']) || ! empty($values['require_nida'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span class="text-gray-800">Require national ID verification</span>
                </label>
            </div>
        </div>

        <div class="rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            <strong>Industry-standard flow:</strong> Attempt 1 — clear warning · Attempt 2 — final warning · Attempt 3+ — verification paused (not a full login lock). Borrowers are directed to Support to appeal if they believe there is an error. Admins can unlock from the customer dossier.
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save identity rules</button>
        </div>
    </form>
</x-admin.layout>
