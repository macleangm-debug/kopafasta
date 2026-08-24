<?php

namespace App\Console\Commands;

use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\PlusSubscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class RemindUnwatchedPlusLessons extends Command
{
    protected $signature = 'plus:remind-unwatched';

    protected $description = 'Remind Plus members about an unwatched published monthly lesson after re-checking state.';

    public function handle(NotificationService $notifications): int
    {
        $lesson = PlusLesson::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->first();

        if (! $lesson) {
            return self::SUCCESS;
        }

        $subscribers = PlusSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('customer')
            ->get();

        foreach ($subscribers as $subscription) {
            $customer = $subscription->customer;
            if (! $customer) {
                continue;
            }

            $watched = PlusLessonProgress::query()
                ->where('customer_id', $customer->id)
                ->where('plus_lesson_id', $lesson->id)
                ->whereNotNull('completed_at')
                ->exists();
            if ($watched) {
                continue;
            }

            $notifications->notifyCustomer($customer, 'plus_lesson_unwatched', [
                'lesson' => $lesson->title_en,
                '_fallback_body' => 'Your Kopafasta Plus monthly lesson is ready. Watch it when you have 5–10 quiet minutes.',
            ]);
        }

        return self::SUCCESS;
    }
}
