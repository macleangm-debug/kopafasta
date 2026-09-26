<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\AccountThemeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountThemeController extends Controller
{
    public function update(Request $request, AccountThemeService $themes): Response
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $themes->persist($request->user(), $data['theme']);

        return response()->noContent();
    }
}
