<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Support\Str;

class AffiliateMembershipService
{
    /** @return array{enabled: bool, fee_amount: float, duration_days: int, grace_period_hours: int, required_before_sharing: bool} */
    public static function config(): array
    {
        $defaults = config('affiliates.membership', []);
        $stored = Setting::get('affiliates.membership');

        $merged = array_merge($defaults, is_array($stored) ? $stored : []);

        return [
            'enabled'                 => (bool) ($merged['enabled'] ?? true),
            'fee_amount'              => (float) ($merged['fee_amount'] ?? 50000),
            'duration_days'           => (int) ($merged['duration_days'] ?? 365),
            'grace_period_hours'      => (int) ($merged['grace_period_hours'] ?? 48),
            'required_before_sharing' => (bool) ($merged['required_before_sharing'] ?? true),
        ];
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

    public function isSharingAllowed(Vendor|Partner $partner): bool
    {
        $cfg = self::config();
        if (! $cfg['enabled'] || ! $cfg['required_before_sharing']) {
            return true;
        }

        return $this->isActive($partner);
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
            'fee'        => $cfg['fee_amount'],
            'expires_at' => $partner->membership_expires_at,
            'due_at'     => $partner->membership_payment_due_at,
            'active'     => $this->isActive($partner),
            'enabled'    => $cfg['enabled'],
            'reference'  => $partner->membership_payment_reference,
        ];
    }
}
