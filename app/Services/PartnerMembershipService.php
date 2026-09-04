<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Support\Str;

class PartnerMembershipService
{
    /** @return array<string, mixed> */
    public static function config(): array
    {
        $defaults = config('partners.membership', []);
        $stored = Setting::get('partners.membership');

        $merged = array_merge($defaults, is_array($stored) ? $stored : []);
        $storedFees = is_array($stored) && isset($stored['category_fees']) && is_array($stored['category_fees'])
            ? $stored['category_fees']
            : [];
        $merged['category_fees'] = self::mergeCategoryFees($defaults['category_fees'] ?? [], $storedFees);
        $merged['categories_requiring_payment'] = $merged['categories_requiring_payment']
            ?? $merged['categories_requiring_payment']
            ?? [];
        $merged['default_fee_amount'] = $merged['default_fee_amount']
            ?? $merged['default_fee_amount']
            ?? 0;

        return $merged;
    }

    public function requiresPayment(Partner $partner): bool
    {
        $cfg = self::config();
        if (! ($cfg['enabled'] ?? true)) {
            return false;
        }

        $category = (string) ($partner->category ?? '');
        $map = $cfg['categories_requiring_payment'] ?? $cfg['categories_requiring_payment'] ?? [];

        return (bool) ($map[$category] ?? false);
    }

    public static function roleSplitsByApplicant(string $role): bool
    {
        return $role === 'valuer';
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function mergeCategoryFees(array $defaults, array $stored): array
    {
        $out = $defaults;
        foreach ($stored as $role => $entry) {
            $base = $out[$role] ?? null;
            if (is_array($entry)) {
                $out[$role] = array_merge(is_array($base) ? $base : [], $entry);
            } elseif (is_numeric($entry) && is_array($base)) {
                $out[$role] = [
                    'individual' => (float) $entry,
                    'company' => (float) $entry,
                ];
            } else {
                $out[$role] = $entry;
            }
        }

        return $out;
    }

    public function feeFor(Partner $partner): float
    {
        $cfg = self::config();
        $category = (string) ($partner->category ?? '');
        $fees = $cfg['category_fees'] ?? $cfg['category_fees'] ?? [];
        $fallback = (float) ($cfg['default_fee_amount'] ?? $cfg['default_fee_amount'] ?? 0);

        return self::resolveCategoryFee(
            $fees[$category] ?? null,
            $partner->isIndividualApplicant(),
            $fallback,
        );
    }

    public function customerFacingFee(Partner $partner): float
    {
        return app(\App\Services\Staging\StagingPaymentsService::class)
            ->effective('partner_membership', $this->feeFor($partner));
    }

    public static function resolveCategoryFee(mixed $entry, bool $individual, float $fallback): float
    {
        if (is_array($entry)) {
            $key = $individual ? 'individual' : 'company';
            if (isset($entry[$key]) && is_numeric($entry[$key])) {
                return (float) $entry[$key];
            }
            foreach (['company', 'individual'] as $alt) {
                if (isset($entry[$alt]) && is_numeric($entry[$alt])) {
                    return (float) $entry[$alt];
                }
            }

            return $fallback;
        }

        if (is_numeric($entry)) {
            return (float) $entry;
        }

        return $fallback;
    }

    public function isActive(Partner $partner): bool
    {
        if (($partner->membership_status ?? null) === 'active'
            && $partner->membership_expires_at
            && $partner->membership_expires_at->isFuture()) {
            return true;
        }

        if (($partner->membership_status ?? null) === 'grace' && $partner->membership_expires_at) {
            $graceDays = (int) (self::config()['grace_period_days'] ?? 14);

            return now()->lte($partner->membership_expires_at->copy()->addDays($graceDays));
        }

        // Partners that do not require payment stay usable after activation until expiry.
        if (! $this->requiresPayment($partner) && ($partner->status ?? '') === 'active') {
            if (! $partner->membership_expires_at) {
                return true;
            }

            return $partner->membership_expires_at->isFuture();
        }

        return false;
    }

    public function daysRemaining(Partner $partner): int
    {
        if (! $partner->membership_expires_at) {
            return 0;
        }

        $days = now()->startOfDay()->diffInDays(
            $partner->membership_expires_at->copy()->startOfDay(),
            false
        );

        return max(0, (int) $days);
    }

    public function durationDays(): int
    {
        return max(1, (int) (self::config()['default_duration_days'] ?? 365));
    }

    public function activate(Partner $partner, ?string $paymentReference = null): Partner
    {
        $days = (int) (self::config()['default_duration_days'] ?? 365);
        $start = now();

        $partner->update([
            'membership_status' => 'active',
            'membership_started_at' => $start,
            'membership_expires_at' => $start->copy()->addDays($days),
            'membership_payment_due_at' => null,
            'membership_payment_reference' => $paymentReference ?: ('PTR-MEM-'.$partner->id.'-'.Str::upper(Str::random(5))),
        ]);

        return $partner->fresh();
    }

    public function requestRenewal(Partner $partner): Partner
    {
        $partner->update([
            'membership_status' => 'pending_payment',
            'membership_payment_due_at' => now()->addDays((int) (self::config()['grace_period_days'] ?? 14)),
            'membership_payment_reference' => 'PTR-REN-'.$partner->id.'-'.Str::upper(Str::random(5)),
        ]);

        return $partner->fresh();
    }

    public function ensurePaymentReference(Partner $partner): string
    {
        $ref = (string) ($partner->membership_payment_reference ?? '');
        if ($ref !== '') {
            return $ref;
        }

        $ref = 'PTR-MEM-'.$partner->id.'-'.Str::upper(Str::random(5));
        $partner->update([
            'membership_status' => in_array($partner->membership_status, ['active', 'grace'], true)
                ? $partner->membership_status
                : 'pending_payment',
            'membership_payment_reference' => $ref,
        ]);

        return $ref;
    }
}
