<?php

namespace App\Support;

class MemberNumberFormatter
{
    /**
     * Display a member number in bank-card style groups.
     */
    public static function display(?string $memberNo): string
    {
        if (! $memberNo || $memberNo === '—') {
            return '—';
        }

        if (str_starts_with(strtoupper($memberNo), 'KPF-TZ-')) {
            $suffix = substr($memberNo, 7);
            $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $suffix) ?? '');

            if ($clean === '') {
                return strtoupper($memberNo);
            }

            $groups = str_split($clean, 4);

            return 'KPF-TZ-'.implode('-', $groups);
        }

        return strtoupper($memberNo);
    }

    /**
     * Raw value suitable for clipboard copy.
     */
    public static function raw(?string $memberNo): string
    {
        return $memberNo ? strtoupper(trim($memberNo)) : '';
    }
}
