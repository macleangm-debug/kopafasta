<?php

namespace App\Services;

use App\Models\Setting;

class ChatbotContentService
{
    public const SETTING_KEY = 'support.chatbot_entries';

    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        $stored = Setting::get(self::SETTING_KEY);

        if (is_array($stored) && $stored !== []) {
            return $this->normaliseEntries($stored);
        }

        return $this->normaliseEntries(config('chatbot.default_entries', []));
    }

    /** @param list<array<string, mixed>> $entries */
    public function saveEntries(array $entries): void
    {
        Setting::set(self::SETTING_KEY, $this->normaliseEntries($entries));
    }

    /** @return array{greeting: string, default: string, suggestions: list<string>, rules: list<array<string, mixed>>} */
    public function payload(): array
    {
        $locale = app()->getLocale();
        $isSw = str_starts_with($locale, 'sw');

        $rules = collect($this->entries())
            ->filter(fn (array $entry) => ($entry['active'] ?? true))
            ->sortBy('sort')
            ->values()
            ->map(fn (array $entry) => [
                'keywords' => $entry['keywords'] ?? [],
                'question' => $isSw ? ($entry['question_sw'] ?? $entry['question_en'] ?? '') : ($entry['question_en'] ?? ''),
                'answer'   => $isSw ? ($entry['answer_sw'] ?? $entry['answer_en'] ?? '') : ($entry['answer_en'] ?? ''),
            ])
            ->all();

        $suggestions = collect($rules)
            ->pluck('question')
            ->filter()
            ->take(4)
            ->values()
            ->all();

        return [
            'greeting'    => __('site.support.chat.greeting'),
            'default'     => __('site.support.chat.default'),
            'suggestions' => $suggestions,
            'rules'       => $rules,
        ];
    }

    /** @param list<array<string, mixed>> $entries */
    private function normaliseEntries(array $entries): array
    {
        return collect($entries)->values()->map(function (array $entry, int $index) {
            $keywords = $entry['keywords'] ?? [];
            if (is_string($keywords)) {
                $keywords = array_values(array_filter(array_map('trim', preg_split('/[,;|]+/', $keywords) ?: [])));
            }

            return [
                'key'         => (string) ($entry['key'] ?? 'entry_'.($index + 1)),
                'sort'        => (int) ($entry['sort'] ?? ($index + 1)),
                'active'      => (bool) ($entry['active'] ?? true),
                'keywords'    => array_values($keywords),
                'question_en' => (string) ($entry['question_en'] ?? ''),
                'question_sw' => (string) ($entry['question_sw'] ?? ''),
                'answer_en'   => (string) ($entry['answer_en'] ?? ''),
                'answer_sw'   => (string) ($entry['answer_sw'] ?? ''),
            ];
        })->all();
    }
}
