<?php

namespace App\Models;

use App\Support\PlusArticleSteps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlusSubject extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'published_at' => 'datetime',
            'eligible_grades' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PlusSubjectCategory::class, 'plus_subject_category_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PlusSubjectProgress::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'sw' ? ($this->title_sw ?: $this->title_en) : $this->title_en;
    }

    public function localizedIntro(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'sw' ? ($this->intro_sw ?: $this->intro_en) : ($this->intro_en ?: '');
    }

    public function localizedBody(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'sw' ? ($this->body_sw ?: $this->body_en) : ($this->body_en ?: '');
    }

    public function localizedAction(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $locale === 'sw' ? ($this->action_sw ?: $this->action_en) : $this->action_en;

        return filled($value) ? $value : null;
    }

    /** @return list<string> */
    public function localizedSteps(?string $locale = null): array
    {
        return PlusArticleSteps::fromBody($this->localizedBody($locale) ?: $this->localizedIntro($locale));
    }

    public function actionUrl(): ?string
    {
        $route = (string) ($this->action_route ?? '');
        if ($route === '') {
            return null;
        }

        try {
            return route($route);
        } catch (\Throwable) {
            return null;
        }
    }
}
