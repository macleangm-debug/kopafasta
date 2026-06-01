<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', 'in:en,sw']])['locale'];
        $request->session()->put('locale', $locale);

        return back();
    }
}
