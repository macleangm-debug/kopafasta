<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConsoleSecondFactorService;
use App\Services\RoleService;
use App\Services\UserAccountService;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    public function show(Request $request, RoleService $roles): View|RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $uid = (int) $request->query('uid', 0);

        if ($token === '' || $uid < 1) {
            return redirect()->route('staff.login')
                ->withErrors(['email' => 'This password setup link is invalid or incomplete.']);
        }

        $user = User::query()->find($uid);
        if (! $user || ! $roles->isStaffUser($user)) {
            return redirect()->route('staff.login')
                ->withErrors(['email' => 'This password setup link is invalid.']);
        }

        return view('staff.auth.password-setup', [
            'token' => $token,
            'uid' => $user->id,
            'email' => (string) $request->query('email', app(UserAccountService::class)->passwordBrokerKey($user)),
            'name' => $user->name,
        ]);
    }

    public function store(
        Request $request,
        RoleService $roles,
        UserAccountService $accounts,
        WebTwoFactorAuthService $twoFactor,
        ConsoleSecondFactorService $secondFactor,
    ): RedirectResponse {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'max:190'],
            'uid' => ['required', 'integer', 'min:1'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->find((int) $data['uid']);
        if (! $user || ! $roles->isStaffUser($user)) {
            return back()->withErrors(['password' => 'Invalid setup link.']);
        }

        $brokerKey = $accounts->passwordBrokerKey($user);
        if ((string) $data['email'] !== $brokerKey) {
            return back()->withErrors(['password' => 'This setup link is invalid.']);
        }

        $table = config('auth.passwords.users.table', 'password_reset_tokens');
        $row = DB::table($table)->where('email', $brokerKey)->first();
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        $valid = $row
            && Hash::check($data['token'], $row->token)
            && $row->created_at
            && now()->subMinutes($expireMinutes)->lte($row->created_at);

        if (! $valid) {
            return back()->withErrors(['password' => 'This setup link is invalid or has expired. Ask Admin for a new link.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'password_changed_at' => now(),
        ])->save();

        DB::table($table)->where('email', $brokerKey)->delete();

        $context = $roles->hasConsoleAccess($user) ? 'admin' : 'staff';
        $home = $context === 'admin'
            ? route($roles->homeRoute($user))
            : route('staff.dashboard');

        // Continue the same secure-access journey into second-step enrollment.
        if ($secondFactor->mustEnroll($user, $context)) {
            $twoFactor->storePendingLogin($request, $user, 'admin', $context, $home, false);

            return redirect()->to($secondFactor->setupRedirect($user, $context))
                ->with('status', 'Password saved. Set up your security verification next.');
        }

        return redirect()->route('staff.login')
            ->with('status', 'Password set. Sign in with your Staff or Console credentials.');
    }
}
