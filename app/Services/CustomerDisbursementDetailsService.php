<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDisbursementAccount;
use App\Models\LoanApplication;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    public function accountsForCustomer(Customer $customer): Collection
    {
        return CustomerDisbursementAccount::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    public function hasUsableAccounts(Customer $customer): bool
    {
        return $this->accountsForCustomer($customer)
            ->filter(fn (CustomerDisbursementAccount $account) => $this->accountIsComplete($account))
            ->isNotEmpty();
    }

    public function isComplete(Customer $customer): bool
    {
        return $this->hasUsableAccounts($customer);
    }

    public function accountIsComplete(CustomerDisbursementAccount $account): bool
    {
        if ($account->isMobile()) {
            return filled($account->mobile_provider)
                && filled($account->mobile_number)
                && filled($account->account_name);
        }

        if ($account->isBank()) {
            return filled($account->bank_name)
                && filled($account->account_number)
                && filled($account->account_name);
        }

        return false;
    }

    public function accountNameMatchesBorrower(CustomerDisbursementAccount $account, Customer $customer): bool
    {
        $borrowerName = $this->normalizePersonName(
            $customer->legalDisplayName() ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))
        );
        $accountName = $this->normalizePersonName($account->account_name);

        if ($borrowerName === '' || $accountName === '') {
            return false;
        }

        return $borrowerName === $accountName;
    }

    /** @return array<string, mixed> */
    public function snapshotFromAccount(CustomerDisbursementAccount $account): array
    {
        if ($account->isMobile()) {
            return [
                'method'                => self::METHOD_MOBILE,
                'method_label'          => $this->methodLabel(self::METHOD_MOBILE),
                'account_id'            => $account->id,
                'mobile_provider'       => $account->mobile_provider,
                'mobile_provider_label' => $this->providerLabel($account->mobile_provider),
                'mobile_number'         => $account->mobile_number,
                'mobile_account_name'   => $account->account_name,
                'selected_by_borrower'  => true,
            ];
        }

        return [
            'method'              => self::METHOD_BANK,
            'method_label'        => $this->methodLabel(self::METHOD_BANK),
            'account_id'          => $account->id,
            'bank_name'           => $account->bank_name,
            'bank_account_name'   => $account->account_name,
            'bank_account_number' => $account->account_number,
            'bank_branch'         => $account->bank_branch,
            'selected_by_borrower'=> true,
        ];
    }

    /** @return array<string, mixed> */
    public function snapshotFromCustomer(Customer $customer): array
    {
        $account = $this->accountsForCustomer($customer)->first();

        if ($account && $this->accountIsComplete($account)) {
            return $this->snapshotFromAccount($account);
        }

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

    public function confirmForApplication(
        LoanApplication $application,
        Customer $customer,
        CustomerDisbursementAccount $account,
    ): void {
        abort_unless((int) $account->customer_id === (int) $customer->id, 403);
        abort_unless($this->accountIsComplete($account), 422, __('borrower.payment_details.incomplete'));

        if (! $this->accountNameMatchesBorrower($account, $customer)) {
            throw ValidationException::withMessages([
                'disbursement_account_id' => __('borrower.payment_details.name_mismatch'),
            ]);
        }

        $application->update([
            'disbursement_account_id'           => $account->id,
            'disbursement_details_confirmed_at' => now(),
            'disbursement_details_snapshot'     => array_merge(
                $this->snapshotFromAccount($account),
                ['confirmed_at' => now()->toIso8601String()],
            ),
        ]);

        app(ApplicationDisbursementReadinessService::class)->syncBorrowerProgress($application->fresh());
    }

    public function createAccount(Customer $customer, array $data): CustomerDisbursementAccount
    {
        if (($data['type'] ?? '') === self::METHOD_MOBILE && filled($data['mobile_number'] ?? null)) {
            $data['mobile_number'] = $this->assertValidMobileNumber(
                $this->normalizeMobileNumber((string) $data['mobile_number'], $customer)
            );
        }

        if (! $this->accountNameMatchesBorrower(
            new CustomerDisbursementAccount(['account_name' => $data['account_name'] ?? '']),
            $customer,
        )) {
            throw ValidationException::withMessages([
                'account_name' => __('borrower.payment_details.name_mismatch'),
            ]);
        }

        $type = (string) ($data['type'] ?? '');
        $isFirst = ! CustomerDisbursementAccount::query()
            ->where('customer_id', $customer->id)
            ->exists();

        $account = CustomerDisbursementAccount::create([
            'customer_id'      => $customer->id,
            'type'             => $type,
            'account_name'     => $data['account_name'],
            'mobile_provider'  => $data['mobile_provider'] ?? null,
            'mobile_number'    => $data['mobile_number'] ?? null,
            'bank_name'        => $data['bank_name'] ?? null,
            'account_number'   => $data['account_number'] ?? null,
            'bank_branch'      => $data['bank_branch'] ?? null,
            'is_default'       => $isFirst || (bool) ($data['is_default'] ?? false),
        ]);

        if ($account->is_default) {
            $this->setDefaultAccount($customer, $account);
        }

        $this->syncLegacyCustomerFields($customer->fresh());
        $this->clearUnlatchedConfirmationsForCustomer($customer->fresh());

        return $account;
    }

    public function deleteAccount(Customer $customer, CustomerDisbursementAccount $account): void
    {
        abort_unless((int) $account->customer_id === (int) $customer->id, 403);

        $confirmedInUse = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->where('disbursement_account_id', $account->id)
            ->whereNotNull('disbursement_details_confirmed_at')
            ->whereNotIn('status', ['disbursed', 'rejected', 'closed', 'withdrawn'])
            ->exists();

        if ($confirmedInUse) {
            throw ValidationException::withMessages([
                'account' => __('borrower.disbursement_details.cannot_delete_confirmed'),
            ]);
        }

        $wasDefault = $account->is_default;
        $account->delete();

        if ($wasDefault) {
            $next = $this->accountsForCustomer($customer)->first();
            if ($next) {
                $this->setDefaultAccount($customer, $next);
            }
        }

        $this->syncLegacyCustomerFields($customer->fresh());
        $this->clearUnlatchedConfirmationsForCustomer($customer->fresh());
    }

    public function setDefaultAccount(Customer $customer, CustomerDisbursementAccount $account): void
    {
        abort_unless((int) $account->customer_id === (int) $customer->id, 403);

        CustomerDisbursementAccount::query()
            ->where('customer_id', $customer->id)
            ->where('id', '!=', $account->id)
            ->update(['is_default' => false]);

        $account->update(['is_default' => true]);
        $this->syncLegacyCustomerFields($customer->fresh());
    }

    public function syncLegacyCustomerFields(Customer $customer): void
    {
        $account = $this->accountsForCustomer($customer)->first();

        if (! $account) {
            $customer->update([
                'preferred_disbursement_method'    => null,
                'disbursement_mobile_provider'     => null,
                'disbursement_mobile_number'       => null,
                'disbursement_mobile_account_name' => null,
                'disbursement_bank_name'           => null,
                'disbursement_bank_account_name'   => null,
                'disbursement_bank_account_number' => null,
                'disbursement_bank_branch'         => null,
            ]);

            return;
        }

        if ($account->isMobile()) {
            $customer->update([
                'preferred_disbursement_method'    => self::METHOD_MOBILE,
                'disbursement_mobile_provider'     => $account->mobile_provider,
                'disbursement_mobile_number'       => $account->mobile_number,
                'disbursement_mobile_account_name' => $account->account_name,
                'disbursement_bank_name'           => null,
                'disbursement_bank_account_name'   => null,
                'disbursement_bank_account_number' => null,
                'disbursement_bank_branch'         => null,
            ]);

            return;
        }

        $customer->update([
            'preferred_disbursement_method'    => self::METHOD_BANK,
            'disbursement_mobile_provider'     => null,
            'disbursement_mobile_number'       => null,
            'disbursement_mobile_account_name' => null,
            'disbursement_bank_name'           => $account->bank_name,
            'disbursement_bank_account_name'   => $account->account_name,
            'disbursement_bank_account_number' => $account->account_number,
            'disbursement_bank_branch'         => $account->bank_branch,
        ]);
    }

    public function clearApplicationConfirmation(LoanApplication $application): void
    {
        if (! $application->disbursement_details_confirmed_at) {
            return;
        }

        $application->update([
            'disbursement_account_id'           => null,
            'disbursement_details_confirmed_at' => null,
            'disbursement_details_snapshot'       => null,
        ]);
    }

    public function clearConfirmationForCustomerApplications(Customer $customer): void
    {
        $this->clearUnlatchedConfirmationsForCustomer($customer);
    }

    public function clearUnlatchedConfirmationsForCustomer(Customer $customer): void
    {
        LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('disbursement_details_confirmed_at')
            ->whereNotIn('status', ['disbursed', 'rejected', 'closed', 'withdrawn'])
            ->get()
            ->each(function (LoanApplication $application): void {
                if (in_array('disbursement_account_confirmed', $application->borrower_completed_steps ?? [], true)) {
                    return;
                }

                $this->clearApplicationConfirmation($application);
            });
    }

    public function accountLabel(CustomerDisbursementAccount $account): string
    {
        if ($account->isMobile()) {
            return $this->shortProviderLabel($account->mobile_provider).' · '.$account->mobile_number;
        }

        return ($account->bank_name ?: 'Bank').' · '.$account->account_number;
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
    public function validationRules(?string $type, ?Customer $customer = null): array
    {
        $borrowerNameRule = ['required', 'string', 'max:120'];

        if ($type === self::METHOD_MOBILE) {
            return [
                'type'             => ['required', 'in:'.self::METHOD_MOBILE],
                'mobile_provider'  => ['required', 'in:'.implode(',', array_keys(self::MOBILE_PROVIDERS))],
                'mobile_number'    => ['required', 'string', 'max:20'],
                'account_name'     => $borrowerNameRule,
            ];
        }

        if ($type === self::METHOD_BANK) {
            return [
                'type'           => ['required', 'in:'.self::METHOD_BANK],
                'bank_name'      => ['required', 'string', 'max:120'],
                'account_name'   => $borrowerNameRule,
                'account_number' => ['required', 'string', 'max:40'],
                'bank_branch'    => ['nullable', 'string', 'max:120'],
            ];
        }

        return ['type' => ['required', 'in:'.self::METHOD_MOBILE.','.self::METHOD_BANK]];
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

    public function maskDestinationPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 6) {
            return $phone ?: '—';
        }

        return substr($digits, 0, 2).'•• ••• '.substr($digits, -3);
    }

    public function maskAccountNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? $number;

        if (strlen($digits) < 4) {
            return $number ?: '—';
        }

        return str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4);
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

    protected function normalizePersonName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        return $name;
    }

    public function normalizeMobileNumber(string $phone, ?Customer $customer = null): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        if (strlen($digits) > 12 && str_ends_with($digits, substr($digits, -9))) {
            return '255'.substr($digits, -9);
        }

        return $digits;
    }

    /** @throws \Illuminate\Validation\ValidationException */
    public function assertValidMobileNumber(string $phone): string
    {
        $normalized = $this->normalizeMobileNumber($phone);

        if (! preg_match('/^255\d{9}$/', $normalized)) {
            throw ValidationException::withMessages([
                'mobile_number' => __('borrower.payment_details.invalid_mobile'),
            ]);
        }

        return $normalized;
    }
}
