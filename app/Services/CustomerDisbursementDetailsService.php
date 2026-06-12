<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;

class CustomerDisbursementDetailsService
{
    public const METHOD_MOBILE = 'mobile_money';

    public const METHOD_BANK = 'bank';

    /** @var array<string, string> */
    public const MOBILE_PROVIDERS = [
        'mpesa'     => 'Vodacom M-PESA',
        'airtel'    => 'Airtel Money',
        'mixx'      => 'Mixx by Yas',
        'halopesa'  => 'HaloPesa',
    ];

    public function isComplete(Customer $customer): bool
    {
        $method = (string) ($customer->preferred_disbursement_method ?? '');

        if ($method === self::METHOD_MOBILE) {
            return filled($customer->disbursement_mobile_provider)
                && filled($customer->disbursement_mobile_number)
                && filled($customer->disbursement_mobile_account_name);
        }

        if ($method === self::METHOD_BANK) {
            return filled($customer->disbursement_bank_name)
                && filled($customer->disbursement_bank_account_name)
                && filled($customer->disbursement_bank_account_number);
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function snapshotFromCustomer(Customer $customer): array
    {
        $method = (string) ($customer->preferred_disbursement_method ?? '');

        return [
            'method'                  => $method,
            'method_label'            => $this->methodLabel($method),
            'mobile_provider'         => $customer->disbursement_mobile_provider,
            'mobile_provider_label'   => $this->providerLabel($customer->disbursement_mobile_provider),
            'mobile_number'           => $customer->disbursement_mobile_number,
            'mobile_account_name'     => $customer->disbursement_mobile_account_name,
            'bank_name'               => $customer->disbursement_bank_name,
            'bank_account_name'       => $customer->disbursement_bank_account_name,
            'bank_account_number'     => $customer->disbursement_bank_account_number,
            'bank_branch'             => $customer->disbursement_bank_branch,
        ];
    }

    /** @return array<string, mixed> */
    public function snapshotForApplication(LoanApplication $application): array
    {
        $stored = $application->disbursement_details_snapshot;

        if (is_array($stored) && filled($stored['method'] ?? null)) {
            return $stored;
        }

        $application->loadMissing('customer');

        return $this->snapshotFromCustomer($application->customer);
    }

    public function disbursementDetailsConfirmed(LoanApplication $application): bool
    {
        return filled($application->disbursement_details_confirmed_at)
            && is_array($application->disbursement_details_snapshot)
            && filled($application->disbursement_details_snapshot['method'] ?? null);
    }

    public function confirmForApplication(LoanApplication $application, Customer $customer): void
    {
        abort_unless($this->isComplete($customer), 422, __('borrower.payment_details.incomplete'));

        $application->update([
            'disbursement_details_confirmed_at' => now(),
            'disbursement_details_snapshot'       => array_merge(
                $this->snapshotFromCustomer($customer),
                ['confirmed_at' => now()->toIso8601String()],
            ),
        ]);
    }

    public function clearApplicationConfirmation(LoanApplication $application): void
    {
        if (! $application->disbursement_details_confirmed_at) {
            return;
        }

        $application->update([
            'disbursement_details_confirmed_at' => null,
            'disbursement_details_snapshot'       => null,
        ]);
    }

    public function clearConfirmationForCustomerApplications(Customer $customer): void
    {
        LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('disbursement_details_confirmed_at')
            ->whereNotIn('status', ['disbursed', 'rejected', 'closed'])
            ->each(fn (LoanApplication $application) => $this->clearApplicationConfirmation($application));
    }

    public function methodLabel(?string $method): string
    {
        return match ($method) {
            self::METHOD_MOBILE => __('borrower.payment_details.method_mobile'),
            self::METHOD_BANK   => __('borrower.payment_details.method_bank'),
            default             => '—',
        };
    }

    public function providerLabel(?string $provider): string
    {
        if (! $provider) {
            return '—';
        }

        return self::MOBILE_PROVIDERS[$provider] ?? ucfirst(str_replace('_', ' ', $provider));
    }

    public function shortProviderLabel(?string $provider): string
    {
        return match ($provider) {
            'mpesa'    => 'M-PESA',
            'airtel'   => 'Airtel Money',
            'mixx'     => 'Mixx by Yas',
            'halopesa' => 'HaloPesa',
            default    => $this->providerLabel($provider),
        };
    }

    /** @return array<string, string> */
    public function validationRules(?string $method): array
    {
        $base = [
            'preferred_disbursement_method' => ['required', 'in:'.self::METHOD_MOBILE.','.self::METHOD_BANK],
        ];

        if ($method === self::METHOD_MOBILE) {
            return array_merge($base, [
                'disbursement_mobile_provider'     => ['required', 'in:'.implode(',', array_keys(self::MOBILE_PROVIDERS))],
                'disbursement_mobile_number'       => ['required', 'string', 'regex:/^255\d{9}$/'],
                'disbursement_mobile_account_name' => ['required', 'string', 'max:120'],
            ]);
        }

        if ($method === self::METHOD_BANK) {
            return array_merge($base, [
                'disbursement_bank_name'           => ['required', 'string', 'max:120'],
                'disbursement_bank_account_name'   => ['required', 'string', 'max:120'],
                'disbursement_bank_account_number' => ['required', 'string', 'max:40'],
                'disbursement_bank_branch'         => ['nullable', 'string', 'max:120'],
            ]);
        }

        return $base;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function destinationSummary(array $snapshot): string
    {
        $method = (string) ($snapshot['method'] ?? '');

        if ($method === self::METHOD_MOBILE) {
            return $this->maskPhone((string) ($snapshot['mobile_number'] ?? ''));
        }

        if ($method === self::METHOD_BANK) {
            return (string) ($snapshot['bank_account_number'] ?? '—');
        }

        return '—';
    }

    public function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 6) {
            return $phone ?: '—';
        }

        return substr($digits, 0, 4).str_repeat('X', max(0, strlen($digits) - 7)).substr($digits, -3);
    }

    /** @param  array<string, mixed>  $snapshot */
    public function displayLines(array $snapshot): array
    {
        $method = (string) ($snapshot['method'] ?? '');

        if ($method === self::METHOD_MOBILE) {
            return array_filter([
                __('borrower.payment_details.method') => $this->shortProviderLabel($snapshot['mobile_provider'] ?? null),
                __('borrower.payment_details.provider') => $snapshot['mobile_provider_label'] ?? $this->providerLabel($snapshot['mobile_provider'] ?? null),
                __('borrower.payment_details.phone_number') => $snapshot['mobile_number'] ?? null,
                __('borrower.payment_details.account_name') => $snapshot['mobile_account_name'] ?? null,
            ]);
        }

        if ($method === self::METHOD_BANK) {
            $lines = [
                __('borrower.payment_details.method') => __('borrower.payment_details.method_bank'),
                __('borrower.payment_details.bank_name') => $snapshot['bank_name'] ?? null,
                __('borrower.payment_details.account_number') => $snapshot['bank_account_number'] ?? null,
                __('borrower.payment_details.account_name') => $snapshot['bank_account_name'] ?? null,
            ];

            if (filled($snapshot['bank_branch'] ?? null)) {
                $lines[__('borrower.payment_details.branch')] = $snapshot['bank_branch'];
            }

            return array_filter($lines);
        }

        return [];
    }
}
