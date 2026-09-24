<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\AccountWelcomeService;
use App\Services\KopafastaLaunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountWelcomeController extends Controller
{
    public function show(Request $request, AccountWelcomeService $welcome): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $payload = $welcome->forUser($user);
        if (! $payload) {
            return redirect()->to($welcome->homeUrl($user));
        }

        return view('site.account-welcome', [
            'welcome' => $payload,
        ]);
    }

    public function complete(Request $request, AccountWelcomeService $welcome): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $audience = $request->input('audience');
        $welcome->complete($user, is_string($audience) ? $audience : null);

        if ($user->customer) {
            app(\App\Services\MembershipService::class)->ensureMemberNumber($user->customer);
        }

        // Supplier welcome is an account slider, not a first-launch brand animation.
        if (($audience ?: $welcome->audienceFor($user)) !== 'supplier') {
            app(KopafastaLaunchService::class)->arm($request);
        }

        return redirect()->to($welcome->homeUrl($user->fresh()));
    }
}
