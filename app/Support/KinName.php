<?php

namespace App\Support;

class KinName
{
    public static function full(?string $first, ?string $middle, ?string $last): string
    {
        return trim(collect([$first, $middle, $last])->filter(fn (?string $part) => filled($part))->implode(' '));
    }
}
