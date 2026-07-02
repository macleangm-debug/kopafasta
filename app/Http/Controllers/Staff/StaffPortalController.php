<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Services\StaffPortalService;
use App\Services\WebTwoFactorAuthService;
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

    public function security(Request $request, WebTwoFactorAuthService $twoFactor): View
    {
        $user = $request->user('admin');

        return view('staff.security', [
            'user'        => $user,
            'twoFactorOn' => $twoFactor->isEnabled($user),
            'required'    => $twoFactor->isRequired('staff'),
        ]);
    }
}
