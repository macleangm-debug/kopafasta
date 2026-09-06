<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\Setting;

class ChatbotContentService
{
    public const SETTING_KEY = 'support.chatbot_entries';

    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        $stored = Setting::get(self::SETTING_KEY);

        if (is_array($stored) && $stored !== []) {
            return $this->normaliseEntries(array_merge($stored, $this->runtimeProductEntries()));
        }

        return $this->normaliseEntries(array_merge(config('chatbot.default_entries', []), $this->runtimeProductEntries()));
    }

    /** @return list<array<string, mixed>> */
    private function runtimeProductEntries(): array
    {
        $products = LoanProduct::query()
            ->with('rateTiers')
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(12)
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $rates = app(DisplayedRateService::class);
        $linesEn = [];
        $linesSw = [];
        foreach ($products as $product) {
            $name = (string) ($product->name ?? $product->code);
            $rateLabel = $rates->formatBorrowerRateRange($product);
            $min = format_money((float) $product->min_amount, false, 0);
            $max = format_money((float) $product->max_amount, false, 0);
            $fee = quoted_application_fee(null, $product);
            $feeLabel = $fee > 0 ? format_money((float) $fee, false, 0) : '—';
            $linesEn[] = "{$name} ({$product->code}): {$min}–{$max}".($rateLabel !== '' ? ", {$rateLabel}" : '').", application fee {$feeLabel}";
            $linesSw[] = "{$name} ({$product->code}): {$min}–{$max}".($rateLabel !== '' ? ", {$rateLabel}" : '').", ada ya ombi {$feeLabel}";
        }

        return [[
            'key' => 'products_live',
            'sort' => 1,
            'active' => true,
            'keywords' => ['rate', 'amount', 'fee', 'kiwango', 'kiasi', 'ada', 'product', 'bidhaa', 'GL', 'BL'],
            'question_en' => 'What are current product amounts and rates?',
            'question_sw' => 'Kiasi na viwango vya sasa vya bidhaa ni vip?',
            'answer_en' => "Live product settings (illustrative until offer):\n".implode("\n", $linesEn)."\nOpen a product page for full details. I will not invent approval decisions.",
            'answer_sw' => "Mipangilio hai ya bidhaa (mfano hadi ofa):\n".implode("\n", $linesSw)."\nFungua ukurasa wa bidhaa kwa maelezo kamili. Sitabuni maamuzi ya idhini.",
        ]];
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
