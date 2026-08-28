<x-admin.layout title="Referrals" heading="Referral Program" subheading="Member invite links and points. Rewards catalogue is under Settings → Growth → Rewards.">
    @include('admin.settings._tabs', ['active' => 'referrals'])
    <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
        <p class="font-semibold">How member referrals work</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-xs text-sky-800">
            <li>Borrowers share an <strong>invite link</strong> (not a wakala/promo code).</li>
            <li>Invitee registers within the attribution window → referrer earns registration points.</li>
            <li>Invitee submits a first valid application and pays the application fee → referrer earns application points.</li>
            <li>Profile/KYC points belong to the account owner. Borrowing never earns points.</li>
            <li>Wakala (affiliate) is a separate commercial programme.</li>
        </ul>
    </div>

    <x-admin.settings-editor
        action="{{ route('admin.settings.referrals.save') }}"
        submit-label="Save referral settings"
        :tabs="[
            'attribution' => 'Attribution',
            'rewards' => 'Points',
            'wallet' => 'Spending',
            'messages' => 'Messages',
        ]"
    >
        <x-admin.settings-panel id="attribution">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Invite link attribution</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="code_prefix" label="Link code prefix (internal)" :value="$values['code_prefix'] ?? config('referrals.code_prefix')" required />
                    <x-admin.input name="attribution_days" label="Link valid for registration (days)" type="number" min="1" max="365"
                                   :value="$values['attribution_days'] ?? config('referrals.attribution_days', 30)" required />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="rewards">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">How points are earned</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="register_points" label="Points when invitee registers" type="number" min="0" max="100000"
                                   :value="$values['register_points'] ?? config('referrals.register_points', 5)" required />
                    <x-admin.input name="application_points" label="Extra points when they apply and pay the application fee" type="number" min="0" max="100000"
                                   :value="$values['application_points'] ?? config('referrals.application_points', 25)" required />
                </div>
                <p class="mt-3 text-xs text-gray-500">All values are configuration, not code. Default seed: 5 on register + 25 on first paid application.</p>
                <p class="mt-2 text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                    Legacy <span class="font-mono">referrals.discount_percent</span> is retired. It belonged to the old membership-referral cash discount and is forced to 0 — it cannot change checkout.
                </p>
                <input type="hidden" name="commission_percent" value="{{ $values['commission_percent'] ?? config('referrals.commission_percent') }}">
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="wallet">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Legacy referral wallet (TZS)</h3>
                <x-admin.input name="wallet_max_fee_percent" label="Max % of a fee payable with legacy referral wallet" type="number" step="0.01" min="0" max="100"
                               :value="$values['wallet_max_fee_percent'] ?? config('referrals.wallet_max_fee_percent')" required />
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="messages">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Invite messages (share link)</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Placeholders:
                    <span class="font-mono">{Referral Link}</span>,
                    <span class="font-mono">{referral_link}</span>,
                    <span class="font-mono">{brand}</span>,
                    <span class="font-mono">{register_points}</span>,
                    <span class="font-mono">{application_points}</span>
                </p>
                <div class="space-y-4">
                    <x-admin.textarea name="message_share_en" label="Share message (English)" rows="6"
                                      :value="$values['message_share_en'] ?? config('referrals.messages.share_en')" />
                    <x-admin.textarea name="message_share_sw" label="Share message (Swahili)" rows="6"
                                      :value="$values['message_share_sw'] ?? config('referrals.messages.share_sw')" />
                    <x-admin.textarea name="message_share_template" label="Short share template" rows="2"
                                      :value="$values['message_share_template'] ?? config('referrals.messages.share_template')" />
                    <x-admin.textarea name="message_invite_sms" label="Invite SMS" rows="2"
                                      :value="$values['message_invite_sms'] ?? config('referrals.messages.invite_sms')" />
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
