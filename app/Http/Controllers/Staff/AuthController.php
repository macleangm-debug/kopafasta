<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('staff.auth.login');
    }

    public function login(Request $request, RoleService $roles, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        app(\App\Services\TurnstileService::class)->assertHuman($request);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::guard('admin')->user();
        Auth::guard('admin')->logout();

        if (! $roles->isStaff($user->role)) {
            throw ValidationException::withMessages([
                'email' => 'This portal is for staff accounts only.',
            ]);
        }

        if ($roles->hasConsoleAccess($user)) {
            return $this->delegateConsoleLogin($request, $user, $twoFactor);
        }

        return $this->finishStaffLogin($request, $user, $twoFactor);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        app(WebTwoFactorAuthService::class)->clearSessionVerification($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }

    protected function delegateConsoleLogin(Request $request, $user, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        $home = route(app(RoleService::class)->homeRoute($user));

        if ($twoFactor->mustEnroll($user, 'admin')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'admin', $home, $request->boolean('remember'));

            return redirect()->route('auth.two-factor.setup', ['context' => 'admin']);
        }

        if ($twoFactor->needsChallenge($user, $request, 'admin')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'admin', $home, $request->boolean('remember'));

            return redirect()->route('auth.two-factor.challenge', ['context' => 'admin']);
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $twoFactor->markSessionVerified($request);

        return redirect()->to($home);
    }

    protected function finishStaffLogin(Request $request, $user, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        if ($twoFactor->mustEnroll($user, 'staff')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'staff', route('staff.dashboard'), $request->boolean('remember'));

            return redirect()->route('auth.two-factor.setup', ['context' => 'staff']);
        }

        if ($twoFactor->needsChallenge($user, $request, 'staff')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'staff', route('staff.dashboard'), $request->boolean('remember'));

            return redirect()->route('auth.two-factor.challenge', ['context' => 'staff']);
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $twoFactor->markSessionVerified($request);

        return redirect()->route('staff.dashboard');
    }
}
