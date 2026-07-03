<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Lang;

class ChatbotContentService
{
    /** @return array{greeting: string, default: string, suggestions: list<string>, answers: array<string, string>} */
    public function payload(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $entries = $this->entries();

        $suggestions = collect($entries)
            ->filter(fn (array $e) => $e['active'] ?? true)
            ->sortBy('sort')
            ->map(fn (array $e) => $locale === 'sw' ? ($e['question_sw'] ?: $e['question_en']) : ($e['question_en'] ?: $e['question_sw']))
            ->filter()
            ->take(6)
            ->values()
            ->all();

        $answers = ['default' => $this->translate('default', $locale)];
        $rules = [];
        foreach ($entries as $entry) {
            if (! ($entry['active'] ?? true)) {
                continue;
            }
            $answer = $locale === 'sw'
                ? ($entry['answer_sw'] ?: $entry['answer_en'])
                : ($entry['answer_en'] ?: $entry['answer_sw']);
            $key = (string) ($entry['key'] ?? '');
            if ($key !== '') {
                $answers[$key] = $answer;
            }
            $rules[] = [
                'keywords' => $entry['keywords'] ?? [],
                'answer'   => $answer,
            ];
        }

        return [
            'greeting'    => $this->translate('greeting', $locale),
            'default'     => $answers['default'],
            'suggestions' => $suggestions ?: Lang::get('site.support.suggestions', [], $locale),
            'answers'     => array_merge($this->defaultAnswers($locale), $answers),
            'rules'       => $rules,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        $stored = Setting::get('chatbot.entries');
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        return config('chatbot.default_entries', []);
    }

    /** @param list<array<string, mixed>> $entries */
    public function saveEntries(array $entries): void
    {
        Setting::set('chatbot.entries', array_values($entries));
    }

    /** @return array<string, string> */
    private function defaultAnswers(string $locale): array
    {
        return Lang::get('site.support.chat', [], $locale);
    }

    private function translate(string $key, string $locale): string
    {
        return (string) Lang::get('site.support.chat.'.$key, [], $locale);
    }

    /** Match user message to the best answer key. */
    public function matchReply(string $message, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $payload = $this->payload($locale);
        $lower = mb_strtolower(trim($message));

        foreach ($this->entries() as $entry) {
            if (! ($entry['active'] ?? true)) {
                continue;
            }
            $keywords = $entry['keywords'] ?? [];
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($lower, mb_strtolower((string) $keyword))) {
                    $key = (string) ($entry['key'] ?? 'default');

                    return $payload['answers'][$key] ?? $payload['default'];
                }
            }
        }

        return $payload['default'];
    }
}
