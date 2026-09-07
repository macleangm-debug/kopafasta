<?php

namespace App\Support;

class PlusArticleSteps
{
    /** Soft ceiling so a huge body never becomes dozens of empty slides. */
    public const MAX_SLIDES = 12;

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
     * Content-driven slides: short articles stay one page; longer ones follow
     * authored # headings or natural paragraph groups — never a fixed 6-slide deck.
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

        $slides = self::slidesFromContent($all);
        $opening = $slides === [] ? [] : (preg_split('/\n\s*\n/', $slides[0]) ?: [$slides[0]]);
        $cards = array_slice($slides, 1);

        return [
            'opening' => array_values(array_filter(array_map('trim', $opening))),
            'cards' => $cards,
            'slides' => $slides,
        ];
    }

    /**
     * @param  list<string>  $paras
     * @return list<string>
     */
    public static function slidesFromContent(array $paras): array
    {
        if ($paras === []) {
            return [];
        }

        $joined = implode("\n\n", $paras);
        $hasHeadings = (bool) preg_match('/^#\s+/m', $joined);
        if ($hasHeadings) {
            return self::limitSlides(self::splitByHeadings($paras));
        }

        // Short article → one reading page.
        if (count($paras) <= 3 || mb_strlen($joined) <= 900) {
            return [implode("\n\n", $paras)];
        }

        // Medium/long: ~2–3 paragraphs per slide, content-driven count.
        $perSlide = count($paras) <= 6 ? 2 : 3;

        return self::limitSlides(self::chunk($paras, $perSlide));
    }

    /**
     * @param  list<string>  $paras
     * @return list<string>
     */
    private static function splitByHeadings(array $paras): array
    {
        $slides = [];
        $bucket = [];
        foreach ($paras as $para) {
            $isHeading = (bool) preg_match('/^#\s+/', $para);
            if ($isHeading && $bucket !== []) {
                $slides[] = implode("\n\n", $bucket);
                $bucket = [];
            }
            $bucket[] = $para;
        }
        if ($bucket !== []) {
            $slides[] = implode("\n\n", $bucket);
        }

        return $slides !== [] ? $slides : [implode("\n\n", $paras)];
    }

    /**
     * @param  list<string>  $slides
     * @return list<string>
     */
    private static function limitSlides(array $slides): array
    {
        if (count($slides) <= self::MAX_SLIDES) {
            return array_values($slides);
        }

        $kept = array_slice($slides, 0, self::MAX_SLIDES - 1);
        $rest = array_slice($slides, self::MAX_SLIDES - 1);
        $kept[] = implode("\n\n", $rest);

        return $kept;
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
