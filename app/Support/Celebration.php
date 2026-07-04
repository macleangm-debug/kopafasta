<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

class Celebration
{
    public const SESSION_KEY = 'celebrate';

    /** @param list<string> $reasons */
    public static function flash(array $reasons): void
    {
        session()->flash(self::SESSION_KEY, array_values(array_unique($reasons)));
    }

    public static function flashOne(string $reason): void
    {
        self::flash([$reason]);
    }

    public static function with(RedirectResponse $response, string $reason): RedirectResponse
    {
        return $response->with(self::SESSION_KEY, [$reason]);
    }

    /** @return list<string> */
    public static function reasons(): array
    {
        $value = session(self::SESSION_KEY, []);

        return is_array($value) ? array_values($value) : [];
    }

    public static function shouldCelebrate(): bool
    {
        return self::reasons() !== [];
    }
}
