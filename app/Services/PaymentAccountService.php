<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\LoanProduct;
use App\Models\LoanProductPaymentAccountOverride;
use App\Models\MobileMoneyAccount;
use App\Models\PaymentAccountMapping;
use App\Models\Setting;

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
                $mobileAccount = $override->mobileMoneyAccount
                    ?? ($paymentMethod === 'mobile_money' ? $this->defaultCollectionAccount() : null);
                $bankAccount = $paymentMethod === 'bank_transfer'
                    ? ($this->defaultCollectionBankAccount() ?? $override->bankAccount ?? $this->fallbackBankAccount())
                    : $override->bankAccount;

                return [
                    'bank_account'         => $bankAccount,
                    'mobile_money_account' => $mobileAccount,
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
            $account = $mapping->mobileMoneyAccount ?? $this->defaultCollectionAccount();
            $bankAccount = $paymentMethod === 'bank_transfer'
                ? ($this->defaultCollectionBankAccount() ?? $mapping->bankAccount ?? $this->fallbackBankAccount())
                : $mapping->bankAccount;

            return [
                'bank_account'         => $bankAccount,
                'mobile_money_account' => $paymentMethod === 'mobile_money' ? $account : $mapping->mobileMoneyAccount,
                'instructions'         => $mapping->payment_instructions,
            ];
        }

        $defaultMobile = $paymentMethod === 'mobile_money' ? $this->defaultCollectionAccount() : null;
        $defaultBank = $paymentMethod === 'bank_transfer'
            ? ($this->defaultCollectionBankAccount() ?? $this->fallbackBankAccount())
            : null;

        return [
            'bank_account'         => $defaultBank,
            'mobile_money_account' => $defaultMobile,
            'instructions'         => null,
        ];
    }

    public function defaultCollectionAccount(): ?MobileMoneyAccount
    {
        $id = (int) (Setting::get('payments.default_collection_mobile_money_account_id') ?? 0);
        if ($id <= 0) {
            return null;
        }

        return MobileMoneyAccount::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->first();
    }

    /** Single inbound bank account for all bank_transfer payments (e.g. TCB). */
    public function defaultCollectionBankAccount(): ?BankAccount
    {
        $id = (int) (Setting::get('payments.default_collection_bank_account_id') ?? 0);
        if ($id > 0) {
            $account = BankAccount::query()
                ->whereKey($id)
                ->where('is_active', true)
                ->first();
            if ($account) {
                return $account;
            }
        }

        // Prefer an active TCB collection account if the setting is not yet saved.
        return BankAccount::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('bank_name', 'like', '%TCB%')
                    ->orWhere('name', 'like', '%TCB%');
            })
            ->orderByRaw("CASE WHEN purpose = 'collection' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    public function applyDefaultCollectionToAllMappings(int $accountId): int
    {
        return PaymentAccountMapping::query()
            ->where('payment_method', 'mobile_money')
            ->update(['mobile_money_account_id' => $accountId]);
    }

    public function applyDefaultCollectionBankToAllMappings(int $accountId): int
    {
        return PaymentAccountMapping::query()
            ->where('payment_method', 'bank_transfer')
            ->update(['bank_account_id' => $accountId]);
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

    /**
     * Prefer the configured collection bank (TCB); otherwise any active mapped/default bank.
     * Keeps Mobile Money | Bank Transfer available on every PSP gate.
     */
    public function resolveBankAccount(string $paymentType, ?LoanProduct $product = null): ?BankAccount
    {
        return $this->defaultCollectionBankAccount()
            ?? $this->resolve($paymentType, 'bank_transfer', $product)['bank_account']
            ?? $this->fallbackBankAccount();
    }

    public function fallbackBankAccount(): ?BankAccount
    {
        if ($default = $this->defaultCollectionBankAccount()) {
            return $default;
        }

        $mapped = PaymentAccountMapping::query()
            ->where('payment_method', 'bank_transfer')
            ->where('is_active', true)
            ->whereNotNull('bank_account_id')
            ->with('bankAccount')
            ->get()
            ->pluck('bankAccount')
            ->first(fn ($account) => $account && $account->is_active);

        if ($mapped instanceof BankAccount) {
            return $mapped;
        }

        return BankAccount::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Bank transfer details for borrower-facing screens (membership, application fee, etc.).
     *
     * @return list<array{bank: string, account_name: string, account_number: string, branch: ?string, reference: string, instructions: ?string}>
     */
    public function bankAccountsForDisplay(string $paymentType, string $reference, ?LoanProduct $product = null): array
    {
        $resolved = $this->resolve($paymentType, 'bank_transfer', $product);
        $account = $this->resolveBankAccount($paymentType, $product);

        if ($account) {
            $details = $this->bankTransferDetails($account, $reference);

            return [[
                'bank'           => $details['bank_name'],
                'account_name'   => $details['account_name'],
                'account_number' => $details['account_number'],
                'branch'         => $account->branch,
                'reference'      => $details['reference'],
                'instructions'   => $resolved['instructions'] ?? $details['instructions'],
            ]];
        }

        $details = $this->bankTransferDetails(null, $reference);

        return [[
            'bank'           => $details['bank_name'],
            'account_name'   => $details['account_name'],
            'account_number' => $details['account_number'],
            'branch'         => null,
            'reference'      => $details['reference'],
            'instructions'   => $resolved['instructions'] ?? $details['instructions'],
        ]];
    }

    /** @return array{number: ?string, provider: ?string, label: ?string, account_type: ?string, instructions: ?string} */
    public function mobileMoneyDetails(?MobileMoneyAccount $account, ?string $reference = null): array
    {
        if (! $account) {
            return [
                'number'       => null,
                'provider'     => null,
                'label'        => null,
                'account_type' => null,
                'instructions' => 'Enter your mobile number with country code (e.g. 255712345678).',
            ];
        }

        $number = $account->paybill_number ?: $account->till_number ?: $account->msisdn;
        $accountType = $account->paybill_number ? 'paybill' : ($account->till_number ? 'till' : 'msisdn');
        $instructions = "Pay to {$account->name}".($number ? " ({$number})" : '');

        if ($reference) {
            $instructions .= $accountType === 'paybill'
                ? " Enter {$reference} as the account number."
                : " Use reference {$reference}.";
        }

        return [
            'number'       => $number,
            'provider'     => $account->provider,
            'label'        => $account->name,
            'account_type' => $accountType,
            'instructions' => $instructions,
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
