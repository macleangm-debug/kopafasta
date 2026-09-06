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

    /**
     * Progressive product chips — never dump the full catalogue into the first fee answer.
     *
     * @return list<array{code: string, name: string, url: string, summary_en: string, summary_sw: string}>
     */
    public function productOptions(): array
    {
        $rates = app(DisplayedRateService::class);

        return LoanProduct::query()
            ->with('rateTiers')
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->map(function (LoanProduct $product) use ($rates) {
                $name = $product->localizedName();
                $rateLabel = $rates->formatBorrowerRateRange($product);
                $min = format_money((float) $product->min_amount, false, 0);
                $max = format_money((float) $product->max_amount, false, 0);
                $fee = quoted_application_fee(null, $product);
                $feeLabel = $fee > 0 ? format_money((float) $fee, false, 0) : '—';

                return [
                    'code' => (string) $product->code,
                    'name' => $name,
                    'url' => route('site.products.show', $product->code),
                    'summary_en' => "{$name}: {$min}–{$max}".($rateLabel !== '' ? ", {$rateLabel}" : '').". Application fee {$feeLabel}. Other charges depend on stage and Settings for this product — open the product page for the live basis.",
                    'summary_sw' => "{$name}: {$min}–{$max}".($rateLabel !== '' ? ", {$rateLabel}" : '').". Ada ya ombi {$feeLabel}. Ada nyingine zinategemea hatua na Mipangilio ya bidhaa hii — fungua ukurasa wa bidhaa kwa msingi hai.",
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function runtimeProductEntries(): array
    {
        // Keep a short gate answer only — detailed product lines come after chip selection.
        return [[
            'key' => 'fees_progressive',
            'sort' => 2,
            'active' => true,
            'keywords' => ['fee', 'fees', 'ada', 'charges', 'application fee', 'kiwango', 'rate', 'amount', 'kiasi'],
            'question_en' => 'What fees apply?',
            'question_sw' => 'Ada gani zinatumika?',
            'answer_en' => 'Fees depend on the loan product and stage (application, post-approval, insurance, GPS where applicable). Choose a product below for the current Settings-backed summary — I will not dump the full catalogue here.',
            'answer_sw' => 'Ada zinategemea bidhaa ya mkopo na hatua (ombi, baada ya idhini, bima, GPS inapohitajika). Chagua bidhaa hapa chini kwa muhtasari wa sasa kutoka Mipangilio — sitatoa orodha nzima hapa.',
            'follow_up' => 'choose_product',
        ]];
    }

    /** @param list<array<string, mixed>> $entries */
    public function saveEntries(array $entries): void
    {
        Setting::set(self::SETTING_KEY, $this->normaliseEntries($entries));
    }

    /** @return array{greeting: string, default: string, suggestions: list<string>, rules: list<array<string, mixed>>, products: list<array<string, mixed>>, choose_product_prompt: string} */
    public function payload(): array
    {
        $locale = app()->getLocale();
        $isSw = str_starts_with($locale, 'sw');
        $products = $this->productOptions();

        $rules = collect($this->entries())
            ->filter(fn (array $entry) => ($entry['active'] ?? true))
            ->sortBy('sort')
            ->values()
            ->map(fn (array $entry) => [
                'keywords' => $entry['keywords'] ?? [],
                'question' => $isSw ? ($entry['question_sw'] ?? $entry['question_en'] ?? '') : ($entry['question_en'] ?? ''),
                'answer' => $isSw ? ($entry['answer_sw'] ?? $entry['answer_en'] ?? '') : ($entry['answer_en'] ?? ''),
                'follow_up' => $entry['follow_up'] ?? null,
            ])
            ->all();

        // Prefer fee / product questions in chips without duplicating every product name.
        $suggestions = collect($rules)
            ->pluck('question')
            ->filter()
            ->unique()
            ->take(4)
            ->values()
            ->all();

        return [
            'greeting' => __('site.support.chat.greeting'),
            'default' => __('site.support.chat.default'),
            'suggestions' => $suggestions,
            'rules' => $rules,
            'products' => collect($products)->map(fn (array $p) => [
                'code' => $p['code'],
                'name' => $p['name'],
                'url' => $p['url'],
                'summary' => $isSw ? $p['summary_sw'] : $p['summary_en'],
            ])->all(),
            'choose_product_prompt' => $isSw
                ? 'Chagua bidhaa ya mkopo:'
                : 'Choose a loan product:',
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
                'key' => (string) ($entry['key'] ?? 'entry_'.($index + 1)),
                'sort' => (int) ($entry['sort'] ?? ($index + 1)),
                'active' => (bool) ($entry['active'] ?? true),
                'keywords' => array_values($keywords),
                'question_en' => (string) ($entry['question_en'] ?? ''),
                'question_sw' => (string) ($entry['question_sw'] ?? ''),
                'answer_en' => (string) ($entry['answer_en'] ?? ''),
                'answer_sw' => (string) ($entry['answer_sw'] ?? ''),
                'follow_up' => $entry['follow_up'] ?? null,
            ];
        })->all();
    }
}
