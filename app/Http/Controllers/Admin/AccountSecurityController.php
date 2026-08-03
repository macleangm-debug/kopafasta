<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function show(Request $request, WebTwoFactorAuthService $twoFactor, RoleService $roles): View
    {
        $user = $request->user('admin');
        $context = $roles->hasConsoleAccess($user) ? 'admin' : 'staff';

        return view('admin.settings.account-security', [
            'user'              => $user,
            'twoFactorOn'       => $twoFactor->isEnabled($user),
            'required'          => $twoFactor->isRequired($context),
            'setupContext'      => $context,
            'recoveryRemaining' => $twoFactor->remainingRecoveryCodeCount($user),
            'confirmedAt'       => $user->two_factor_confirmed_at,
            'canManageSettings' => $user->hasPermission('settings.manage'),
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
            ->route('admin.settings.account-security')
            ->with('status', 'New recovery codes generated. Save them now — they are shown only once.')
            ->with('fresh_recovery_codes', $codes);
    }
}
