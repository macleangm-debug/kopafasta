<x-admin.layout title="Notification center" heading="Notification center" subheading="Categories and referral leaderboard">
    @include('admin.settings.engagement._nav', ['active' => 'notifications'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @php
        $cats = $values['categories'] ?? config('gamification.notifications.categories', []);
        $allCats = ['repayment', 'application', 'promotions', 'referral', 'membership'];
    @endphp

    @include('admin.settings.engagement._guide', [
        'title' => 'How engagement notifications work',
        'summary' => 'Category checkboxes control which notification types are emphasised in the member notification center filters. The referral leaderboard settings control the public/top-referrers list on the referrals tab — not loan underwriting.',
        'borrowerSees' => [
            'Notifications page: category chips (repayment, application, promotions, referral, membership).',
            'Referrals tab: optional top-N leaderboard with masked or full names.',
        ],
        'fields' => [
            'Categories' => 'Enabled filters in the borrower notification center. Disabling a category hides it from the filter UI; existing logs remain.',
            'Leaderboard enabled' => 'Show/hide the top referrers block.',
            'Mask names' => 'Privacy: show “First *****” instead of full names.',
            'Top N' => 'How many referrers to list.',
        ],
        'tips' => [
            'Keep promotions enabled if you send loyalty / campaign in-app messages.',
            'Guarantor inbox is separate from these categories — it uses the guarantor notification templates.',
        ],
    ])

    <form method="POST" action="{{ route('admin.settings.engagement.notifications.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Notification categories</h3>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ($allCats as $cat)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="categories[]" value="{{ $cat }}" @checked(in_array($cat, $cats, true))>
                        {{ ucfirst($cat) }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid md:grid-cols-2 gap-4">
            <h3 class="md:col-span-2 text-sm font-semibold text-gray-700">Referral leaderboard</h3>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="leaderboard_enabled" value="1" @checked($leaderboard['enabled'] ?? true)> Show leaderboard on referral page</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="mask_names" value="1" @checked($leaderboard['mask_names'] ?? true)> Mask first names (First ***** )</label>
            <x-admin.input name="leaderboard_limit" label="Top N referrers" type="number" :value="$leaderboard['limit'] ?? 10" />
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
