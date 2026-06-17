<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ReferralTransaction;
use App\Models\ReferralWallet;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    /** @return array{code_prefix: string, discount_percent: float, commission_percent: float, wallet_max_fee_percent: float} */
    public function settings(): array
    {
        $group = Setting::group('referrals');

        return [
            'code_prefix'            => (string) ($group['code_prefix'] ?? config('referrals.code_prefix', 'KPF')),
            'discount_percent'       => (float) ($group['discount_percent'] ?? config('referrals.discount_percent', 10)),
            'commission_percent'     => (float) ($group['commission_percent'] ?? config('referrals.commission_percent', 10)),
            'wallet_max_fee_percent' => (float) ($group['wallet_max_fee_percent'] ?? config('referrals.wallet_max_fee_percent', 50)),
            'message_share_template' => (string) ($group['message_share_template'] ?? config('referrals.messages.share_template', '')),
            'message_invite_sms'     => (string) ($group['message_invite_sms'] ?? config('referrals.messages.invite_sms', '')),
        ];
    }

    public function shareMessage(Customer $customer): string
    {
        $settings = $this->settings();
        $template = trim($settings['message_share_template'] ?? '')
            ?: trim((string) config('referrals.messages.share_template'))
            ?: 'Join {brand} with my referral code {referral_code}. Register here: {referral_link}';

        $replacements = [
            '{brand}'             => brand_name(),
            '{referrer_name}'     => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            '{referral_code}'     => $this->ensureCode($customer),
            '{referral_link}'     => $this->referralLink($customer),
            '{discount_percent}'  => format_number($settings['discount_percent'], 0),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function ensureCode(Customer $customer): string
    {
        if (filled($customer->referral_code)) {
            return $customer->referral_code;
        }

        $prefix = $this->settings()['code_prefix'];
        $base = strtoupper(Str::slug(substr($customer->first_name ?? 'MEMBER', 0, 6), ''));
        $code = $prefix.'-'.$base.str_pad((string) $customer->id, 3, '0', STR_PAD_LEFT);

        $customer->update(['referral_code' => $code]);

        return $code;
    }

    public function referralLink(Customer $customer): string
    {
        $code = $this->ensureCode($customer);
        $base = rtrim($this->appBaseUrl(), '/');

        return $base.'/register/borrower?ref='.urlencode($code);
    }

    public function appBaseUrl(): string
    {
        return rtrim(Setting::group('company')['app_base_url'] ?? Setting::group('company')['website'] ?? config('app.url'), '/');
    }

    public function wallet(Customer $customer): ReferralWallet
    {
        return ReferralWallet::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0]
        );
    }

    public function referrer(Customer $customer): ?Customer
    {
        if (! $customer->referred_by_customer_id) {
            return null;
        }

        return Customer::query()->find($customer->referred_by_customer_id);
    }

    public function canUseWalletFor(string $feeType): bool
    {
        return in_array($feeType, config('referrals.wallet_allowed_for', []), true);
    }

    /** @return array{allowed: list<string>, blocked: list<string>} */
    public function walletRules(): array
    {
        $allowedLabels = [
            'registration_fee'  => 'Registration fee',
            'application_fee'   => 'Loan application fees',
            'post_approval_fee' => 'Post-approval fees (GPS, insurance, etc.)',
        ];
        $blockedLabels = [
            'loan_repayment' => 'Loan repayments',
            'interest'       => 'Interest charges',
            'penalty'        => 'Penalties and late fees',
        ];

        $allowed = [];
        foreach (config('referrals.wallet_allowed_for', []) as $key) {
            $allowed[] = $allowedLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        }

        $blocked = [];
        foreach (config('referrals.wallet_blocked_for', []) as $key) {
            $blocked[] = $blockedLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        }

        return ['allowed' => $allowed, 'blocked' => $blocked];
    }

    public function credit(Customer $customer, float $amount, string $description, ?string $refType = null, ?int $refId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $wallet = $this->wallet($customer);
        $wallet->increment('balance', $amount);

        ReferralTransaction::create([
            'referral_wallet_id' => $wallet->id,
            'type'               => 'credit',
            'amount'             => $amount,
            'description'        => $description,
            'reference_type'     => $refType,
            'reference_id'       => $refId,
        ]);
    }

    public function debit(Customer $customer, float $amount, string $description, ?string $refType = null, ?int $refId = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $wallet = $this->wallet($customer);
        if ((float) $wallet->fresh()->balance < $amount) {
            throw new \InvalidArgumentException('Insufficient referral wallet balance.');
        }

        $wallet->decrement('balance', $amount);

        ReferralTransaction::create([
            'referral_wallet_id' => $wallet->id,
            'type'               => 'debit',
            'amount'             => $amount,
            'description'        => $description,
            'reference_type'     => $refType,
            'reference_id'       => $refId,
        ]);
    }

    public function maxWalletUsable(float $feeAmount): float
    {
        $pct = $this->settings()['wallet_max_fee_percent'];

        return round($feeAmount * ($pct / 100), 2);
    }

    /**
     * @return array{
     *     base: float,
     *     discount: float,
     *     after_discount: float,
     *     wallet_usable: float,
     *     wallet_applied: float,
     *     cash_due: float,
     *     commission: float,
     *     has_referrer: bool,
     *     referrer: Customer|null
     * }
     */
    public function quoteFee(Customer $payer, float $baseAmount, bool $useWallet, string $feeType, bool $applyDiscount = true): array
    {
        $settings = $this->settings();
        $referrer = $this->referrer($payer);
        $discount = 0.0;

        if ($applyDiscount && $referrer && $baseAmount > 0) {
            $discount = round($baseAmount * ($settings['discount_percent'] / 100), 2);
        }

        $afterDiscount = max(0, round($baseAmount - $discount, 2));
        $promotion = app(PromotionService::class)->applyAfter($feeType, $afterDiscount);
        if ($promotion['promotion_discount'] > 0) {
            $discount += $promotion['promotion_discount'];
            $afterDiscount = $promotion['after_discount'];
        }

        $commission = ($applyDiscount && $referrer && $baseAmount > 0)
            ? round($baseAmount * ($settings['commission_percent'] / 100), 2)
            : 0.0;

        $walletApplied = 0.0;
        if ($useWallet && $this->canUseWalletFor($feeType) && $afterDiscount > 0) {
            $wallet = $this->wallet($payer);
            $maxUsable = $this->maxWalletUsable($afterDiscount);
            $walletApplied = round(min((float) $wallet->balance, $maxUsable, $afterDiscount), 2);
        }

        return [
            'base'           => round($baseAmount, 2),
            'discount'       => $discount,
            'after_discount' => $afterDiscount,
            'wallet_usable'  => $this->maxWalletUsable($afterDiscount),
            'wallet_applied' => $walletApplied,
            'cash_due'       => max(0, round($afterDiscount - $walletApplied, 2)),
            'commission'     => $commission,
            'has_referrer'   => (bool) $referrer,
            'referrer'       => $referrer,
        ];
    }

    /**
     * Apply wallet debit and referrer commission after a fee is confirmed paid.
     *
     * @return array<string, mixed>
     */
    public function settleFee(
        Customer $payer,
        float $baseAmount,
        bool $useWallet,
        string $feeType,
        ?string $refType = null,
        ?int $refId = null,
        bool $applyDiscount = true,
    ): array {
        $quote = $this->quoteFee($payer, $baseAmount, $useWallet, $feeType, $applyDiscount);

        return DB::transaction(function () use ($payer, $quote, $feeType, $refType, $refId): array {
            if ($quote['wallet_applied'] > 0) {
                $this->debit(
                    $payer,
                    $quote['wallet_applied'],
                    'Applied to '.str_replace('_', ' ', $feeType),
                    $refType,
                    $refId
                );
            }

            if ($quote['commission'] > 0 && $quote['referrer']) {
                $this->credit(
                    $quote['referrer'],
                    $quote['commission'],
                    'Commission on '.str_replace('_', ' ', $feeType),
                    $refType,
                    $refId
                );
            }

            return $quote;
        });
    }

    public function attachReferrer(Customer $customer, ?string $referralCode): void
    {
        if (blank($referralCode) || filled($customer->referred_by_customer_id)) {
            return;
        }

        $referrer = Customer::query()->where('referral_code', strtoupper(trim($referralCode)))->first();
        if (! $referrer || $referrer->id === $customer->id) {
            return;
        }

        $customer->update(['referred_by_customer_id' => $referrer->id]);
    }
}
