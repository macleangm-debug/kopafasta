<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class PlusLesson extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'notified' => 'boolean',
            'channels' => 'array',
        ];
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PlusLessonProgress::class);
    }

    public function signedVideoUrl(string $locale = 'en'): ?string
    {
        $path = $locale === 'sw' ? $this->video_sw_path : $this->video_en_path;
        $path = $path ?: $this->video_en_path;
        if (! $path) {
            return null;
        }

        return URL::temporarySignedRoute('site.borrower.plus.lesson.video', now()->addMinutes(30), [
            'lesson' => $this->id,
            'locale' => $locale,
        ]);
    }
}
