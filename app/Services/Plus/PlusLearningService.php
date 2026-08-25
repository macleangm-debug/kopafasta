<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
use App\Models\PlusSubject;
use App\Models\PlusSubjectCategory;
use App\Models\PlusSubjectProgress;
use Illuminate\Support\Collection;

class PlusLearningService
{
    public function ensureCatalog(): void
    {
        if (PlusSubjectCategory::query()->exists() && PlusSubject::query()->count() >= 500) {
            app(PlusLearningCatalog::class)->refreshPublishedCopyIfStale();

            return;
        }

        app(PlusLearningCatalog::class)->seed();
        app(PlusLearningCatalog::class)->refreshPublishedCopyIfStale();
    }

    public function publishedQuery()
    {
        return PlusSubject::query()->published()->with('category');
    }

    public function discover(Customer $customer, ?string $search = null, ?string $category = null): array
    {
        $this->ensureCatalog();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $categories = PlusSubjectCategory::query()
            ->where('status', 'published')
            ->orderBy('sort')
            ->get();

        $published = $this->publishedQuery();
        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $published->where(function ($q) use ($like) {
                $q->where('title_en', 'like', $like)
                    ->orWhere('title_sw', 'like', $like)
                    ->orWhere('intro_en', 'like', $like)
                    ->orWhere('intro_sw', 'like', $like);
            });
        }
        if ($category) {
            $cat = PlusSubjectCategory::query()->where('slug', $category)->first();
            if ($cat) {
                $published->where('plus_subject_category_id', $cat->id);
            }
        }

        $results = $search || $category ? $published->latest('published_at')->limit(24)->get() : collect();

        $progress = PlusSubjectProgress::query()
            ->where('customer_id', $customer->id)
            ->get()
            ->keyBy('plus_subject_id');

        $forYou = $this->forYou($customer, $progress);
        $featured = $this->publishedQuery()->where('featured', true)->latest('published_at')->limit(8)->get();
        $continue = $this->continueReading($customer, $progress);
        $saved = PlusSubject::query()
            ->published()
            ->whereIn('id', $progress->whereNotNull('saved_at')->pluck('plus_subject_id'))
            ->with('category')
            ->get();
        $icons = collect(app(PlusLearningCatalog::class)->categories())
            ->mapWithKeys(fn (array $cat) => [$cat['slug'] => $cat['icon']]);

        return [
            'categories' => $categories,
            'category_icons' => $icons,
            'locale' => $locale,
            'search' => $search,
            'category' => $category,
            'results' => $results,
            'for_you' => $forYou,
            'featured' => $featured,
            'continue' => $continue,
            'saved' => $saved,
            'progress' => $progress,
        ];
    }

    public function markViewed(Customer $customer, PlusSubject $subject): PlusSubjectProgress
    {
        $row = PlusSubjectProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_subject_id' => $subject->id],
            ['started_at' => now(), 'locale' => app()->getLocale()]
        );
        $row->update([
            'viewed_at' => now(),
            'started_at' => $row->started_at ?? now(),
            'locale' => app()->getLocale(),
        ]);

        return $row->fresh();
    }

    public function markCompleted(Customer $customer, PlusSubject $subject): void
    {
        $row = $this->markViewed($customer, $subject);
        $row->update(['completed_at' => $row->completed_at ?? now(), 'last_position' => 100]);
    }

    public function toggleSaved(Customer $customer, PlusSubject $subject): PlusSubjectProgress
    {
        $row = $this->markViewed($customer, $subject);
        $row->update(['saved_at' => $row->saved_at ? null : now()]);

        return $row->fresh();
    }

    public function markActionClicked(Customer $customer, PlusSubject $subject): void
    {
        $row = $this->markViewed($customer, $subject);
        $row->update(['action_clicked_at' => now()]);
    }

    /** @return Collection<int, PlusSubject> */
    private function forYou(Customer $customer, Collection $progress): Collection
    {
        $slugs = ['money', 'saving', 'growth'];
        if (PlusBusinessEntry::query()->where('customer_id', $customer->id)->exists()) {
            $slugs = ['business', 'pricing', 'customers', 'stock'];
        }
        if (PlusGoal::query()->where('customer_id', $customer->id)->where('kind', 'school')->exists()) {
            $slugs = ['goals', 'family', 'saving'];
        }
        $ids = PlusSubjectCategory::query()->whereIn('slug', $slugs)->pluck('id');
        $seen = $progress->whereNotNull('completed_at')->pluck('plus_subject_id');

        return $this->publishedQuery()
            ->whereIn('plus_subject_category_id', $ids)
            ->whereNotIn('id', $seen)
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    /** @return Collection<int, PlusSubject> */
    private function continueReading(Customer $customer, Collection $progress): Collection
    {
        $ids = $progress
            ->filter(fn ($row) => $row->started_at && ! $row->completed_at)
            ->pluck('plus_subject_id');

        return PlusSubject::query()->published()->whereIn('id', $ids)->with('category')->limit(4)->get();
    }

    public function progressPercent(PlusSubjectProgress $row): int
    {
        if ($row->completed_at) {
            return 100;
        }
        if ($row->last_position) {
            return (int) $row->last_position;
        }

        return $row->started_at ? 40 : 0;
    }
}
