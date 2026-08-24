<x-admin.layout title="Kopafasta Plus" heading="Kopafasta Plus" subheading="Optional subscription. Never affects Grade or Trust Score.">
    @include('admin.settings._tabs', ['active' => 'plus'])

    <form method="post" action="{{ route('admin.settings.plus.save') }}" class="space-y-6">
        @csrf
        @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="tz_price" label="Monthly price (TZS)" type="number" :value="$config['plans']['monthly']['prices']['TZ']['amount'] ?? 3000" />
            <x-admin.input name="period_days" label="Billing period (days)" type="number" :value="$config['plans']['monthly']['period_days'] ?? 30" />
        </div>
        <button class="rounded-xl bg-brand text-white px-5 py-2.5 font-semibold">Save Plus settings</button>
    </form>

    <div class="mt-8 grid lg:grid-cols-2 gap-6">
        <form method="post" action="{{ route('admin.settings.plus.lessons.save') }}" enctype="multipart/form-data" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold">Monthly Club lesson</h2>
            <x-admin.input name="month" label="Month (YYYY-MM)" :value="now()->format('Y-m')" />
            <x-admin.input name="title_en" label="Title (EN)" />
            <x-admin.input name="title_sw" label="Title (SW)" />
            <x-admin.textarea name="intro_en" label="Intro (EN)" />
            <x-admin.textarea name="intro_sw" label="Intro (SW)" />
            <x-admin.input name="action_en" label="Monthly action (EN)" />
            <x-admin.input name="action_sw" label="Monthly action (SW)" />
            <x-admin.input name="duration_minutes" label="Duration (5–10 min)" type="number" :value="7" />
            <x-admin.input name="audience" label="Audience" :value="'plus_members'" />
            <x-admin.input name="published_at" label="Publish at" type="datetime-local" />
            <label class="block text-sm">Private video (EN)
                <input type="file" name="video_en" accept="video/mp4,video/webm" class="mt-1 block w-full text-sm">
            </label>
            <label class="block text-sm">Private video (SW)
                <input type="file" name="video_sw" accept="video/mp4,video/webm" class="mt-1 block w-full text-sm">
            </label>
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save lesson</button>
            <ul class="text-sm text-gray-600 space-y-1">
                @foreach ($lessons ?? [] as $lesson)
                    <li>{{ $lesson->month }} · {{ $lesson->title_en }}</li>
                @endforeach
            </ul>
        </form>
        <form method="post" action="{{ route('admin.settings.plus.offers.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold">Targeted offer</h2>
            <x-admin.input name="title" label="Title" />
            <x-admin.textarea name="body" label="Body" />
            <x-admin.select name="tier" label="Tier" :options="['standard'=>'Standard','silver'=>'Silver','gold'=>'Gold','platinum'=>'Platinum']" />
            <x-admin.input name="country_code" label="Country (TZ/KE)" />
            <div class="flex flex-wrap gap-3 text-sm">
                @foreach (['bronze','silver','gold','platinum'] as $grade)
                    <label class="inline-flex items-center gap-1.5">
                        <input type="checkbox" name="eligible_grades[]" value="{{ $grade }}">
                        {{ ucfirst($grade) }}
                    </label>
                @endforeach
            </div>
            <label class="text-sm">Plus only <input type="checkbox" name="plus_only" value="1" checked></label>
            <label class="text-sm">Active <input type="checkbox" name="active" value="1" checked></label>
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save offer</button>
            <ul class="text-sm text-gray-600 space-y-1">
                @foreach ($offers ?? [] as $offer)
                    <li>{{ $offer->title }} · {{ $offer->tier }}</li>
                @endforeach
            </ul>
        </form>
    </div>
</x-admin.layout>
