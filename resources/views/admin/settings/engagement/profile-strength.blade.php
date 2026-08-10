<x-admin.layout title="Profile strength" heading="Profile strength tiers" subheading="Bronze, Silver, Gold, Verified">
    @include('admin.settings.engagement._nav', ['active' => 'profile-strength'])
@include('admin.settings.engagement._guide', [
        'title' => 'How profile strength works',
        'summary' => 'Profile strength is a label for the member’s profile completion percent (from Profile builder sections). It does not by itself change loan limits — trust score and underwriting boosts do. Use these bands for clear UX (“You’re Gold — almost Verified”).',
        'borrowerSees' => [
            'Profile hub: strength label next to completion %.',
            'Engagement / financial-health surfaces that mention profile strength.',
        ],
        'fields' => [
            'Min % / Max %' => 'Inclusive completion bands. Cover 0–100 without gaps or overlaps (e.g. 0–39, 40–69, 70–89, 90–100).',
            'Label' => 'Shown to the member. Keep short: Bronze, Silver, Gold, Verified.',
        ],
        'example' => 'Member at 72% completion → Gold. Apply gate may still require 100% for some products; strength is motivational, not the gate.',
        'tips' => [
            'Align Verified (90–100) with your apply-ready threshold messaging.',
            'Change section weights in Profile builder if completion % feels too easy or too hard — not here.',
        ],
    ])

    <form method="POST" action="{{ route('admin.settings.engagement.profile-strength.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            @foreach ($tiers as $i => $tier)
                <div class="grid md:grid-cols-4 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="tiers[{{ $i }}][key]" value="{{ $tier['key'] ?? '' }}">
                    <x-admin.input name="tiers[{{ $i }}][label]" label="Label" :value="$tier['label'] ?? ''" />
                    <x-admin.input name="tiers[{{ $i }}][min_percent]" label="Min %" type="number" :value="$tier['min_percent'] ?? 0" />
                    <x-admin.input name="tiers[{{ $i }}][max_percent]" label="Max %" type="number" :value="$tier['max_percent'] ?? 100" />
                </div>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
