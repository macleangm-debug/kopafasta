<x-admin.layout title="Kopafasta Plus" heading="Kopafasta Plus" subheading="Optional subscription. Never affects Grade or Trust Score.">
    @include('admin.settings._tabs', ['active' => 'plus'])

    <div class="mb-6 rounded-xl bg-brand/5 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700 space-y-1">
        <p><strong>Billing period (days)</strong> is how long one payment covers. Use <strong>365</strong> for a yearly plan. The price next to it is the amount charged for that whole period — not a monthly fee unless the period is 30 days.</p>
        <p><strong>Monthly Club lesson</strong> is a short article (and optional private video) members open in Learn. Sample lessons are loaded so you can see the layout; replace them with your own.</p>
        <p><strong>Targeted offer</strong> is a partner or Club offer shown only in Plus Offers. You choose grade, country, and whether it is Plus-only. It never changes Grade or Trust.</p>
    </div>

    <x-admin.settings-editor
        action="{{ route('admin.settings.plus.save') }}"
        submit-label="Save Plus billing"
        class="mb-8"
    >
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="tz_price" label="Subscription price (TZS)" type="number" :value="$config['plans']['monthly']['prices']['TZ']['amount'] ?? 3000" help="Charged once for the period below. Same payment page as other customer payments." />
            <x-admin.input name="period_days" label="Subscription length (days)" type="number" :value="$config['plans']['monthly']['period_days'] ?? 365" help="365 = one year. 30 = one month. This is the number of days the payment unlocks Plus." />
        </div>
    </x-admin.settings-editor>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="space-y-4">
            <form method="post" action="{{ route('admin.settings.plus.lessons.save') }}" enctype="multipart/form-data" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
                @csrf
                <div>
                    <h2 class="font-semibold text-gray-900">Monthly Club lesson</h2>
                    <p class="text-sm text-gray-600 mt-1">A 5–10 minute article for Plus members. Title and intro show in Learn; the monthly action sits in a box at the bottom. Video is optional and stays private.</p>
                </div>
                <x-admin.input name="month" label="Month (YYYY-MM)" :value="now()->format('Y-m')" />
                <x-admin.input name="title_en" label="Title (EN)" />
                <x-admin.input name="title_sw" label="Title (SW)" />
                <x-admin.textarea name="intro_en" label="Article (EN)" rows="5" />
                <x-admin.textarea name="intro_sw" label="Article (SW)" rows="5" />
                <x-admin.input name="action_en" label="This month’s action (EN)" />
                <x-admin.input name="action_sw" label="This month’s action (SW)" />
                <x-admin.input name="duration_minutes" label="Reading time (5–10 min)" type="number" :value="7" />
                <x-admin.input name="audience" label="Audience" :value="'plus_members'" />
                <x-admin.input name="published_at" label="Publish at" type="datetime-local" help="Leave empty to save as draft. Set now (or earlier) to show it in Learn." />
                <label class="block text-sm text-gray-700">Private video (EN)
                    <input type="file" name="video_en" accept="video/mp4,video/webm" class="mt-1 block w-full text-sm">
                </label>
                <label class="block text-sm text-gray-700">Private video (SW)
                    <input type="file" name="video_sw" accept="video/mp4,video/webm" class="mt-1 block w-full text-sm">
                </label>
                <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Publish lesson</button>
            </form>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
                <h3 class="font-semibold text-gray-900">Published lessons</h3>
                <p class="text-xs text-gray-500">This is the order members see in Learn.</p>
                @forelse ($lessons ?? [] as $lesson)
                    <article class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                        <p class="text-[11px] uppercase tracking-wider text-brand font-semibold">{{ $lesson->month }} · {{ $lesson->duration_minutes }} min · {{ $lesson->published_at ? 'Published' : 'Draft' }}</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $lesson->title_en }}</p>
                        @if ($lesson->title_sw)
                            <p class="text-sm text-gray-600">{{ $lesson->title_sw }}</p>
                        @endif
                        <p class="text-sm text-gray-600 mt-2 line-clamp-3">{{ $lesson->intro_en }}</p>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No lessons yet. The sample articles above appear after you open this page once.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <form method="post" action="{{ route('admin.settings.plus.offers.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
                @csrf
                <div>
                    <h2 class="font-semibold text-gray-900">Targeted offer</h2>
                    <p class="text-sm text-gray-600 mt-1">A short offer card in the Plus Offers room. Use it for a partner deal or a Club challenge. Tick the grades that should see it. Plus-only means it stays inside Kopafasta Plus and is not mixed with lending.</p>
                </div>
                <x-admin.input name="title" label="Title" />
                <x-admin.textarea name="body" label="What the member gets" rows="4" />
                <x-admin.select name="tier" label="Offer tier" :options="['standard'=>'Standard','silver'=>'Silver','gold'=>'Gold','platinum'=>'Platinum']" />
                <x-admin.input name="country_code" label="Country (TZ or KE)" help="Leave blank for every country." />
                <p class="text-xs font-semibold text-gray-700">Visible to grades</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    @foreach (['bronze','silver','gold','platinum'] as $grade)
                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" name="eligible_grades[]" value="{{ $grade }}" class="rounded border-gray-300 text-brand">
                            {{ ucfirst($grade) }}
                        </label>
                    @endforeach
                </div>
                <label class="text-sm inline-flex items-center gap-2">
                    <input type="checkbox" name="plus_only" value="1" checked class="rounded border-gray-300 text-brand">
                    Plus members only
                </label>
                <label class="text-sm inline-flex items-center gap-2">
                    <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300 text-brand">
                    Active
                </label>
                <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save offer</button>
            </form>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
                <h3 class="font-semibold text-gray-900">Live offers</h3>
                @forelse ($offers ?? [] as $offer)
                    <article class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                        <p class="text-[11px] uppercase tracking-wider text-brand font-semibold">{{ $offer->tier }} · {{ $offer->active ? 'Active' : 'Off' }}{{ $offer->plus_only ? ' · Plus only' : '' }}</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $offer->title }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $offer->body }}</p>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No offers yet. A sample offer is created when this page is opened.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-8 grid lg:grid-cols-2 gap-6">
        <form method="post" action="{{ route('admin.settings.plus.categories.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold text-gray-900">Learning category</h2>
            <p class="text-sm text-gray-600">Customer-facing names. Archive subjects instead of deleting history.</p>
            <x-admin.input name="slug" label="Slug" />
            <x-admin.input name="title_en" label="Title (EN)" />
            <x-admin.input name="title_sw" label="Title (SW)" />
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save category</button>
            <p class="text-xs text-gray-500">{{ $subjectCount ?? 0 }} subjects in catalogue · {{ $publishedCount ?? 0 }} published to members.</p>
        </form>
        <form method="post" action="{{ route('admin.settings.plus.subjects.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold text-gray-900">Learning subject</h2>
            <p class="text-sm text-gray-600">Keep new articles as Draft until reviewed. Do not dump unreviewed financial advice on members.</p>
            <label class="block text-sm text-gray-700">Category
                <select name="plus_subject_category_id" class="mt-1 w-full rounded-xl border-gray-300">
                    @foreach ($categories ?? [] as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->title_en }}</option>
                    @endforeach
                </select>
            </label>
            <x-admin.input name="title_en" label="Title (EN)" />
            <x-admin.input name="title_sw" label="Title (SW)" />
            <x-admin.textarea name="intro_en" label="Intro (EN)" rows="2" />
            <x-admin.textarea name="intro_sw" label="Intro (SW)" rows="2" />
            <x-admin.textarea name="body_en" label="Article (EN)" rows="4" />
            <x-admin.textarea name="body_sw" label="Article (SW)" rows="4" />
            <x-admin.input name="duration_minutes" label="Minutes" type="number" :value="4" />
            <x-admin.input name="action_en" label="Practical action (EN)" />
            <x-admin.input name="action_sw" label="Practical action (SW)" />
            <x-admin.input name="action_route" label="Action route" value="site.borrower.plus.money" />
            <x-admin.select name="status" label="Status" :options="['draft'=>'Draft','published'=>'Published','archived'=>'Archived']" />
            <label class="text-sm inline-flex items-center gap-2">
                <input type="checkbox" name="featured" value="1" class="rounded border-gray-300 text-brand"> Featured
            </label>
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save subject</button>
        </form>
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
