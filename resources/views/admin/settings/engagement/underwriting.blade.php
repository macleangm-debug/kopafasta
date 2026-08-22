<x-admin.layout title="Underwriting boosts" heading="Underwriting boosts" subheading="Referral level and trust score multipliers for limits, rates, and processing priority">
    @include('admin.settings.engagement._nav', ['active' => 'underwriting'])
@php
        $referralLevels = $values['referral_level'] ?? config('gamification.underwriting_boosts.referral_level', []);
        $trust = $values['trust_score'] ?? config('gamification.underwriting_boosts.trust_score', []);
    @endphp

    @include('admin.settings.engagement._guide', [
        'title' => 'How underwriting boosts work',
        'summary' => 'When a member opens the loan amount step, the system combines their referral tier and trust score into three live benefits: a higher available limit, a lower quoted interest rate, and faster review priority. These settings control that math — they do not change product base rates or hard product max amounts by themselves.',
        'borrowerSees' => [
            'On the apply wizard quote step: “Your member benefits” with available limit and interest discount.',
            'On the dashboard financial-health panel: available limit that already includes the boost.',
            'Faster review when processing priority is higher (SLA label shortens for high-priority members).',
        ],
        'fields' => [
            'Limit multiplier' => 'Multiplies the member’s calculated available limit. 1.00 = no change. 1.10 = 10% higher limit than the unboosted calculation. Product max amount still caps the result.',
            'Rate discount (fraction)' => 'Subtracted from the product interest rate as a decimal. 0.010 = 1.0 percentage point off (e.g. 3.0% → 2.0% monthly if the product uses that scale). Cap in code is 5% total discount.',
            'Processing priority' => 'Integer score (0–10 after trust is added). Higher values move the application earlier in the review queue and can shorten the estimated review SLA shown to the member.',
            'Trust per step' => 'Trust score is split into 20% bands (0–19% = 0 steps … 100% = 5 steps). Each step adds the “per step” values below on top of the referral-level boost.',
        ],
        'example' => 'Gold referral (limit ×1.10, rate −0.010, priority 2) + trust 65% (3 steps × 0.02 / 0.002 / 0.5) ⇒ limit ×1.16, rate discount 0.016, priority 4. A TZS 1,000,000 base limit becomes about TZS 1,160,000 before product caps.',
        'tips' => [
            'Keep Bronze at 1.00 / 0 / 0 so new members are not over-promised.',
            'Raise Silver/Gold gradually — large jumps show up immediately on the next quote refresh.',
            'Trust steps reward profile completion and on-time behaviour; pair with Trust score weights on the Trust score page.',
            'Referral tiers themselves are configured under Referral levels; this page only maps each tier to loan terms.',
        ],
    ])

    <x-admin.settings-editor
        action="{{ route('admin.settings.engagement.underwriting.save') }}"
        submit-label="Save"
        :tabs="[
            'referral' => 'Referral',
            'trust' => 'Trust',
        ]"
    >
        <x-admin.settings-panel id="referral">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Referral level boosts</h3>
                <p class="text-xs text-gray-500">Applied when calculating loan limits, quoted rates, and review queue priority. Tier keys must match Referral levels (bronze → diamond).</p>
                @foreach ($levels as $level)
                    @php $key = $level['key'] ?? ''; $boost = $referralLevels[$key] ?? []; @endphp
                    <div class="grid md:grid-cols-4 gap-3 pb-4 border-b border-gray-100 last:border-0">
                        <div class="md:col-span-4 text-sm font-medium text-gray-800">{{ $level['label'] ?? ucfirst($key) }} <span class="text-xs font-normal text-gray-400">({{ $key }})</span></div>
                        <x-admin.input name="referral_level[{{ $key }}][limit_multiplier]" label="Limit multiplier" type="number" step="0.01" min="1" :value="$boost['limit_multiplier'] ?? 1" />
                        <x-admin.input name="referral_level[{{ $key }}][rate_discount]" label="Rate discount (fraction)" type="number" step="0.001" min="0" :value="$boost['rate_discount'] ?? 0" />
                        <x-admin.input name="referral_level[{{ $key }}][processing_priority]" label="Processing priority" type="number" min="0" :value="$boost['processing_priority'] ?? 0" />
                    </div>
                @endforeach
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="trust">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid md:grid-cols-3 gap-4">
                <div class="md:col-span-3">
                    <h3 class="text-sm font-semibold text-gray-700">Trust score per step (20% bands, max 5 steps)</h3>
                    <p class="text-xs text-gray-500 mt-1">Example: trust 45% → floor(45/20) = 2 steps. Added on top of the referral-level row above.</p>
                </div>
                <x-admin.input name="trust_score[limit_multiplier_per_step]" label="Limit multiplier per step" type="number" step="0.01" :value="$trust['limit_multiplier_per_step'] ?? 0.02" />
                <x-admin.input name="trust_score[rate_discount_per_step]" label="Rate discount per step" type="number" step="0.001" :value="$trust['rate_discount_per_step'] ?? 0.002" />
                <x-admin.input name="trust_score[processing_priority_per_step]" label="Priority per step" type="number" step="0.1" :value="$trust['processing_priority_per_step'] ?? 0.5" />
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
