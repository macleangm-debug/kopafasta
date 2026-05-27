<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Read a system setting. Pass null key to get the Setting class for ::set() etc.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return new Setting();
        }
        return Setting::get($key, $default);
    }
}
