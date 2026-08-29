<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Tanzanian National ID: first 8 digits are date of birth (YYYYMMDD).
 */
class NationalIdDob
{
    /**
     * @return array{
     *   ok: bool,
     *   digits: string,
     *   date: ?Carbon,
     *   formatted: ?string,
     *   reason: ?string
     * }
     */
    public static function derive(?string $nationalId): array
    {
        $digits = preg_replace('/\D+/', '', (string) $nationalId) ?: '';
        if ($digits === '') {
            return [
                'ok' => false,
                'digits' => '',
                'date' => null,
                'formatted' => null,
                'reason' => 'missing',
            ];
        }
        if (strlen($digits) < 8) {
            return [
                'ok' => false,
                'digits' => $digits,
                'date' => null,
                'formatted' => null,
                'reason' => 'malformed',
            ];
        }

        $stamp = substr($digits, 0, 8);
        $year = (int) substr($stamp, 0, 4);
        $month = (int) substr($stamp, 4, 2);
        $day = (int) substr($stamp, 6, 2);

        if (! checkdate($month, $day, $year) || $year < 1900 || $year > ((int) date('Y'))) {
            return [
                'ok' => false,
                'digits' => $digits,
                'date' => null,
                'formatted' => null,
                'reason' => 'impossible',
            ];
        }

        $date = Carbon::createFromDate($year, $month, $day)->startOfDay();

        return [
            'ok' => true,
            'digits' => $digits,
            'date' => $date,
            'formatted' => $date->format('d M Y'),
            'reason' => null,
        ];
    }

    public static function matchesBorrower(?string $nationalId, mixed $borrowerDob): array
    {
        $derived = self::derive($nationalId);
        $borrower = null;
        if ($borrowerDob instanceof Carbon) {
            $borrower = $borrowerDob->copy()->startOfDay();
        } elseif (filled($borrowerDob)) {
            try {
                $borrower = Carbon::parse((string) $borrowerDob)->startOfDay();
            } catch (\Throwable) {
                $borrower = null;
            }
        }

        return [
            'derived' => $derived,
            'borrower' => $borrower,
            'borrower_formatted' => $borrower?->format('d M Y'),
            'match' => $derived['ok'] && $borrower instanceof Carbon
                && $derived['date']?->toDateString() === $borrower->toDateString(),
        ];
    }
}
