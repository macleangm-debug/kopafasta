<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\LoanProduct;
use App\Models\LoanProductPaymentAccountOverride;
use App\Models\MobileMoneyAccount;
use App\Models\PaymentAccountMapping;

class PaymentAccountService
{
    /** @return array{bank_account: ?BankAccount, mobile_money_account: ?MobileMoneyAccount, instructions: ?string} */
    public function resolve(string $paymentType, string $paymentMethod, ?LoanProduct $product = null): array
    {
        if ($product) {
            $override = LoanProductPaymentAccountOverride::query()
                ->where('loan_product_id', $product->id)
                ->where('payment_type', $paymentType)
                ->where('payment_method', $paymentMethod)
                ->first();

            if ($override) {
                return [
                    'bank_account'         => $override->bankAccount,
                    'mobile_money_account' => $override->mobileMoneyAccount,
                    'instructions'         => $override->payment_instructions,
                ];
            }
        }

        $mapping = PaymentAccountMapping::query()
            ->where('payment_type', $paymentType)
            ->where('payment_method', $paymentMethod)
            ->where('is_active', true)
            ->first();

        if ($mapping) {
            return [
                'bank_account'         => $mapping->bankAccount,
                'mobile_money_account' => $mapping->mobileMoneyAccount,
                'instructions'         => $mapping->payment_instructions,
            ];
        }

        return [
            'bank_account'         => null,
            'mobile_money_account' => null,
            'instructions'         => null,
        ];
    }

    /** @return array<string, string> */
    public function bankTransferDetails(?BankAccount $account, string $reference): array
    {
        if (! $account) {
            return [
                'bank_name'       => setting('company.name', 'Kopafasta'),
                'account_name'    => setting('company.legal_name', setting('company.name', 'Kopafasta Limited')),
                'account_number'  => '—',
                'reference'       => $reference,
                'instructions'    => 'Contact support for bank transfer details.',
            ];
        }

        return [
            'bank_name'       => $account->bank_name,
            'account_name'    => $account->name,
            'account_number'  => $account->account_number,
            'reference'       => $reference,
            'instructions'    => $account->notes,
        ];
    }

    /** @return array{number: ?string, provider: ?string, instructions: ?string} */
    public function mobileMoneyDetails(?MobileMoneyAccount $account): array
    {
        if (! $account) {
            return [
                'number'       => null,
                'provider'     => null,
                'instructions' => 'Enter your mobile number with country code (e.g. 255712345678).',
            ];
        }

        $number = $account->paybill_number ?: $account->till_number ?: $account->msisdn;

        return [
            'number'       => $number,
            'provider'     => $account->provider,
            'instructions' => "Pay to {$account->name}".($number ? " ({$number})" : ''),
        ];
    }

    public function syncDefaultMappings(array $rows): void
    {
        foreach ($rows as $row) {
            PaymentAccountMapping::updateOrCreate(
                [
                    'payment_type'   => $row['payment_type'],
                    'payment_method' => $row['payment_method'],
                ],
                [
                    'bank_account_id'         => $row['bank_account_id'] ?? null,
                    'mobile_money_account_id' => $row['mobile_money_account_id'] ?? null,
                    'payment_instructions'    => $row['payment_instructions'] ?? null,
                    'is_active'               => (bool) ($row['is_active'] ?? true),
                ],
            );
        }
    }

    public function ensureDefaultMappings(): void
    {
        $types = array_keys(config('payment_types.types', []));
        $methods = array_keys(config('payment_types.methods', []));

        foreach ($types as $type) {
            foreach ($methods as $method) {
                PaymentAccountMapping::firstOrCreate(
                    ['payment_type' => $type, 'payment_method' => $method],
                    ['is_active' => true],
                );
            }
        }
    }
}
