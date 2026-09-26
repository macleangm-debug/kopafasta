<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cookie;

class AccountThemeService
{
    public const COOKIE = 'kf_account_theme';

    public const PREFERENCE_KEY = 'account_theme';

    public function resolved(?User $user = null): string
    {
        $fromUser = data_get($user?->preferences, self::PREFERENCE_KEY);
        if ($this->valid($fromUser)) {
            return $fromUser;
        }

        $fromCookie = request()->cookie(self::COOKIE);
        if ($this->valid($fromCookie)) {
            return $fromCookie;
        }

        return 'light';
    }

    public function persist(?User $user, string $theme): void
    {
        $theme = $this->valid($theme) ? $theme : 'light';

        if ($user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $prefs[self::PREFERENCE_KEY] = $theme;
            $user->forceFill(['preferences' => $prefs])->save();
        }

        Cookie::queue(cookie(self::COOKIE, $theme, 60 * 24 * 365, '/', null, null, false, false, 'lax'));
    }

    public function valid(mixed $theme): bool
    {
        return in_array($theme, ['light', 'dark'], true);
    }
}
