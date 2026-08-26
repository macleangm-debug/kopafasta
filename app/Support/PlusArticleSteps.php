<?php

namespace App\Support;

class PlusArticleSteps
{
    public const MAX_OPENING = 4;

    public const MAX_CARDS = 4;

    /** @return list<string> */
    public static function fromBody(?string $body): array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return [];
        }

        $parts = preg_split('/\n\s*\n/', $body) ?: [];
        $steps = [];
        foreach ($parts as $part) {
            $text = trim($part);
            if ($text === '') {
                continue;
            }
            if (preg_match('/^(Try now|Jaribu sasa)\s*:/iu', $text)) {
                continue;
            }
            $steps[] = $text;
        }

        return $steps !== [] ? $steps : [$body];
    }

    /**
     * Most of the article stays on the first screen as real paragraphs.
     * Remaining copy is at most four swipe cards — never a sentence-per-slide deck.
     *
     * @return array{opening: list<string>, cards: list<string>, slides: list<string>}
     */
    public static function openingAndCards(?string $intro, ?string $body = null): array
    {
        $introParas = self::fromBody($intro);
        $bodyParas = self::withoutLeadingDupes(self::fromBody($body), $introParas);
        $all = self::uniqueConsecutive([...$introParas, ...$bodyParas]);

        if ($all === []) {
            return ['opening' => [], 'cards' => [], 'slides' => []];
        }

        if (count($all) <= self::MAX_OPENING) {
            return [
                'opening' => $all,
                'cards' => [],
                'slides' => self::slidesFrom($all, []),
            ];
        }

        $opening = array_slice($all, 0, self::MAX_OPENING);
        $rest = array_slice($all, self::MAX_OPENING);
        $cards = self::chunkToMax($rest, self::MAX_CARDS);

        return [
            'opening' => $opening,
            'cards' => $cards,
            'slides' => self::slidesFrom($opening, $cards),
        ];
    }

    /**
     * @param  list<string>  $paras
     * @param  list<string>  $skip
     * @return list<string>
     */
    private static function withoutLeadingDupes(array $paras, array $skip): array
    {
        if ($paras === [] || $skip === []) {
            return $paras;
        }

        $skipNorm = array_map(fn (string $p) => mb_strtolower(trim($p)), $skip);
        $out = [];
        foreach ($paras as $para) {
            $norm = mb_strtolower(trim($para));
            if ($out === [] && in_array($norm, $skipNorm, true)) {
                continue;
            }
            $out[] = $para;
        }

        return $out;
    }

    /**
     * @param  list<string>  $paras
     * @return list<string>
     */
    private static function uniqueConsecutive(array $paras): array
    {
        $out = [];
        $last = null;
        foreach ($paras as $para) {
            $norm = mb_strtolower(trim($para));
            if ($norm === '' || $norm === $last) {
                continue;
            }
            $out[] = $para;
            $last = $norm;
        }

        return $out;
    }

    /**
     * @param  list<string>  $paras
     * @return list<string>
     */
    private static function chunkToMax(array $paras, int $maxCards): array
    {
        if ($paras === []) {
            return [];
        }

        $size = (int) ceil(count($paras) / max(1, $maxCards));

        return self::chunk($paras, max(1, $size));
    }

    /**
     * @param  list<string>  $paras
     * @return list<string>
     */
    private static function chunk(array $paras, int $size): array
    {
        $out = [];
        foreach (array_chunk(array_values($paras), max(1, $size)) as $group) {
            $out[] = implode("\n\n", $group);
        }

        return $out;
    }

    /**
     * @param  list<string>  $opening
     * @param  list<string>  $cards
     * @return list<string>
     */
    public static function slidesFrom(array $opening, array $cards): array
    {
        $slides = [];
        if ($opening !== []) {
            $slides[] = implode("\n\n", $opening);
        }
        foreach ($cards as $card) {
            $text = trim((string) $card);
            if ($text !== '') {
                $slides[] = $text;
            }
        }

        return $slides;
    }

    /**
     * @return list<array{type: string, text?: string, html?: string}>
     */
    public static function blocks(string $slide): array
    {
        $paras = preg_split('/\n\s*\n/', trim($slide)) ?: [];
        $blocks = [];
        foreach ($paras as $i => $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            if (preg_match('/^#\s+(.+)/s', $para, $m)) {
                $blocks[] = ['type' => 'h', 'text' => trim($m[1])];
                continue;
            }
            $shortTitle = $i === 0
                && mb_strlen($para) <= 72
                && ! str_contains($para, "\n")
                && substr_count($para, '.') === 0;
            if ($shortTitle) {
                $blocks[] = ['type' => 'h', 'text' => $para];
                continue;
            }
            $blocks[] = ['type' => 'p', 'html' => self::inlineHtml($para)];
        }

        return $blocks;
    }

    private static function inlineHtml(string $text): string
    {
        $escaped = e($text);

        return (string) preg_replace(
            '/\*\*(.+?)\*\*/u',
            '<strong class="font-bold text-gray-900">$1</strong>',
            $escaped
        );
    }
}
