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
    public function dashboard(Request $request, StaffPortalService $portal, RoleService $roles): View|RedirectResponse
    {
        $user = $request->user('admin');

        if ($roles->hasConsoleAccess($user)) {
            return redirect()->route($roles->homeRoute($user));
        }

        return view('staff.dashboard', [
            'user'         => $user,
            'shortcuts'    => $portal->shortcuts($user),
            'roleLabel'    => $roles->label($user->role),
            'hasConsole'   => false,
            'twoFactorOn'       => app(WebTwoFactorAuthService::class)->isEnabled($user),
            'twoFactorRequired' => app(WebTwoFactorAuthService::class)->isRequired('staff'),
        ]);
    }

    public function security(): RedirectResponse
    {
        return redirect()->route('admin.settings.account-security');
    }
}
