<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KopafastaLaunchService;
use App\Services\RoleService;
use App\Services\TurnstileService;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private RoleService $roles,
        private WebTwoFactorAuthService $twoFactor,
    ) {}

    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        app(TurnstileService::class)->assertHuman($request);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::guard('admin')->user();
        Auth::guard('admin')->logout();

        if (! $this->roles->hasConsoleAccess($user)) {
            throw ValidationException::withMessages([
                'email' => 'Your role uses the staff workspace. Sign in at '.route('staff.login'),
            ]);
        }

        $home = route($this->roles->homeRoute($user));

        if ($this->twoFactor->mustEnroll($user, 'admin')) {
            $this->twoFactor->storePendingLogin($request, $user, 'admin', 'admin', $home, $request->boolean('remember'));

            return redirect()->route('auth.two-factor.setup', ['context' => 'admin']);
        }

        if ($this->twoFactor->needsChallenge($user, $request, 'admin')) {
            $this->twoFactor->storePendingLogin($request, $user, 'admin', 'admin', $home, $request->boolean('remember'));

            return redirect()->route('auth.two-factor.challenge', ['context' => 'admin']);
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->twoFactor->markSessionVerified($request);
        app(KopafastaLaunchService::class)->arm($request);

        return redirect()->intended($home);
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $this->twoFactor->clearSessionVerification($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
