<x-admin.layout title="Referrals" heading="Referral Program" subheading="Discounts, commissions, wallet rules, and share messages">
    @include('admin.settings._tabs', ['active' => 'referrals'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.referrals.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Referral codes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="code_prefix" label="Code prefix" :value="$values['code_prefix'] ?? config('referrals.code_prefix')" required />
                <x-admin.input name="attribution_days" label="Link attribution window (days)" type="number" :value="$values['attribution_days'] ?? config('referrals.attribution_days', 30)" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">When someone clicks a referral link, they stay tied to the referrer for this many days.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Rewards</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="discount_percent" label="Referral discount (%)" type="number" step="0.01" :value="$values['discount_percent'] ?? config('referrals.discount_percent')" required />
                <x-admin.input name="commission_percent" label="Referrer commission (%)" type="number" step="0.01" :value="$values['commission_percent'] ?? config('referrals.commission_percent')" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">Referrals complete only after membership payment is confirmed.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Referral wallet</h3>
            <x-admin.input name="wallet_max_fee_percent" label="Max wallet usage per fee (%)" type="number" step="0.01" :value="$values['wallet_max_fee_percent'] ?? config('referrals.wallet_max_fee_percent')" required />
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Default referral messages</h3>
            <p class="text-xs text-gray-500 mb-4">Placeholders: <span class="font-mono">{Referral Link}</span>, <span class="font-mono">{referral_link}</span>, <span class="font-mono">{referral_code}</span>, <span class="font-mono">{brand}</span></p>
            <div class="space-y-4">
                <x-admin.textarea name="message_share_en" label="Share message (English)" rows="6"
                                  :value="$values['message_share_en'] ?? config('referrals.messages.share_en')" />
                <x-admin.textarea name="message_share_sw" label="Share message (Swahili)" rows="6"
                                  :value="$values['message_share_sw'] ?? config('referrals.messages.share_sw')" />
                <x-admin.textarea name="message_share_template" label="Legacy share template" rows="2"
                                  :value="$values['message_share_template'] ?? config('referrals.messages.share_template')" />
                <x-admin.textarea name="message_invite_sms" label="SMS invite template" rows="2"
                                  :value="$values['message_invite_sms'] ?? config('referrals.messages.invite_sms')" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save referral settings</button>
        </div>
    </form>
</x-admin.layout>
