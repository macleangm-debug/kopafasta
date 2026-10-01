<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Support\SupportHelpLibraryService;
use Illuminate\View\View;

class SupportCenterController extends Controller
{
    public function index(SupportHelpLibraryService $help): View
    {
        $isSw = str_starts_with(app()->getLocale(), 'sw');
        $q = trim((string) request('q', ''));

        return view('site.support.index', [
            'isSw' => $isSw,
            'helpGroups' => $help->groups('member'),
            'helpCategories' => $help->categories('member'),
            'helpResults' => $q !== '' ? $help->search($q, 'member') : [],
            'helpQuery' => $q,
            'helpTopic' => (string) request('topic', ''),
            'helpArticle' => (string) request('article', ''),
            'phones' => support_phones(),
            'primaryPhone' => support_phones()[0] ?? null,
        ]);
    }
}
