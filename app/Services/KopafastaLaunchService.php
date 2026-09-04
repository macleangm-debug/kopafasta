<?php

namespace App\Services;

use Illuminate\Http\Request;

class KopafastaLaunchService
{
    public const SESSION_KEY = 'kf_launch';

    /** Minimum brand animation in milliseconds. */
    public const MIN_DURATION_MS = 1200;

    public function arm(?Request $request = null): void
    {
        ($request ?? request())->session()->put(self::SESSION_KEY, true);
    }

    public function consume(?Request $request = null): bool
    {
        return (bool) ($request ?? request())->session()->pull(self::SESSION_KEY);
    }

    public function pending(?Request $request = null): bool
    {
        return (bool) ($request ?? request())->session()->get(self::SESSION_KEY);
    }
}
