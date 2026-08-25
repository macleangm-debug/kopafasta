<?php

namespace App\Support;

class PlusArticleSteps
{
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
     * Opening paragraphs stay on the first screen. Remaining paragraphs swipe.
     * Swipe controls length per screen — it does not turn the article into numbered school steps.
     *
     * @return array{opening: list<string>, cards: list<string>}
     */
    public static function openingAndCards(?string $intro, ?string $body = null): array
    {
        $introParas = self::fromBody($intro);
        $bodyParas = self::fromBody($body);

        if ($introParas !== [] && $bodyParas !== []) {
            return ['opening' => $introParas, 'cards' => self::chunk($bodyParas, 3)];
        }

        $paras = $introParas !== [] ? $introParas : $bodyParas;
        if (count($paras) <= 3) {
            return ['opening' => $paras, 'cards' => []];
        }

        return [
            'opening' => array_slice($paras, 0, 2),
            'cards' => self::chunk(array_slice($paras, 2), 3),
        ];
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
}
