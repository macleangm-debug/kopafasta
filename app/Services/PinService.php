<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PinService
{
    public function hash(string $pin): string
    {
        return Hash::make($pin);
    }

    public function verify(string $pin, ?string $hash): bool
    {
        if (! $hash) {
            return false;
        }

        return Hash::check($pin, $hash);
    }

    public function setPin(User $user, string $pin): void
    {
        $user->forceFill([
            'pin_hash'   => $this->hash($pin),
            'pin_set_at' => now(),
        ])->save();
    }

    public function hasPin(User $user): bool
    {
        return ! empty($user->pin_hash);
    }
}
