<?php

namespace App\Support;

final class RatePercent
{
    /** Accept stored decimal (0.035) or admin percent input (3.5). */
    public static function toDecimal(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $v = (float) $value;

        return $v > 1 ? round($v / 100, 4) : round($v, 4);
    }

    public static function toPercent(?float $decimal): ?float
    {
        if ($decimal === null) {
            return null;
        }

        return round((float) $decimal * 100, 2);
    }

    /** For form fields: accept stored decimal or a repopulated percent from old(). */
    public static function forInput(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = (float) $value;

        return $v <= 1 ? round($v * 100, 2) : round($v, 2);
    }

    public static function formatOne(float $decimal): string
    {
        $percent = (float) $decimal * 100;
        $decimals = abs($percent - round($percent)) < 0.05 ? 0 : 1;

        return number_format($percent, $decimals).'%';
    }

    public static function formatRange(float $minDecimal, float $maxDecimal): string
    {
        if (abs($minDecimal - $maxDecimal) < 0.0001) {
            return self::formatOne($minDecimal);
        }

        return self::formatOne($minDecimal).' - '.self::formatOne($maxDecimal);
    }
}
