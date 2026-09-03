<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Rules\FourDigitPin;
use App\Services\PinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartnerAccountController extends Controller
{
    public function updatePin(Request $request, PinService $pins): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['vendor', 'investor'], true), 403);

        $rules = [
            'pin' => ['required', 'string', new FourDigitPin, 'confirmed'],
        ];

        if ($pins->hasPin($user)) {
            $rules['current_pin'] = ['required', 'string', new FourDigitPin];
        }

        $data = $request->validate($rules);

        if ($pins->hasPin($user) && ! $pins->verify($data['current_pin'], $user->pin_hash)) {
            return back()->withErrors(['current_pin' => 'Current PIN is incorrect.']);
        }

        $pins->setPin($user, $data['pin']);

        return back()->with('status', __('site.partner_account.pin_updated'));
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['vendor', 'investor'], true), 403);

        $data = $request->validate([
            'preferred_locale' => ['required', 'in:en,sw'],
        ]);

        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $prefs['preferred_locale'] = $data['preferred_locale'];
        $user->forceFill(['preferences' => $prefs])->save();

        session(['locale' => $data['preferred_locale']]);
        app()->setLocale($data['preferred_locale']);

        return back()->with('status', __('site.partner_account.preferences_updated'));
    }
}
