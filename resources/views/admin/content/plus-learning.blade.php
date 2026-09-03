<x-admin.layout title="Plus Learning" heading="Kopafasta Plus Learning" subheading="Content creates it. Marketing may promote a published article. Settings still defines categories/rules/triggers.">
    <div class="grid lg:grid-cols-2 gap-6">
        <form method="post" action="{{ route('admin.content.plus-learning.lessons.save') }}" enctype="multipart/form-data" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold text-gray-900">Monthly Club lesson</h2>
            <x-admin.input name="month" label="Month (YYYY-MM)" :value="now()->format('Y-m')" />
            <x-admin.input name="title_en" label="Title (EN)" required />
            <x-admin.input name="title_sw" label="Title (SW)" />
            <x-admin.textarea name="intro_en" label="Article (EN)" rows="5" />
            <x-admin.textarea name="intro_sw" label="Article (SW)" rows="5" />
            <x-admin.input name="action_en" label="This month’s action (EN)" />
            <x-admin.input name="action_sw" label="This month’s action (SW)" />
            <x-admin.input name="duration_minutes" label="Reading time (5–10 min)" type="number" :value="7" />
            <x-admin.input name="audience" label="Audience" :value="'plus_members'" />
            <x-admin.input name="published_at" label="Publish at" type="datetime-local" />
            <label class="block text-sm text-gray-700">Private video (EN)
                <input type="file" name="video_en" accept="video/mp4,video/webm" class="mt-1 block w-full text-sm">
            </label>
            <label class="block text-sm text-gray-700">Private video (SW)
                <input type="file" name="video_sw" accept="video/mp4,video/webm" class="mt-1 block w-full text-sm">
            </label>
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Publish lesson</button>
        </form>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            <h3 class="font-semibold">Published lessons</h3>
            @forelse ($lessons as $lesson)
                <article class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                    <p class="text-[11px] uppercase tracking-wider text-brand font-semibold">{{ $lesson->month }} · {{ $lesson->published_at ? 'Published' : 'Draft' }}</p>
                    <p class="font-semibold mt-1">{{ $lesson->title_en }}</p>
                </article>
            @empty
                <p class="text-sm text-gray-600">No lessons yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-8 grid lg:grid-cols-2 gap-6">
        <form method="post" action="{{ route('admin.content.plus-learning.categories.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold">Learning category</h2>
            <x-admin.input name="slug" label="Slug" required />
            <x-admin.input name="title_en" label="Title (EN)" required />
            <x-admin.input name="title_sw" label="Title (SW)" required />
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save category</button>
            <p class="text-xs text-gray-500">{{ $subjectCount }} subjects · {{ $publishedCount }} published.</p>
        </form>
        <form method="post" action="{{ route('admin.content.plus-learning.subjects.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-3">
            @csrf
            <h2 class="font-semibold">Learning subject</h2>
            <label class="block text-sm text-gray-700">Category
                <select name="plus_subject_category_id" class="mt-1 w-full rounded-xl border-gray-300">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->title_en }}</option>
                    @endforeach
                </select>
            </label>
            <x-admin.input name="title_en" label="Title (EN)" required />
            <x-admin.input name="title_sw" label="Title (SW)" required />
            <x-admin.textarea name="intro_en" label="Intro (EN)" rows="2" />
            <x-admin.textarea name="intro_sw" label="Intro (SW)" rows="2" />
            <x-admin.textarea name="body_en" label="Article (EN)" rows="4" />
            <x-admin.textarea name="body_sw" label="Article (SW)" rows="4" />
            <x-admin.input name="duration_minutes" label="Minutes" type="number" :value="4" />
            <x-admin.input name="action_en" label="Practical action (EN)" />
            <x-admin.input name="action_sw" label="Practical action (SW)" />
            <x-admin.input name="action_route" label="Action route" value="site.borrower.plus.money" />
            <x-admin.select name="status" label="Status" :options="['draft'=>'Draft','published'=>'Published','archived'=>'Archived']" />
            <x-admin.input name="seo_title" label="SEO title (EN, optional)" />
            <x-admin.input name="seo_title_sw" label="SEO title (SW, optional)" />
            <x-admin.textarea name="seo_description" label="Meta description (EN, optional)" rows="2" />
            <x-admin.textarea name="seo_description_sw" label="Meta description (SW, optional)" rows="2" />
            <label class="text-sm inline-flex items-center gap-2">
                <input type="hidden" name="seo_indexable" value="0">
                <input type="checkbox" name="seo_indexable" value="1" checked class="rounded border-gray-300 text-brand"> Indexable when published
            </label>
            <label class="text-sm inline-flex items-center gap-2">
                <input type="checkbox" name="featured" value="1" class="rounded border-gray-300 text-brand"> Featured
            </label>
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save subject</button>
        </form>
    </div>
</x-admin.layout>
