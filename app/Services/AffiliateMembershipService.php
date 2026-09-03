<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Support\Str;

class AffiliateMembershipService
{
    /** @return array{enabled: bool, fee_amount: float, fee_amount_individual: float, fee_amount_company: float, duration_days: int, grace_period_hours: int, required_before_sharing: bool, renewal_window_days: int, require_terms_before_activation: bool, promo_code_on_expiry: string, commission_after_expiry: string} */
    public static function config(): array
    {
        $defaults = config('affiliates.membership', []);
        $stored = Setting::get('affiliates.membership');

        $merged = array_merge($defaults, is_array($stored) ? $stored : []);
        $company = (float) ($merged['fee_amount_company'] ?? $merged['fee_amount'] ?? 50000);
        $individual = (float) ($merged['fee_amount_individual'] ?? 25000);

        return [
            'enabled'                         => (bool) ($merged['enabled'] ?? true),
            'fee_amount'                      => $company,
            'fee_amount_individual'           => $individual,
            'fee_amount_company'              => $company,
            'duration_days'                   => (int) ($merged['duration_days'] ?? 365),
            'grace_period_hours'              => (int) ($merged['grace_period_hours'] ?? 48),
            'required_before_sharing'         => (bool) ($merged['required_before_sharing'] ?? true),
            'renewal_window_days'             => max(1, (int) ($merged['renewal_window_days'] ?? 30)),
            'require_terms_before_activation' => (bool) ($merged['require_terms_before_activation'] ?? true),
            'promo_code_on_expiry'            => (string) ($merged['promo_code_on_expiry'] ?? 'disable'),
            'commission_after_expiry'         => (string) ($merged['commission_after_expiry'] ?? 'historical_only'),
        ];
    }

    public function feeFor(Vendor|Partner $partner): float
    {
        $cfg = self::config();
        if ($partner instanceof Partner && $partner->isIndividualApplicant()) {
            return $cfg['fee_amount_individual'];
        }

        if (($partner->applicant_category ?? 'company') === 'individual') {
            return $cfg['fee_amount_individual'];
        }

        return $cfg['fee_amount_company'];
    }

    public function isEnabled(): bool
    {
        return self::config()['enabled'];
    }

    public function isActive(Vendor|Partner $partner): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (($partner->membership_status ?? null) === 'active'
            && $partner->membership_expires_at
            && $partner->membership_expires_at->isFuture()) {
            return true;
        }

        if (($partner->membership_status ?? null) === 'grace'
            && $partner->membership_expires_at) {
            $graceEnds = $partner->membership_expires_at->copy()
                ->addHours(self::config()['grace_period_hours']);

            return now()->lte($graceEnds);
        }

