<?php

namespace App\Services;

use App\Models\Customer;

class IdentityNameService
{
    /** @return array{first_name: string, middle_name: string|null, last_name: string} */
    public function parse(?string $fullName, ?string $firstName = null, ?string $lastName = null): array
    {
        if ($firstName && $lastName) {
            $middle = null;
            if ($fullName) {
                $parts = preg_split('/\s+/', trim($fullName)) ?: [];
                $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
                if (count($parts) > 2) {
                    $middleParts = array_slice($parts, 1, -1);
                    $middle = implode(' ', $middleParts) ?: null;
                }
            }

            return [
                'first_name'  => trim($firstName),
                'middle_name' => $middle,
                'last_name'   => trim($lastName),
            ];
        }

        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

        if (count($parts) === 0) {
            return ['first_name' => '', 'middle_name' => null, 'last_name' => ''];
        }

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => $parts[0]];
        }

        if (count($parts) === 2) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => $parts[1]];
        }

        return [
            'first_name'  => $parts[0],
            'middle_name' => implode(' ', array_slice($parts, 1, -1)),
            'last_name'   => $parts[array_key_last($parts)],
        ];
    }

    /**
     * @return array{
     *   matched: bool,
     *   mismatches: list<array{field: string, label: string, registered: string|null, verified: string|null}>
     * }
     */
    public function compare(Customer $customer, array $verifiedNames): array
    {
        $fields = [
            'first_name'  => 'First name',
            'middle_name' => 'Middle name',
            'last_name'   => 'Last name',
        ];

        $mismatches = [];

        foreach ($fields as $key => $label) {
            $registered = $this->normalize($customer->{$key});
            $verified = $this->normalize($verifiedNames[$key] ?? null);

            if ($registered === '' && $verified === '') {
                continue;
            }

            if ($registered !== $verified) {
                $mismatches[] = [
                    'field'      => $key,
                    'label'      => $label,
                    'registered' => $customer->{$key} ?: '—',
                    'verified'   => $verifiedNames[$key] ?: '—',
                ];
            }
        }

        return [
            'matched'    => count($mismatches) === 0,
            'mismatches' => $mismatches,
        ];
    }

    public function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return mb_strtolower(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
