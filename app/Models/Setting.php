<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['key', 'value'];

    public $timestamps = true;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', function () {
            return static::query()->pluck('value', 'key')->toArray();
        });

        $value = $all[$key] ?? $default;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded))) {
                return $decoded;
            }
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = is_array($value) || is_bool($value) ? json_encode($value) : (string) $value;

        static::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        Cache::forget('settings.all');
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            $stored = is_array($v) || is_bool($v) ? json_encode($v) : (string) $v;
            static::query()->updateOrCreate(['key' => $k], ['value' => $stored]);
        }
        Cache::forget('settings.all');
    }

    public static function group(string $prefix): array
    {
        $all = Cache::rememberForever('settings.all', function () {
            return static::query()->pluck('value', 'key')->toArray();
        });

        $out = [];
        foreach ($all as $k => $v) {
            if (str_starts_with($k, $prefix . '.')) {
                $short = substr($k, strlen($prefix) + 1);
                if (is_string($v)) {
                    $decoded = json_decode($v, true);
                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded))) {
                        $v = $decoded;
                    }
                }
                $out[$short] = $v;
            }
        }
        return $out;
    }
}
