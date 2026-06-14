<?php

namespace App\Services;

use App\Models\BorrowerRefund;
use App\Models\MobileMoneyAccount;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileMoneyDisbursementService
{
    public function usesDummyGateway(): bool
    {
        return payment_gateway_is_dummy();
    }

    public function defaultDisbursementAccount(): ?MobileMoneyAccount
    {
        $id = (int) (Setting::get('payments.default_disbursement_mobile_money_account_id') ?? 0);
        if ($id > 0) {
            $account = MobileMoneyAccount::query()
                ->whereKey($id)
                ->where('is_active', true)
                ->first();

            if ($account) {
                return $account;
            }
        }

        return MobileMoneyAccount::query()
            ->where('is_active', true)
            ->whereIn('purpose', ['disbursement', 'both'])
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{success: bool, reference: string, account: ?MobileMoneyAccount, error: ?string}
     */
    public function send(BorrowerRefund $refund, ?MobileMoneyAccount $account = null): array
    {
        if ($refund->payout_channel !== 'mobile_money') {
            throw ValidationException::withMessages([
                'disbursement' => 'Auto-disburse is only available for mobile money refunds.',
            ]);
        }

        $phone = trim((string) $refund->payout_phone);
        if ($phone === '') {
            return [
                'success'   => false,
                'reference' => '',
                'account'   => null,
                'error'     => 'Borrower payout phone number is missing.',
            ];
        }

        $amount = (float) $refund->amount;
        if ($amount <= 0) {
            return [
                'success'   => false,
                'reference' => '',
                'account'   => null,
                'error'     => 'Refund amount must be greater than zero.',
            ];
        }

        $account ??= $this->defaultDisbursementAccount();

        if ($this->usesDummyGateway()) {
            return [
                'success'   => true,
                'reference' => 'MMD-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
                'account'   => $account,
                'error'     => null,
            ];
        }

        if (! $account) {
            return [
                'success'   => false,
                'reference' => '',
                'account'   => null,
                'error'     => 'Configure a disbursement mobile money account under Payment Accounts, or mark paid manually.',
            ];
        }

        if (! filled($account->api_username) || ! filled($account->api_secret)) {
            return [
                'success'   => false,
                'reference' => '',
                'account'   => $account,
                'error'     => 'Live disbursement requires API credentials on the mobile money account. Enter a manual payment reference instead.',
            ];
        }

        return $this->dispatchLive($account, $phone, $amount, $refund->reference);
    }

    /**
     * @return array{success: bool, reference: string, account: MobileMoneyAccount, error: ?string}
     */
    protected function dispatchLive(MobileMoneyAccount $account, string $phone, float $amount, string $refundReference): array
    {
        logger()->info('Mobile money disbursement queued (live API not yet integrated)', [
            'account_id' => $account->id,
            'provider'   => $account->provider,
            'phone'      => $phone,
            'amount'     => $amount,
            'reference'  => $refundReference,
        ]);

        return [
            'success'   => false,
            'reference' => '',
            'account'   => $account,
            'error'     => 'Live mobile money disbursement API is not connected yet. Pay manually and enter the provider reference.',
        ];
    }
}
