<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlusSubject;
use App\Models\PlusSubjectCategory;
use App\Services\Plus\PlusLearningService;
use App\Services\Plus\PlusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContentPlusLearningController extends Controller
{
    public function index(PlusService $plus): View
    {
        $plus->ensureSampleContent();
        app(PlusLearningService::class)->ensureCatalog();

        return view('admin.content.plus-learning', [
            'lessons' => \App\Models\PlusLesson::query()->latest('id')->limit(24)->get(),
            'categories' => \App\Models\PlusSubjectCategory::query()->orderBy('sort')->get(),
            'subjects' => \App\Models\PlusSubject::query()->with('category')->latest('id')->limit(40)->get(),
            'subjectCount' => \App\Models\PlusSubject::query()->count(),
            'publishedCount' => \App\Models\PlusSubject::query()->where('status', 'published')->count(),
        ]);
    }

    public function saveLesson(Request $request): RedirectResponse
    {
        return app(GradeSettingsController::class)->saveLesson($request);
    }

    public function saveCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:40'],
            'title_en' => ['required', 'string', 'max:150'],
            'title_sw' => ['required', 'string', 'max:150'],
        ]);
        $slug = Str::slug($data['slug']);
        PlusSubjectCategory::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title_en' => $data['title_en'],
                'title_sw' => $data['title_sw'],
                'status' => 'published',
            ]
        );

        return back()->with('status', 'Learning category saved.');
    }

    public function saveSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plus_subject_category_id' => ['required', 'integer', 'exists:plus_subject_categories,id'],
            'title_en' => ['required', 'string', 'max:180'],
            'title_sw' => ['required', 'string', 'max:180'],
            'intro_en' => ['nullable', 'string', 'max:2000'],
            'intro_sw' => ['nullable', 'string', 'max:2000'],
            'body_en' => ['nullable', 'string', 'max:20000'],
            'body_sw' => ['nullable', 'string', 'max:20000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'action_en' => ['nullable', 'string', 'max:180'],
            'action_sw' => ['nullable', 'string', 'max:180'],
            'action_route' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:draft,published,archived'],
            'featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_title_sw' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_description_sw' => ['nullable', 'string', 'max:320'],
            'seo_indexable' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($data['title_en']);
        $base = $slug !== '' ? $slug : 'article';
        $candidate = $base;
        $i = 2;
        while (PlusSubject::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        PlusSubject::query()->create([
            'plus_subject_category_id' => $data['plus_subject_category_id'],
            'slug' => $candidate,
            'title_en' => $data['title_en'],
            'title_sw' => $data['title_sw'],
            'intro_en' => $data['intro_en'] ?? null,
            'intro_sw' => $data['intro_sw'] ?? null,
            'body_en' => $data['body_en'] ?? null,
            'body_sw' => $data['body_sw'] ?? null,
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 4),
            'action_en' => $data['action_en'] ?? null,
            'action_sw' => $data['action_sw'] ?? null,
            'action_route' => $data['action_route'] ?? 'site.borrower.plus.home',
            'status' => $data['status'],
            'featured' => $request->boolean('featured'),
            'published_at' => $data['status'] === 'published' ? now() : null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_title_sw' => $data['seo_title_sw'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_description_sw' => $data['seo_description_sw'] ?? null,
            'seo_indexable' => $request->boolean('seo_indexable', true),
        ]);

        return back()->with('status', 'Learning subject saved. Drafts stay out of the sitemap.');
    }
}
