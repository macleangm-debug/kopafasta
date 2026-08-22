<x-admin.layout title="Referral levels" heading="Referral levels" subheading="Bronze, Silver, Gold, Diamond tiers">
    @include('admin.settings.engagement._nav', ['active' => 'referral-levels'])
@include('admin.settings.engagement._guide', [
        'title' => 'How referral levels work',
        'summary' => 'A member’s tier is based on how many successful referrals they have. The tier key (bronze, silver, gold, diamond) is what Underwriting boosts and the borrower referral page use. Changing min/max ranges moves members between tiers on the next page load — no batch job required.',
        'borrowerSees' => [
            'Rewards & referrals → Referrals tab: current level badge, progress toward the next tier, and benefit bullets for that level.',
            'Loan quote “member benefits” card when the tier has a limit/rate boost configured under Underwriting boosts.',
        ],
        'fields' => [
            'Min / max referrals' => 'Inclusive band for that tier. Leave max blank on Diamond for “51+”. Ranges should not overlap.',
            'Progress milestones' => 'Targets shown as “next reward” chips on the referral page (e.g. 5 referrals → membership discount label). Separate from community milestones.',
        ],
        'example' => 'Silver = 6–20 referrals. A member with 8 successful referrals is Silver. If Underwriting boosts set Silver limit ×1.05, their quote limit rises 5% vs Bronze.',
        'tips' => [
            'Keep Bronze starting at 0 so every new member has a tier.',
            'After renaming labels, update Underwriting boosts — boost rows are keyed by the tier key, not the label.',
            'Benefit marketing copy for each tier lives in config (referral_level_benefits) and is shown on the referrals panel.',
        ],
    ])

    <x-admin.settings-editor
        action="{{ route('admin.settings.engagement.referral-levels.save') }}"
        submit-label="Save"
        :tabs="[
            'levels' => 'Levels',
            'milestones' => 'Milestones',
        ]"
    >
        <x-admin.settings-panel id="levels">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Levels</h3>
                @foreach ($levels as $i => $level)
                    <div class="grid md:grid-cols-4 gap-3 pb-4 border-b border-gray-100 last:border-0">
                        <input type="hidden" name="levels[{{ $i }}][key]" value="{{ $level['key'] ?? '' }}">
                        <x-admin.input name="levels[{{ $i }}][label]" label="Label" :value="$level['label'] ?? ''" />
                        <x-admin.input name="levels[{{ $i }}][min_referrals]" label="Min referrals" type="number" :value="$level['min_referrals'] ?? 0" />
                        <x-admin.input name="levels[{{ $i }}][max_referrals]" label="Max referrals (blank = unlimited)" type="number" :value="$level['max_referrals'] ?? ''" />
                    </div>
                @endforeach
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="milestones">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Progress milestones</h3>
                @foreach (($milestones ?? []) as $i => $milestone)
                    <div class="grid md:grid-cols-2 gap-3">
                        <x-admin.input name="milestones[{{ $i }}][target]" label="Target referrals" type="number" :value="$milestone['target'] ?? ''" />
                        <x-admin.input name="milestones[{{ $i }}][reward_label]" label="Next reward label" :value="$milestone['reward_label'] ?? ''" />
                    </div>
                @endforeach
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
