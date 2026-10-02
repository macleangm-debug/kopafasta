<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\ConsoleSecondFactorService;
use App\Services\KopafastaLaunchService;
use App\Services\RoleService;
use App\Services\StaffCredentialAuthService;
use App\Services\TurnstileService;
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

    public function login(
        Request $request,
        RoleService $roles,
        WebTwoFactorAuthService $twoFactor,
        StaffCredentialAuthService $credentials,
    ): RedirectResponse {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($data['login'] ?? $data['email'] ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages([
                'login' => 'Enter your email or phone.',
            ]);
        }

        app(TurnstileService::class)->assertHuman($request);

        $user = $credentials->attempt($identifier, $data['password']);
        if (! $user) {
            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        if (! $roles->isStaffUser($user)) {
            throw ValidationException::withMessages([
                'login' => 'This portal is for staff accounts only.',
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
        $second = app(ConsoleSecondFactorService::class);

        if ($second->mustEnroll($user, 'admin')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'admin', $home, $request->boolean('remember'));

            return redirect()->to($second->setupRedirect($user, 'admin'));
        }

        if ($second->needsChallenge($user, $request, 'admin')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'admin', $home, $request->boolean('remember'));

            return redirect()->to($second->challengeRedirect($user, 'admin'));
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $twoFactor->markSessionVerified($request);
        app(KopafastaLaunchService::class)->arm($request);

        return redirect()->to($home);
    }

    protected function finishStaffLogin(Request $request, $user, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        $home = route('staff.dashboard');
        $second = app(ConsoleSecondFactorService::class);

        if ($second->mustEnroll($user, 'staff')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'staff', $home, $request->boolean('remember'));

            return redirect()->to($second->setupRedirect($user, 'staff'));
        }

        if ($second->needsChallenge($user, $request, 'staff')) {
            $twoFactor->storePendingLogin($request, $user, 'admin', 'staff', $home, $request->boolean('remember'));

            return redirect()->to($second->challengeRedirect($user, 'staff'));
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $twoFactor->markSessionVerified($request);
        app(KopafastaLaunchService::class)->arm($request);

        return redirect()->to($home);
    }
}
