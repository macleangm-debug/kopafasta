<?php

namespace App\Support;

use App\Models\User;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Separate START registration / LOGIN from RESUME incomplete registration.
 *
 * A pending borrower session must not hijack public Login or Register CTAs.
 */
final class BorrowerRegistrationGate
{
    public static function isIncomplete(?User $user): bool
    {
        if (! $user || ($user->role ?? null) !== 'borrower') {
            return false;
        }

        return ! app(PinService::class)->hasPin($user)
            || ! app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user);
    }

    /**
     * End an incomplete-registration web session so Login / Register START can proceed.
     * Does not delete the pending account — recovery remains via password finish / setup-pin when re-authenticated.
     */
    public static function releaseIncompleteSession(Request $request): bool
    {
        $user = Auth::guard('web')->user();
        if (! self::isIncomplete($user)) {
            return false;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return true;
    }
}
