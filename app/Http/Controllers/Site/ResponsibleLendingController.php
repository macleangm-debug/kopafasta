<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Governance\LendingPolicyResolver;
use Illuminate\View\View;

class ResponsibleLendingController extends Controller
{
    public function __invoke(LendingPolicyResolver $resolver): View
    {
        $resolved = $resolver->resolve();

        return view('site.responsible-lending', [
            'public' => $resolved['public'],
            'products' => $resolved['products'],
            'document' => $resolved['document'],
        ]);
    }
}
