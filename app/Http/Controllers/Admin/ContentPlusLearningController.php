<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Plus\PlusLearningService;
use App\Services\Plus\PlusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return app(GradeSettingsController::class)->saveCategory($request);
    }

    public function saveSubject(Request $request): RedirectResponse
    {
        return app(GradeSettingsController::class)->saveSubject($request);
    }
}
