<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Staff/Admin console credential lookup — same users table + password hash.
 * Email when present; phone when email is blank (no synthetic emails).
 */
class StaffCredentialAuthService
{
    /**
     * Resolve a staff user for login.
     * Accepts email address, or phone digits when the account has no email.
     */
    public function attempt(string $identifier, string $password): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        $user = $this->findByIdentifier($identifier);
        if (! $user || ! $user->password) {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        if (! ($user->is_active ?? true)) {
            return null;
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return null;
        }

        return $user;
    }

    public function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', $identifier)->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?: '';
        if ($digits === '') {
            return null;
        }

        // Match common TZ storage shapes without inventing emails.
        return User::query()
            ->where(function ($q) use ($digits, $identifier) {
                $q->where('phone', $identifier)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', '255'.ltrim($digits, '0'))
                    ->orWhere('phone', '+'.$digits);
            })
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            })
            ->first();
    }
}
