<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\CountrySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function update(Request $request, CountrySettingsService $countries): RedirectResponse
    {
        $code = strtoupper($request->validate([
            'country' => ['required', 'string', 'size:2'],
        ])['country']);

        if (! in_array($code, $countries->codes(), true)) {
            return back();
        }

        $profile = $countries->forCode($code);
        if (! ($profile['active'] ?? false)) {
            return back()->with('warning', __('site.locale.country_coming_soon', ['country' => $profile['name'] ?? $code]));
        }

        $request->session()->put('country', $code);

        if ($code !== 'TZ') {
            return redirect()->route('site.country', strtolower($code));
        }

        return back();
    }
}
