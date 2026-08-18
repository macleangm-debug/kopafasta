<?php

if (! function_exists('pdf_text')) {
    /**
     * DomPDF-safe plain text: replace punctuation that becomes tofu boxes.
     */
    function pdf_text(mixed $value): string
    {
        $text = (string) $value;
        $map = [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            "\u{2212}" => '-',
            "\u{00B7}" => '-',
            "\u{2022}" => '-',
            "\u{2026}" => '...',
            "\u{00A0}" => ' ',
            '—' => '-',
            '–' => '-',
            '·' => '-',
        ];

        return strtr($text, $map);
    }
}

if (! function_exists('pdf_brand_mark_path')) {
    function pdf_brand_mark_path(): ?string
    {
        $mark = public_path('images/brand/kopafasta-mark.png');

        return is_file($mark) ? $mark : null;
    }
}
