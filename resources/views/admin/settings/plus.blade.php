<x-admin.layout title="Kopafasta Plus" heading="Kopafasta Plus" subheading="Optional subscription. Never affects Grade or Trust Score.">
    @include('admin.settings._tabs', ['active' => 'plus'])

    <div class="mb-6 rounded-xl bg-brand/5 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700 space-y-1">
        <p><strong>Billing period</strong> is monthly or yearly. The price is charged once for that whole period — not a monthly fee unless you choose monthly.</p>
        <p>Offers and lessons are operational work. Create them from Growth and Content, not from this Settings page.</p>
    </div>

    <x-admin.settings-editor
        action="{{ route('admin.settings.plus.save') }}"
        submit-label="Save Plus billing"
        class="mb-8"
    >
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.select
                name="billing_cycle"
                label="Billing period"
                :options="['monthly' => 'Monthly', 'yearly' => 'Yearly']"
                :value="$billingCycle ?? 'yearly'"
                help="Monthly = 30 days. Yearly = 365 days. One payment covers the whole period."
                required
            />
            <x-admin.input
                name="tz_price"
                label="Subscription price (TZS)"
                :money="true"
                :value="$config['plans']['monthly']['prices']['TZ']['amount'] ?? 3000"
                help="Charged once for the period on the left. Same payment page as other customer payments."
                required
            />
        </div>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="reports_enabled" value="0">
                <input type="checkbox" name="reports_enabled" value="1" class="rounded border-gray-300 text-brand" @checked($config['reports']['enabled'] ?? true)>
                Monthly reports enabled
            </label>
            <x-admin.input
                name="reports_generation_day"
                label="Generate on day (1–5 of following month)"
                type="number"
                :value="$config['reports']['generation_day'] ?? 1"
            />
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="reports_insights" value="0">
                <input type="checkbox" name="reports_insights" value="1" class="rounded border-gray-300 text-brand" @checked($config['reports']['insights'] ?? true)>
                Plain-language insights
            </label>
        </div>
    </x-admin.settings-editor>

    <div class="grid md:grid-cols-2 gap-4 mb-8">
        <a href="{{ route('admin.growth.offers.index') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30">
            <p class="font-semibold">Offers are managed from Growth</p>
            <p class="text-sm text-gray-600 mt-1">Create and publish Plus offers in the Growth workspace. This page only keeps billing and trigger rules.</p>
            <p class="text-sm font-semibold text-brand mt-3">Open Growth → Offers</p>
        </a>
        <a href="{{ route('admin.content.plus-learning') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30">
            <p class="font-semibold">Plus Learning is managed from Content</p>
            <p class="text-sm text-gray-600 mt-1">Categories, subjects and Monthly Club live under More → Plus Learning. Marketing can promote a published article.</p>
            <p class="text-sm font-semibold text-brand mt-3">Open Plus Learning →</p>
        </a>
        <a href="{{ route('admin.promotions.index') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30 md:col-span-2">
            <p class="font-semibold">Campaigns are managed from Growth</p>
            <p class="text-sm text-gray-600 mt-1">Launch audience, message and timing from Growth → Campaigns. Fee discounts still use the existing promotion engine.</p>
            <p class="text-sm font-semibold text-brand mt-3">Open Campaigns →</p>
        </a>
    </div>

    <form method="post" action="{{ route('admin.settings.plus.notifications.save') }}" class="mt-8 bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
        @csrf
        @method('PUT')
        <h2 class="font-semibold text-gray-900">Plus notification triggers</h2>
        <p class="text-sm text-gray-600">Active/inactive lives here. English/Swahili templates, channels, quiet hours and cadence stay in Settings Hub → Transactional messaging. Each send re-checks current state (stop conditions).</p>
        @foreach ($triggers ?? [] as $code => $label)
            <input type="hidden" name="known[]" value="{{ $code }}">
            <label class="flex items-center justify-between gap-3 rounded-xl ring-1 ring-gray-200 px-4 py-3">
                <span class="text-sm">{{ $label }} <code class="text-xs text-gray-400">{{ $code }}</code></span>
                <input type="checkbox" name="triggers[]" value="{{ $code }}"
                       @checked(($notifications[$code]['active'] ?? true))
                       class="rounded border-gray-300 text-brand">
            </label>
        @endforeach
        <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save Plus triggers</button>
    </form>
</x-admin.layout>
