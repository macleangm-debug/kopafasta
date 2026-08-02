<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateService
{
    public function findByCode(?string $code): ?Vendor
    {
        if (blank($code)) {
            return null;
        }

        $affiliate = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', 'active')
            ->where('affiliate_code', strtoupper(trim($code)))
            ->first();

        if (! $affiliate || ! app(AffiliateLifecycleService::class)->canReceiveReferrals($affiliate)) {
            return null;
        }

        if (app(AffiliateFraudDetectionService::class)->referralsBlocked($affiliate)) {
            return null;
        }

        // When KYC is required for public verification, unsigned codes must not attribute referrals.
        if (app(AffiliateSettingsService::class)->requireKycForVerification()
            && ! app(AffiliateLifecycleService::class)->canSharePublicly($affiliate)) {
            return null;
        }

        return $affiliate;
    }

    public function affiliateLink(Vendor $affiliate): string
    {
        $code = $this->ensureCode($affiliate);
        $base = rtrim(app(ReferralService::class)->appBaseUrl(), '/');

        return $base.'/aff/'.$code;
    }

    public function registrationLink(Vendor $affiliate): string
    {
        $code = $this->ensureCode($affiliate);

        return rtrim(app(ReferralService::class)->appBaseUrl(), '/').'/register/borrower?aff='.urlencode($code);
    }

    public function ensureCode(Vendor $affiliate): string
    {
        if (filled($affiliate->affiliate_code)) {
            return $affiliate->affiliate_code;
        }

        $fromName = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $affiliate->name) ?? '');
        if (strlen($fromName) >= 3 && $this->codeIsUnique($fromName)) {
            $affiliate->update(['affiliate_code' => $fromName]);

            return $fromName;
        }

        $prefix = config('affiliates.code_prefix', 'KPA');
        do {
            $code = $prefix.'-'.strtoupper(Str::random(6));
        } while (! $this->codeIsUnique($code));

        $affiliate->update(['affiliate_code' => $code]);

        return $code;
    }

    public function trackClick(Vendor $affiliate, Request $request): void
    {
        $attribution = app(AffiliateAttributionService::class)->mergeIntoSession($request);

        AffiliateEvent::create(array_merge([
            'vendor_id'  => $affiliate->id,
            'event_type' => 'click',
        ], app(AffiliateAttributionService::class)->attributesForEvent($attribution)));
    }

    public function attachAffiliate(Customer $customer, ?string $code): void
    {
        if (blank($code) || filled($customer->affiliate_vendor_id)) {
            return;
        }

        $affiliate = $this->findByCode($code);
        if (! $affiliate) {
            return;
        }

        $attribution = app(AffiliateAttributionService::class)->attributesForEvent();

        $customer->update(['affiliate_vendor_id' => $affiliate->id]);

        AffiliateEvent::create(array_merge([
            'vendor_id'   => $affiliate->id,
            'event_type'  => 'registration',
            'customer_id' => $customer->id,
        ], $attribution));

        app(AffiliateAttributionService::class)->clearSession();

        app(AffiliateFraudDetectionService::class)->scanAndPersist($affiliate);
    }

    public function trackApplication(LoanApplication $application): void
    {
        $customer = $application->customer;
        if (! $customer?->affiliate_vendor_id) {
            return;
        }

        AffiliateEvent::create([
            'vendor_id'           => $customer->affiliate_vendor_id,
            'event_type'          => 'application',
            'customer_id'         => $customer->id,
            'loan_application_id' => $application->id,
        ]);
    }

    public function registrationDiscountPercent(Vendor $affiliate): float
    {
        return (float) ($affiliate->registration_discount_percent
            ?? app(AffiliateSettingsService::class)->forForm()['default_registration_discount_percent']
            ?? config('affiliates.default_registration_discount_percent', 10));
    }

    public function applicationDiscountPercent(Vendor $affiliate): float
    {
        return (float) ($affiliate->application_discount_percent
            ?? app(AffiliateSettingsService::class)->forForm()['default_application_discount_percent']
            ?? config('affiliates.default_application_discount_percent', 10));
    }

    public function stats(Vendor $affiliate): array
    {
        $events = AffiliateEvent::query()->where('partner_id', $affiliate->id);

        return [
            'clicks'        => (clone $events)->where('event_type', 'click')->count(),
            'registrations' => (clone $events)->where('event_type', 'registration')->count(),
            'applications'  => (clone $events)->where('event_type', 'application')->count(),
            'commissions'   => (float) (clone $events)->where('event_type', 'like', 'commission_%')->sum('commission_amount'),
        ];
    }

    /** @return array<string, string> */
    public function messageContext(Vendor $affiliate): array
    {
        $code = $this->ensureCode($affiliate);

        return [
            'brand'              => brand_name(),
            'affiliate_name'     => $affiliate->name,
            'affiliate_code'     => $code,
            'affiliate_link'     => $this->affiliateLink($affiliate),
            'registration_link'  => $this->registrationLink($affiliate),
            'verify_link'        => route('site.affiliate.verify', $code),
        ];
    }

    public function renderMessage(Vendor $affiliate, string $key): string
    {
        return app(AffiliateSettingsService::class)->message($key, $this->messageContext($affiliate));
    }

    public function affiliate(Customer $customer): ?Vendor
    {
        if (! $customer->affiliate_vendor_id) {
            return null;
        }

        return Vendor::query()->find($customer->affiliate_vendor_id);
    }

    public function attributionBreakdown(Vendor $affiliate): array
    {
        $base = AffiliateEvent::query()->where('partner_id', $affiliate->id);

        $bySource = (clone $base)
            ->whereNotNull('utm_source')
            ->selectRaw('utm_source, count(*) as total')
            ->groupBy('utm_source')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'utm_source')
            ->all();

        $byDevice = (clone $base)
            ->whereNotNull('device_type')
            ->selectRaw('device_type, count(*) as total')
            ->groupBy('device_type')
            ->pluck('total', 'device_type')
            ->all();

        $byCampaign = (clone $base)
            ->whereNotNull('utm_campaign')
            ->selectRaw('utm_campaign, count(*) as total')
            ->groupBy('utm_campaign')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'utm_campaign')
            ->all();

        return [
            'utm_sources'  => $bySource,
            'devices'      => $byDevice,
            'utm_campaigns'=> $byCampaign,
        ];
    }

    public function recentEvents(Vendor $affiliate, int $limit = 20): \Illuminate\Support\Collection
    {
        return AffiliateEvent::query()
            ->where('partner_id', $affiliate->id)
            ->with('customer')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function commissionPercent(Vendor $affiliate): float
    {
        return app(AffiliateCommissionCalculatorService::class)->percentFor($affiliate);
    }

    public function codeIsUnique(string $code, ?int $exceptVendorId = null): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        return ! Vendor::query()
            ->where('category', 'affiliate')
            ->where('affiliate_code', $code)
            ->when($exceptVendorId, fn ($q) => $q->where('id', '!=', $exceptVendorId))
            ->exists();
    }

    public function updateCode(Vendor $affiliate, string $code): string
    {
        abort_unless($affiliate->isAffiliate(), 403);

        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', trim($code)) ?? '');
        if (strlen($code) < 3) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_too_short'));
        }

        $current = strtoupper((string) ($affiliate->affiliate_code ?? ''));
        if ($code === $current) {
            return $code;
        }

        $meta = $affiliate->metadata ?? [];
        if (! empty($meta['affiliate_code_changed_at'])) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_change_once'));
        }

        if (! $this->codeIsUnique($code, $affiliate->id)) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_taken'));
        }

        $meta['affiliate_code_changed_at'] = now()->toIso8601String();
        $affiliate->update([
            'affiliate_code' => $code,
            'metadata' => $meta,
        ]);

        return $code;
    }

    public function canChangeCode(Vendor $affiliate): bool
    {
        $meta = $affiliate->metadata ?? [];

        return empty($meta['affiliate_code_changed_at']);
    }

    /**
     * Affiliate discount quote (referral takes precedence elsewhere).
     *
     * @return array{base: float, discount: float, after_discount: float, commission: float, affiliate: Vendor|null, has_affiliate: bool}
     */
    public function quoteFee(Customer $customer, float $baseAmount, string $feeType): array
    {
        $affiliate = $this->affiliate($customer);

        if (! $affiliate || $baseAmount <= 0 || ! app(AffiliateSettingsService::class)->appliesToFeeType($feeType)) {
            return [
                'base'           => round($baseAmount, 2),
                'discount'       => 0.0,
                'after_discount' => round($baseAmount, 2),
                'commission'     => 0.0,
                'affiliate'      => null,
                'has_affiliate'  => false,
            ];
        }

        $discountPct = match ($feeType) {
            'registration_fee' => $this->registrationDiscountPercent($affiliate),
            'application_fee', 'post_approval_fee' => $this->applicationDiscountPercent($affiliate),
            default => 0.0,
        };

        $discount = round($baseAmount * ($discountPct / 100), 2);
        $afterDiscount = max(0, round($baseAmount - $discount, 2));
        $promotion = app(PromotionService::class)->applyAfter($feeType, $afterDiscount);
        if ($promotion['promotion_discount'] > 0) {
            $discount += $promotion['promotion_discount'];
            $afterDiscount = $promotion['after_discount'];
        }

        $commissionBase = app(AffiliateSettingsService::class)->commissionCalculationBase() === 'discounted_amount'
            ? $afterDiscount
            : $baseAmount;
        $commission = app(AffiliateCommissionCalculatorService::class)->calculate($affiliate, $commissionBase, $feeType);

        return [
            'base'           => round($baseAmount, 2),
            'discount'       => $discount,
            'after_discount' => $afterDiscount,
            'commission'     => $commission,
            'affiliate'      => $affiliate,
            'has_affiliate'  => true,
        ];
    }

    public function accrueCommission(
        Customer $customer,
        float $baseAmount,
        string $feeType,
        ?string $refType = null,
        ?int $refId = null,
    ): ?AffiliateEvent {
        if (app(ReferralService::class)->referrer($customer)) {
            return null;
        }

        $quote = $this->quoteFee($customer, $baseAmount, $feeType);
        if (! $quote['affiliate'] || $quote['commission'] <= 0) {
            return null;
        }

        return DB::transaction(function () use ($quote, $customer, $feeType, $refType, $refId): AffiliateEvent {
            $event = AffiliateEvent::create([
                'vendor_id'           => $quote['affiliate']->id,
                'event_type'          => 'commission_'.$feeType,
                'customer_id'         => $customer->id,
                'commission_amount'   => $quote['commission'],
            ]);

            app(PartnerSettlementService::class)->accrue(
                $quote['affiliate'],
                (int) round($quote['commission']),
                'affiliate_commission',
                $event->id,
                'Affiliate commission on '.str_replace('_', ' ', $feeType),
            );

            return $event;
        });
    }
}