        return false;
    }

    /**
     * Commercial sharing uses the same eligibility answer as referrals and promo codes.
     */
    public function isSharingAllowed(Vendor|Partner $partner): bool
    {
        return app(AffiliateEligibilityService::class)->canSharePromo($partner);
    }

    public function startPaymentWindow(Vendor|Partner $partner): Vendor|Partner
    {
        $cfg = self::config();
        $partner->update([
            'membership_status'           => 'pending_payment',
            'membership_payment_due_at'   => now()->addHours($cfg['grace_period_hours']),
            'membership_payment_reference'=> $partner->membership_payment_reference
                ?: $this->generatePaymentReference($partner),
        ]);

        return $partner->fresh();
    }

    public function activate(Vendor|Partner $partner, ?string $paymentReference = null): Vendor|Partner
    {
        $cfg = self::config();
        $start = now();
        $partner->update([
            'membership_status'            => 'active',
            'membership_started_at'        => $start,
            'membership_expires_at'        => $start->copy()->addDays($cfg['duration_days']),
            'membership_payment_due_at'    => null,
            'membership_payment_reference' => $paymentReference ?: $partner->membership_payment_reference,
        ]);

        return $partner->fresh();
    }

    public function renew(Vendor|Partner $partner, ?string $paymentReference = null): Vendor|Partner
    {
        $cfg = self::config();
        $base = ($partner->membership_expires_at && $partner->membership_expires_at->isFuture())
            ? $partner->membership_expires_at->copy()
            : now();

        $partner->update([
            'membership_status'            => 'active',
            'membership_started_at'        => $partner->membership_started_at ?? now(),
            'membership_expires_at'        => $base->addDays($cfg['duration_days']),
            'membership_payment_due_at'    => null,
            'membership_payment_reference' => $paymentReference ?: $this->generatePaymentReference($partner),
        ]);

        return $partner->fresh();
    }

    public function markGrace(Vendor|Partner $partner): void
    {
        if (($partner->membership_status ?? null) === 'grace') {
            return;
        }

        $partner->update(['membership_status' => 'grace']);
    }

    public function markExpired(Vendor|Partner $partner): void
    {
        $partner->update(['membership_status' => 'expired']);
    }

    /**
     * Admin approves a bank transfer submitted while membership_status = pending_payment.
     * Activates (first time) or renews (already had a membership before).
     */
    public function approvePendingPayment(Vendor|Partner $partner, ?string $paymentReference = null): Vendor|Partner
    {
        $ref = $paymentReference ?: $partner->membership_payment_reference;

        return $partner->membership_started_at
            ? $this->renew($partner, $ref)
            : $this->activate($partner, $ref);
    }

    /**
     * Admin rejects a bank transfer submitted while membership_status = pending_payment.
     */
    public function rejectPendingPayment(Vendor|Partner $partner): Vendor|Partner
    {
        $partner->update([
            'membership_status'         => $partner->membership_started_at ? 'expired' : null,
            'membership_payment_due_at' => null,
        ]);

        return $partner->fresh();
    }

    public function generatePaymentReference(Vendor|Partner $partner): string
    {
        return 'AFF-MEM-'.$partner->id.'-'.Str::upper(Str::random(6));
    }

    public function ensurePaymentReference(Vendor|Partner $partner): string
    {
        $ref = (string) ($partner->membership_payment_reference ?? '');
        if ($ref !== '') {
            return $ref;
        }

        $ref = $this->generatePaymentReference($partner);
        $partner->update([
            'membership_status' => in_array($partner->membership_status, ['active', 'grace'], true)
                ? $partner->membership_status
                : 'pending_payment',
            'membership_payment_reference' => $ref,
        ]);

        return $ref;
    }

    public function withinRenewalWindow(Vendor|Partner $partner): bool
    {
        if (! $partner->membership_expires_at) {
            return true;
        }

        $days = self::config()['renewal_window_days'];

        return $partner->membership_expires_at->lte(now()->addDays($days));
    }

    /** @return array{status: string, label: string, fee: float, expires_at: mixed, due_at: mixed, active: bool} */
    public function summary(Vendor|Partner $partner): array
    {
        $cfg = self::config();
        $status = (string) ($partner->membership_status ?? 'inactive');
        if (! $cfg['enabled']) {
            $status = 'disabled';
        }

        $labels = [
            'active'           => __('site.affiliate_portal.membership_active'),
            'pending_payment'  => __('site.affiliate_portal.membership_pending'),
            'grace'            => __('site.affiliate_portal.membership_grace'),
            'expired'          => __('site.affiliate_portal.membership_expired'),
            'inactive'         => __('site.affiliate_portal.membership_inactive'),
            'disabled'         => __('site.affiliate_portal.membership_inactive'),
        ];

        return [
            'status'     => $status,
            'label'      => $labels[$status] ?? $status,
            'fee'        => $this->feeFor($partner),
            'expires_at' => $partner->membership_expires_at,
            'due_at'     => $partner->membership_payment_due_at,
            'active'     => $this->isActive($partner),
            'enabled'    => $cfg['enabled'],
            'reference'  => $partner->membership_payment_reference,
        ];
    }
}
