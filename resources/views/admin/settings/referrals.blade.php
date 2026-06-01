<x-admin.layout title="Referrals" heading="Referral Program" subheading="Discounts, commissions, and wallet rules">
    @include('admin.settings._tabs', ['active' => 'referrals'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.referrals.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Referral codes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="code_prefix" label="Code prefix" :value="$values['code_prefix'] ?? config('referrals.code_prefix')" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">Members receive codes like <span class="font-mono">{{ ($values['code_prefix'] ?? 'KPF') }}-MAGORI001</span>.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Rewards</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="discount_percent" label="Referral discount (%)" type="number" step="0.01" :value="$values['discount_percent'] ?? config('referrals.discount_percent')" required />
                <x-admin.input name="commission_percent" label="Referrer commission (%)" type="number" step="0.01" :value="$values['commission_percent'] ?? config('referrals.commission_percent')" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">Example: TZS 10,000 registration fee with 10% discount and 10% commission → customer pays 9,000, referrer earns 900.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Referral wallet</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="wallet_max_fee_percent" label="Max wallet usage per fee (%)" type="number" step="0.01" :value="$values['wallet_max_fee_percent'] ?? config('referrals.wallet_max_fee_percent')" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">Wallet credits may cover registration, application, and post-approval fees — not loan repayments, interest, or penalties.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save referral settings</button>
        </div>
    </form>
</x-admin.layout>
