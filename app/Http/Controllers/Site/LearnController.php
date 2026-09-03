<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PlusSubject;
use App\Models\PlusSubjectCategory;
use App\Services\Plus\PlusLearningCatalog;
use App\Services\SeoService;
use Illuminate\View\View;

class LearnController extends Controller
{
    public function index(PlusLearningCatalog $catalog): View
    {
        $categories = PlusSubjectCategory::query()
            ->where('status', 'published')
            ->with(['subjects' => fn ($q) => $q->published()->orderBy('id')])
            ->orderBy('sort')
            ->get();

        $icons = [];
        foreach ($catalog->categories() as $row) {
            $icons[$row['slug']] = $row['icon'] ?? '📘';
        }

        return view('site.learn.index', [
            'categories' => $categories,
            'icons' => $icons,
            'seo' => [
                'title' => __('seo.learn_title'),
                'description' => __('seo.learn_description'),
                'breadcrumbs' => [
                    ['name' => brand_name(), 'url' => route('site.home')],
                    ['name' => __('seo.footer_learn'), 'url' => route('site.learn')],
                ],
            ],
        ]);
    }

    public function category(string $category, PlusLearningCatalog $catalog): View
    {
        $record = PlusSubjectCategory::query()
            ->where('slug', $category)
            ->where('status', 'published')
            ->firstOrFail();

        $subjects = PlusSubject::query()
            ->published()
            ->where('plus_subject_category_id', $record->id)
            ->orderBy('id')
            ->get();

        return view('site.learn.category', [
            'category' => $record,
            'subjects' => $subjects,
            'icon' => $catalog->categoryIcon($record->slug),
            'seo' => [
                'title' => __('seo.learn_category_title', ['category' => $record->localizedTitle()]),
                'description' => __('seo.learn_description'),
                'breadcrumbs' => [
                    ['name' => brand_name(), 'url' => route('site.home')],
                    ['name' => __('seo.footer_learn'), 'url' => route('site.learn')],
                    ['name' => $record->localizedTitle(), 'url' => route('site.learn.category', $record->slug)],
                ],
            ],
        ]);
    }

    public function show(string $category, string $slug, SeoService $seo): View
    {
        $record = PlusSubjectCategory::query()
            ->where('slug', $category)
            ->where('status', 'published')
            ->firstOrFail();

        $subject = PlusSubject::query()
            ->published()
            ->where('plus_subject_category_id', $record->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $articleSeo = $seo->forArticle($subject);
        $editorial = $subject->localizedEditorial();

        return view('site.learn.show', [
            'category' => $record,
            'subject' => $subject,
            'editorial' => $editorial,
            'seo' => array_merge($articleSeo, [
                'breadcrumbs' => [
                    ['name' => brand_name(), 'url' => route('site.home')],
                    ['name' => __('seo.footer_learn'), 'url' => route('site.learn')],
                    ['name' => $record->localizedTitle(), 'url' => route('site.learn.category', $record->slug)],
                    ['name' => $subject->localizedTitle(), 'url' => route('site.learn.show', [$record->slug, $subject->slug])],
                ],
            ]),
        ]);
    }
}
