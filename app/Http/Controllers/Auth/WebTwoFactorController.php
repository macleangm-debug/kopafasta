<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebTwoFactorController extends Controller
{
    public function challenge(Request $request, WebTwoFactorAuthService $twoFactor): View|RedirectResponse
    {
        $context = (string) $request->query('context', 'admin');

        if ($twoFactor->pendingLogin($request) === null && ! $request->user()) {
            return redirect()->route($context === 'staff' ? 'staff.login' : ($context === 'partner' ? 'site.login' : 'admin.login'));
        }

        return view('auth.two-factor-challenge', compact('context'));
    }

    public function verifyChallenge(Request $request, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code'    => ['required', 'string'],
            'context' => ['nullable', 'string'],
        ]);

        $pending = $twoFactor->pendingLogin($request);
        $user = $pending
            ? User::find($pending['user_id'] ?? null)
            : ($request->user('admin') ?? $request->user());

        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['code' => 'Session expired. Sign in again.']);
        }

        if (! $twoFactor->verifyCode($user, $data['code'], $request)) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        if ($pending) {
            $response = $twoFactor->completePendingLogin($request);

            return $response ?? redirect()->route('admin.dashboard');
        }

        $twoFactor->markSessionVerified($request);

        return redirect()->intended($this->intendedFor($user));
    }

    public function setup(Request $request, WebTwoFactorAuthService $twoFactor): View|RedirectResponse
    {
        $context = (string) $request->query('context', 'admin');
        $pending = $twoFactor->pendingLogin($request);
        $user = $pending
            ? User::find($pending['user_id'] ?? null)
            : ($request->user('admin') ?? $request->user());

        if (! $user) {
            return redirect()->route($context === 'staff' ? 'staff.login' : 'admin.login');
        }

        if ($twoFactor->isEnabled($user)) {
            return redirect()->to($this->postSetupRedirect($context, $user))
                ->with('status', 'Two-factor authentication is already enabled.');
        }

        $enrollment = $twoFactor->beginEnrollment($user, $request);

        return view('auth.two-factor-setup', array_merge($enrollment, compact('context')));
    }

    public function confirmSetup(Request $request, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code'    => ['required', 'string'],
            'context' => ['nullable', 'string'],
        ]);

        $pending = $twoFactor->pendingLogin($request);
        $user = $pending
            ? User::find($pending['user_id'] ?? null)
            : ($request->user('admin') ?? $request->user());

        if (! $user || ! $twoFactor->confirmEnrollment($user, $data['code'], $request)) {
            return back()->withErrors(['code' => 'Invalid code. Try again with a fresh code from your app.']);
        }

        if ($pending) {
            $response = $twoFactor->completePendingLogin($request);

            return $response ?? redirect()->route('staff.dashboard');
        }

        $twoFactor->markSessionVerified($request);

        return redirect()->route('staff.security')->with('status', 'Two-factor authentication enabled.');
    }

    protected function intendedFor(User $user): string
    {
        if ($user->role === 'vendor') {
            return route('site.partner.dashboard');
        }

        return app(\App\Services\RoleService::class)->hasConsoleAccess($user)
            ? route('admin.dashboard')
            : route('staff.dashboard');
    }

    protected function postSetupRedirect(string $context, User $user): string
    {
        return match ($context) {
            'partner' => route('site.partner.dashboard'),
            'staff'   => route('staff.security'),
            default   => route('admin.dashboard'),
        };
    }
}
