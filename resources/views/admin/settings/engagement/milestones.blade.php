<x-admin.layout title="Community milestones" heading="Community milestones" subheading="Rewards for helping others join">
    @include('admin.settings.engagement._nav', ['active' => 'milestones'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @include('admin.settings.engagement._guide', [
        'title' => 'How community milestones work',
        'summary' => 'These are campaign-style goals on the referrals experience (“Help 5 people join”). Target is the number of people helped; rewards lines are marketing copy shown to the member. They are separate from referral-level tiers and from referral progress milestones on the Referral levels page.',
        'borrowerSees' => [
            'Referrals tab: milestone cards with title, progress toward target, and reward bullets.',
        ],
        'fields' => [
            'Title' => 'Card headline, e.g. “Help 5 people join”.',
            'Target' => 'How many successful joins count toward completion.',
            'Rewards (one per line)' => 'Bullet list of promised rewards. Automate fulfilment separately if needed — this text is what members read.',
        ],
        'example' => 'Title “Help 5 people join”, target 5, rewards “Membership discount” / “Bonus points”. Member with 3 joins sees 3/5 progress.',
        'tips' => [
            'Keep promises realistic relative to Loyalty points and referral wallet rules.',
            'Use Referral levels for structural tier benefits; use this page for time-bound or campaign messaging.',
        ],
    ])

    <form method="POST" action="{{ route('admin.settings.engagement.milestones.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            @foreach ($milestones as $i => $milestone)
                <div class="grid md:grid-cols-2 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="milestones[{{ $i }}][key]" value="{{ $milestone['key'] ?? 'm'.$i }}">
                    <x-admin.input name="milestones[{{ $i }}][title]" label="Title" :value="$milestone['title'] ?? ''" />
                    <x-admin.input name="milestones[{{ $i }}][target]" label="Target" type="number" :value="$milestone['target'] ?? ''" />
                    <div class="md:col-span-2">
                        <x-admin.textarea name="milestones[{{ $i }}][rewards]" label="Rewards (one per line)" rows="3"
                            :value="implode(\"\\n\", $milestone['rewards'] ?? [])" />
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
