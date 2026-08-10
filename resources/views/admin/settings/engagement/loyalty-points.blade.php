<x-admin.layout title="Loyalty points" heading="Loyalty points" subheading="Earn points for actions, redeem for benefits">
    @include('admin.settings.engagement._nav', ['active' => 'loyalty-points'])
@php
        $actions = $values['actions'] ?? config('gamification.loyalty_points.actions', []);
        $penalties = $values['penalties'] ?? config('gamification.loyalty_points.penalties', []);
        $options = $values['redemption_options'] ?? config('gamification.loyalty_points.redemption_options', []);
    @endphp

    @include('admin.settings.engagement._guide', [
        'title' => 'How loyalty points work',
        'summary' => 'Members earn points when they complete tracked actions (profile, documents, on-time repayments, referrals). Late repayments and late-fee accruals can deduct points (penalties below). They redeem from the catalog into time-limited discounts that apply at membership or application-fee checkout. Points are separate from underwriting boosts — boosts change limit/rate; redemptions change fees.',
        'borrowerSees' => [
            'Rewards tab: balance, earn list, redeem cards, recent activity (credits in green, penalties in red), and confetti when points are credited.',
            'Membership / application fee payment: “Use rewards” path after redeeming a matching fee_type option.',
            'In-app notification with CTA back to Rewards when points are earned or deducted.',
        ],
        'fields' => [
            'Action points' => 'How many points each earn event awards. Keys are fixed in code (complete_profile, repay_on_time, etc.).',
            'Penalty points' => 'How many points to deduct for late_repayment (paid after due date) and late_fee_accrual (each LATE_FEE day). Deduction never goes below zero and is idempotent per schedule/fee.',
            'Benefit type / value' => 'Usually percent_discount + a number (e.g. 10 = 10% off).',
            'Fee type' => 'Which checkout the reward applies to: registration_fee, application_fee, etc. Must match the payment gate fee.',
            'Expires after (days)' => 'How long the redeemed voucher stays usable after redeem.',
            'Labels / descriptions' => 'EN + SW copy shown on redeem cards — keep them concrete (“15% off application fee”).',
        ],
        'example' => 'Member with 200 pts pays an instalment 3 days late → late_repayment deducts 50 → balance 150. Separately, each accrued LATE_FEE day can deduct late_fee_accrual points (default 25).',
        'tips' => [
            'Set earn values so a typical good member can redeem something within a few weeks.',
            'Keep fee_type aligned with real payment gates or redemptions will sit unused.',
            'Repayment streak awards extra points on top of repay_on_time — configure both intentionally.',
            'Disable a penalty (uncheck Enabled) to stop deductions without changing the point value.',
        ],
    ])

    <form method="POST" action="{{ route('admin.settings.engagement.loyalty-points.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Earn point values</h3>
            @foreach ($actions as $key => $action)
                <div class="grid md:grid-cols-2 gap-3">
                    <x-admin.input name="actions[{{ $key }}][label]" label="{{ $key }}" :value="$action['label'] ?? ''" />
                    <x-admin.input name="actions[{{ $key }}][points]" label="Points" type="number" :value="$action['points'] ?? 0" />
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Penalty deductions</h3>
            <p class="text-xs text-gray-500">Deducted from the member’s loyalty balance when they miss repayment behaviour. Never takes the balance below zero.</p>
            @foreach ($penalties as $key => $penalty)
                <div class="grid md:grid-cols-3 gap-3 pb-4 border-b border-gray-100 last:border-0 items-end">
                    <x-admin.input name="penalties[{{ $key }}][label]" label="{{ $key }}" :value="$penalty['label'] ?? ''" />
                    <x-admin.input name="penalties[{{ $key }}][points]" label="Points to deduct" type="number" :value="$penalty['points'] ?? 0" />
                    <label class="flex items-center gap-2 text-sm pb-2">
                        <input type="hidden" name="penalties[{{ $key }}][enabled]" value="0">
                        <input type="checkbox" name="penalties[{{ $key }}][enabled]" value="1" @checked($penalty['enabled'] ?? true)>
                        Enabled
                    </label>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Redemption catalog</h3>
            @foreach ($options as $i => $option)
                <div class="grid md:grid-cols-3 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="redemption_options[{{ $i }}][key]" value="{{ $option['key'] ?? '' }}">
                    <x-admin.input name="redemption_options[{{ $i }}][label]" label="Label (EN)" :value="$option['label'] ?? ''" />
                    <x-admin.input name="redemption_options[{{ $i }}][label_sw]" label="Label (SW)" :value="$option['label_sw'] ?? ''" />
                    <x-admin.input name="redemption_options[{{ $i }}][points]" label="Points cost" type="number" :value="$option['points'] ?? 0" />
                    <x-admin.input name="redemption_options[{{ $i }}][benefit_type]" label="Benefit type" :value="$option['benefit_type'] ?? 'percent_discount'" />
                    <x-admin.input name="redemption_options[{{ $i }}][benefit_value]" label="Benefit value" type="number" step="0.001" :value="$option['benefit_value'] ?? 0" />
                    <x-admin.input name="redemption_options[{{ $i }}][fee_type]" label="Fee type (optional)" :value="$option['fee_type'] ?? ''" />
                    <x-admin.input name="redemption_options[{{ $i }}][expires_days]" label="Expires after (days)" type="number" :value="$option['expires_days'] ?? 90" />
                    <div class="md:col-span-3">
                        <x-admin.textarea name="redemption_options[{{ $i }}][description]" label="Description (EN)" rows="2" :value="$option['description'] ?? ''" />
                    </div>
                    <div class="md:col-span-3">
                        <x-admin.textarea name="redemption_options[{{ $i }}][description_sw]" label="Description (SW)" rows="2" :value="$option['description_sw'] ?? ''" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
