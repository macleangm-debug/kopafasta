<x-admin.layout title="Referrals" heading="Referral Program" subheading="Member invite links, invitee discount, referrer points, and attribution">
    @include('admin.settings._tabs', ['active' => 'referrals'])
<form method="POST" action="{{ route('admin.settings.referrals.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
            <p class="font-semibold">How member referrals work</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-xs text-sky-800">
                <li>Borrowers share an <strong>invite link</strong> (not a wakala/promo code).</li>
                <li>Invitee clicks the link and registers within the attribution window → referrer is locked in.</li>
                <li>Invitee pays membership → gets the discount %; referrer gets the fixed points + notification.</li>
                <li>Wakala (affiliate) and promo codes are separate programmes.</li>
            </ul>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Invite link attribution</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="code_prefix" label="Link code prefix (internal)" :value="$values['code_prefix'] ?? config('referrals.code_prefix')" required />
                <x-admin.input name="attribution_days" label="Link valid for registration (days)" type="number" min="1" max="365"
                               :value="$values['attribution_days'] ?? config('referrals.attribution_days', 30)" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Recommended: <strong>30 days</strong>. Example: someone clicks your link today and registers on day 10 → still yours.
                If they register after the window without using your link again → no referral. Once registered under you, membership can be paid later and you still get points.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Rewards (membership)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="discount_percent" label="Invitee membership discount (%)" type="number" step="0.01" min="0" max="100"
                               :value="$values['discount_percent'] ?? config('referrals.discount_percent')" required />
                <x-admin.input name="referrer_points" label="Referrer points when invitee pays membership" type="number" min="0" max="100000"
                               :value="$values['referrer_points'] ?? config('referrals.referrer_points', 50)" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Default now: <strong>{{ rtrim(rtrim(format_number((float) ($values['discount_percent'] ?? config('referrals.discount_percent')), 2), '0'), '.') }}%</strong> off for the new member,
                <strong>{{ (int) ($values['referrer_points'] ?? config('referrals.referrer_points', 50)) }} points</strong> for the referrer after membership is paid.
            </p>
            <input type="hidden" name="commission_percent" value="{{ $values['commission_percent'] ?? config('referrals.commission_percent') }}">
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Spending referral points</h3>
            <x-admin.input name="wallet_max_fee_percent" label="Max % of a fee payable with referral points" type="number" step="0.01" min="0" max="100"
                           :value="$values['wallet_max_fee_percent'] ?? config('referrals.wallet_max_fee_percent')" required />
            <p class="mt-3 text-xs text-gray-500">Applies to membership, application, and post-approval fees — not loan repayments.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Invite messages (share link)</h3>
            <p class="text-xs text-gray-500 mb-4">
                Placeholders:
                <span class="font-mono">{Referral Link}</span>,
                <span class="font-mono">{referral_link}</span>,
                <span class="font-mono">{brand}</span>,
                <span class="font-mono">{discount_percent}</span>,
                <span class="font-mono">{referrer_points}</span>
            </p>
            <div class="space-y-4">
                <x-admin.textarea name="message_share_en" label="Share message (English)" rows="6"
                                  :value="$values['message_share_en'] ?? config('referrals.messages.share_en')" />
                <x-admin.textarea name="message_share_sw" label="Share message (Swahili)" rows="6"
                                  :value="$values['message_share_sw'] ?? config('referrals.messages.share_sw')" />
                <x-admin.textarea name="message_share_template" label="Short share template" rows="2"
                                  :value="$values['message_share_template'] ?? config('referrals.messages.share_template')" />
                <x-admin.textarea name="message_invite_sms" label="SMS invite template" rows="2"
                                  :value="$values['message_invite_sms'] ?? config('referrals.messages.invite_sms')" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save referral settings</button>
        </div>
    </form>
</x-admin.layout>
