<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\PublicPolicyService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicPolicyController extends Controller
{
    public function __construct(private PublicPolicyService $policies) {}

    public function show(string $key): View|Response
    {
        if (! in_array($key, PublicPolicyService::KEYS, true)) {
            abort(404);
        }
        if (! $this->policies->isPubliclyViewable($key)) {
            abort(404);
        }
        $doc = $this->policies->localized($key, app()->getLocale());
        if (! $doc || ! filled($doc['body'] ?? null)) {
            abort(404);
        }

        return view('site.legal.policy', [
            'key' => $key,
            'doc' => $doc,
            'html' => $this->policies->renderHtml($doc['body']),
        ]);
    }
}
