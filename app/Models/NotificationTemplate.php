<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = ['name', 'code', 'locale', 'channel', 'subject', 'body', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Prefer the requested locale, then English, then any active row for the code.
     */
    public static function resolveActive(string $code, ?string $locale = null): ?self
    {
        $locale = $locale ?: app()->getLocale() ?: 'en';

        $preferred = static::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->where('locale', $locale)
            ->first();

        if ($preferred) {
            return $preferred;
        }

        if ($locale !== 'en') {
            $english = static::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->where('locale', 'en')
                ->first();
            if ($english) {
                return $english;
            }
        }

        return static::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->orderBy('locale')
            ->first();
    }
}
