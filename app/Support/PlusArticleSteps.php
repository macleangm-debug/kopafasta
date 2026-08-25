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
}
