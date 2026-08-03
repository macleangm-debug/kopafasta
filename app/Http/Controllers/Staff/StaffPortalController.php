<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Services\StaffPortalService;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffPortalController extends Controller
{
    public function dashboard(Request $request, StaffPortalService $portal, RoleService $roles): View
    {
        $user = $request->user('admin');

        return view('staff.dashboard', [
            'user'         => $user,
            'shortcuts'    => $portal->shortcuts($user),
            'roleLabel'    => $roles->label($user->role),
            'hasConsole'   => $roles->hasConsoleAccess($user),
            'twoFactorOn'       => app(WebTwoFactorAuthService::class)->isEnabled($user),
            'twoFactorRequired' => app(WebTwoFactorAuthService::class)->isRequired('staff'),
        ]);
    }

    public function security(Request $request, WebTwoFactorAuthService $twoFactor, RoleService $roles): View
    {
        $user = $request->user('admin');

        return view('staff.security', [
            'user'              => $user,
            'twoFactorOn'       => $twoFactor->isEnabled($user),
            'required'          => $twoFactor->isRequired('staff'),
            'recoveryRemaining' => $twoFactor->remainingRecoveryCodeCount($user),
            'confirmedAt'       => $user->two_factor_confirmed_at,
            'hasConsole'        => $roles->hasConsoleAccess($user),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, WebTwoFactorAuthService $twoFactor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user('admin');
        $codes = $twoFactor->regenerateRecoveryCodes($user, $data['code'], $request);

        if ($codes === null) {
            return back()->withErrors(['code' => 'Invalid authenticator code. Try again with a fresh code from your app.']);
        }

        return redirect()
            ->route('staff.security')
            ->with('status', 'New recovery codes generated. Save them now — they are shown only once.')
            ->with('fresh_recovery_codes', $codes);
    }
}
