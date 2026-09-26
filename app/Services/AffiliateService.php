<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\PartnerPayment;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateService
{
    public function findByCode(?string $code, bool $requireEligible = true): ?Vendor
    {
        $affiliate = $this->resolveByPublicCode($code);
        if (! $affiliate) {
            return null;
        }

        if ($requireEligible && ! app(AffiliateEligibilityService::class)->canAttributeNewReferral($affiliate)) {
            return null;
        }

        return $affiliate;
    }

    public function resolveByPublicCode(?string $code): ?Vendor
    {
        if (blank($code)) {
            return null;
        }

        $code = strtoupper(trim($code));

        $direct = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', 'active')
            ->where('affiliate_code', $code)
            ->first();

        if ($direct) {
            return $this->canonicalizeIdentity($direct);
        }

        $byToken = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', 'active')
            ->where(function ($query) use ($code) {
                $query->where('metadata->referral_token', $code)
                    ->orWhere('metadata->legacy_referral_token', $code);
            })
            ->first();

        if ($byToken) {
            return $this->canonicalizeIdentity($byToken);
        }

        $byPartnerNumber = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', 'active')
            ->where(function ($query) use ($code) {
                $query->where('partner_number', $code)
                    ->orWhere('metadata->legacy_partner_number', $code)
                    ->orWhere('metadata->referral_token', $code);
            })
            ->first();

        if ($byPartnerNumber) {
            return $this->canonicalizeIdentity($byPartnerNumber);
        }

        $byLegacy = $this->resolveByLegacyIdentity($code);
        if ($byLegacy) {
            return $this->canonicalizeIdentity($byLegacy);
        }

        $alias = $this->resolveByAlias($code);

        return $alias ? $this->canonicalizeIdentity($alias) : null;
    }

    public function canonicalizeIdentity(Vendor|\App\Models\Partner $affiliate): Vendor|\App\Models\Partner
    {
        $affiliate = $affiliate->fresh() ?? $affiliate;
        app(PartnerCodeService::class)->ensure($affiliate);
        $this->ensureReferralToken($affiliate);

        return $affiliate->fresh() ?? $affiliate;
    }

    public function affiliateLink(Vendor $affiliate): string
    {
        $code = $this->ensureReferralToken($affiliate);
        $base = rtrim(app(ReferralService::class)->appBaseUrl(), '/');

        return $base.'/aff/'.$code;
    }

    public function registrationLink(Vendor $affiliate): string
    {
        $code = $this->ensureReferralToken($affiliate);

        return rtrim(app(ReferralService::class)->appBaseUrl(), '/').'/register/borrower?aff='.urlencode($code);
    }

    /**
     * Stable referral URL token. Promo codes may change; this token must not.
     */
    public function ensureReferralToken(Vendor|\App\Models\Partner $affiliate): string
    {
        $affiliate->refresh();
        $meta = is_array($affiliate->metadata ?? null) ? $affiliate->metadata : [];
        $token = strtoupper(trim((string) ($meta['referral_token'] ?? '')));
        $promo = strtoupper(trim((string) ($affiliate->affiliate_code ?? '')));
        $partnerNumber = strtoupper(trim((string) ($affiliate->partner_number ?? '')));

        $coupled = $token !== '' && (
            $token === $promo
            || $token === $partnerNumber
            || $this->looksLikePersonOrCompanyName($affiliate, $token)
        );

        if ($token !== '' && ! $coupled) {
            return $token;
        }

        $fresh = $this->mintReferralToken($affiliate->id);
        if ($token !== '') {
            $legacy = is_array($meta['legacy_referral_tokens'] ?? null) ? $meta['legacy_referral_tokens'] : [];
            $legacy[] = $token;
            $meta['legacy_referral_tokens'] = array_values(array_unique(array_filter($legacy)));
            $meta['legacy_referral_token'] = $token;
        }
        $meta['referral_token'] = $fresh;
        $affiliate->update(['metadata' => $meta]);

        return $fresh;
    }

    private function mintReferralToken(?int $exceptVendorId = null): string
    {
        do {
            $token = strtoupper(Str::random(8));
        } while (! $this->codeIsUnique($token, $exceptVendorId));

        return $token;
    }

    private function resolveByLegacyIdentity(string $code): ?Vendor
    {
        $candidates = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', 'active')
            ->where(function ($query) use ($code) {
                $query->where('metadata->legacy_partner_number', $code)
                    ->orWhere('metadata->legacy_referral_token', $code)
                    ->orWhere('metadata', 'like', '%"'.$code.'"%');
            })
            ->limit(20)
            ->get();

        foreach ($candidates as $affiliate) {
            $meta = is_array($affiliate->metadata ?? null) ? $affiliate->metadata : [];
            $legacyNumbers = is_array($meta['legacy_partner_numbers'] ?? null) ? $meta['legacy_partner_numbers'] : [];
            $legacyTokens = is_array($meta['legacy_referral_tokens'] ?? null) ? $meta['legacy_referral_tokens'] : [];
            if (strtoupper((string) ($meta['legacy_partner_number'] ?? '')) === $code
                || strtoupper((string) ($meta['legacy_referral_token'] ?? '')) === $code
                || in_array($code, array_map('strtoupper', $legacyNumbers), true)
                || in_array($code, array_map('strtoupper', $legacyTokens), true)) {
                return $affiliate;
            }
        }

        return null;
    }

    public function ensureCode(Vendor $affiliate): string
    {
        if (filled($affiliate->affiliate_code)) {
            $this->ensureReferralToken($affiliate);

            return (string) $affiliate->affiliate_code;
        }

        $code = $this->mintPublicCode($affiliate->id);
        $affiliate->update(['affiliate_code' => $code]);
        $this->ensureReferralToken($affiliate->fresh() ?? $affiliate);

        return $code;
    }

    private function mintPublicCode(?int $exceptVendorId = null): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) config('affiliates.code_prefix', 'KPA'))) ?: 'KPA';
        do {
            $code = $prefix.strtoupper(Str::random(4));
        } while (! $this->codeIsUnique($code, $exceptVendorId));

        return $code;
    }

    private function looksLikePersonOrCompanyName(Vendor|\App\Models\Partner $affiliate, string $code): bool
    {
        $name = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $affiliate->name) ?? '');

        return $name !== '' && strlen($name) >= 3 && $code === $name;
    }

    public function trackClick(Vendor $affiliate, Request $request): void
    {
        $attribution = app(AffiliateAttributionService::class)->mergeIntoSession($request);

        AffiliateEvent::create(array_merge([
            'vendor_id'  => $affiliate->id,
            'event_type' => 'click',
        ], app(AffiliateAttributionService::class)->attributesForEvent($attribution)));
    }

    public function attachAffiliate(Customer $customer, ?string $code, ?Request $request = null): void
    {
        $request = $request ?: request();
        $settings = app(AffiliateSettingsService::class);
        $attribution = app(AffiliateAttributionService::class);

        if (filled($customer->affiliate_vendor_id) && $attribution->isLocked($customer) && ! $settings->allowOverrideAfterLock()) {
            return;
        }

        if (filled($customer->affiliate_vendor_id)
            && $settings->attributionModel() === 'first_valid'
            && ! $settings->allowReplacementBeforeLock()
            && ! $attribution->isLocked($customer)) {
            return;
        }

        $affiliate = null;
        $claim = null;

        if (filled($code)) {
            $affiliate = $this->resolveByPublicCode($code);
            if (! $affiliate) {
                return;
            }

            $claim = [
                'affiliate_id' => (int) $affiliate->id,
                'code_used' => strtoupper(trim((string) $code)),
                'source' => 'promo',
                'attributed_at' => now()->toIso8601String(),
                'policy_version' => $settings->policyVersion(),
            ];
        } else {
            $pending = $attribution->pendingClaim($request);
            $pendingAffiliate = $attribution->pendingAffiliate($request);
            if ($pending && $pendingAffiliate && app(AffiliateEligibilityService::class)->canAttributeNewReferral($pendingAffiliate)) {
                $affiliate = $pendingAffiliate;
                $claim = $pending;
            }
        }

        if (! $affiliate || ! $claim) {
            return;
        }

        $lockAtRegistration = $settings->attributionLockAt() === 'registration';
        $attached = $attribution->persistOnCustomer($customer, $affiliate, $claim, lock: $lockAtRegistration);
        if (! $attached) {
            return;
        }

        $customer->refresh();

        if (! AffiliateEvent::query()
            ->where('partner_id', $affiliate->id)
            ->where('customer_id', $customer->id)
            ->where('event_type', 'registration')
            ->exists()) {
            AffiliateEvent::create(array_merge([
                'vendor_id'   => $affiliate->id,
                'event_type'  => 'registration',
                'customer_id' => $customer->id,
            ], $attribution->attributesForEvent()));

            app(NotificationService::class)->notifyPartnerOnce($affiliate, 'affiliate_referral_new', [
                'partner' => $affiliate->name,
                '_fallback_subject' => __('site.affiliate_portal.notify_referral_subject'),
                '_fallback_body' => __('site.affiliate_portal.notify_referral_body'),
            ], route('site.affiliate.referrals'), 'reg:'.$customer->id);
        }

        $attribution->clearSession();

        app(AffiliateFraudDetectionService::class)->scanAndPersist($affiliate);
    }

    /**
     * Existing-member attribution through the canonical persist path.
     *
     * @return 'attached'|'already'|'protected'|'not_allowed'|'ineligible'|'none'
     */
    public function connectMember(Customer $customer, Vendor $affiliate, ?Request $request = null, string $source = 'link'): string
    {
        $settings = app(AffiliateSettingsService::class);
        $attribution = app(AffiliateAttributionService::class);
        $existingId = (int) ($customer->affiliate_vendor_id ?? 0);

        if ($existingId === (int) $affiliate->id) {
            return 'already';
        }

        if ($existingId > 0) {
            if ($attribution->isLocked($customer) && ! $settings->allowOverrideAfterLock()) {
                return 'protected';
            }
            if ($settings->attributionModel() === 'first_valid' && ! $settings->allowReplacementBeforeLock()) {
                return 'protected';
            }
        }

        if ($existingId === 0 && $attribution->customerIsExistingBorrower($customer) && ! $settings->existingCustomerReferral()) {
            return 'not_allowed';
        }

        if (! app(AffiliateEligibilityService::class)->canAttributeNewReferral($affiliate)) {
            return 'ineligible';
        }

        $this->attachAffiliate($customer, $affiliate->affiliate_code ?: $this->ensureReferralToken($affiliate), $request);
        $customer->refresh();

        return (int) $customer->affiliate_vendor_id === (int) $affiliate->id ? 'attached' : 'none';
    }

    public function connectFromPendingClaim(Customer $customer, ?Request $request = null): ?array
    {
        $pending = app(AffiliateAttributionService::class)->pendingAffiliate($request);
        if (! $pending) {
            return null;
        }

        $outcome = $this->connectMember($customer, $pending, $request, 'login');

        return [
            'outcome' => $outcome,
            'affiliate' => $pending,
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function configuredBenefitItems(Vendor $affiliate, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $settings = app(AffiliateSettingsService::class);
        $items = [];

        $map = [
            'application_fee' => [$this->applicationDiscountPercent($affiliate), 'benefit_application_discount'],
            'registration_fee' => [$this->registrationDiscountPercent($affiliate), 'benefit_registration_discount'],
            'kopafasta_plus' => [$this->plusDiscountPercent($affiliate), 'benefit_plus_discount'],
        ];

        foreach ($map as $feeType => [$percent, $key]) {
            if (! $settings->appliesToFeeType($feeType) || $percent <= 0) {
                continue;
            }
            $items[] = [
                'key' => $feeType,
                'label' => __('site.affiliate_portal.'.$key, [
                    'percent' => rtrim(rtrim(number_format((float) $percent, 1, '.', ''), '0'), '.'),
                ], $locale),
            ];
        }

        return $items;
    }

    public function trackApplication(LoanApplication $application): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        if (! $customer->affiliate_vendor_id) {
            $this->attachAffiliate($customer, null);
            $customer->refresh();
        }

        if (! $customer->affiliate_vendor_id) {
            return;
        }

        $attribution = app(AffiliateAttributionService::class);
        if (app(AffiliateSettingsService::class)->attributionLockAt() === 'application_created') {
            $attribution->lockToApplication($customer, $application);
        }

        if (AffiliateEvent::query()
            ->where('loan_application_id', $application->id)
            ->where('event_type', 'application')
            ->exists()) {
            return;
        }

        AffiliateEvent::create([
            'vendor_id'           => $customer->affiliate_vendor_id,
            'event_type'          => 'application',
            'customer_id'         => $customer->id,
            'loan_application_id' => $application->id,
            'referral_code'       => $attribution->customerClaim($customer)['code_used'] ?? null,
        ]);

        $affiliate = Vendor::query()->find($customer->affiliate_vendor_id);
        if ($affiliate) {
            $template = $affiliate->isPremiumAffiliate() ? 'affiliate_impact_progressed' : 'affiliate_referral_progressed';
            app(NotificationService::class)->notifyPartnerOnce($affiliate, $template, [
                'partner' => $affiliate->name,
                '_fallback_subject' => __('site.affiliate_portal.notify_progressed_subject'),
                '_fallback_body' => __('site.affiliate_portal.notify_progressed_body'),
            ], route('site.affiliate.referrals'), 'app:'.$application->id);
        }
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

    public function plusDiscountPercent(Vendor $affiliate): float
    {
        $meta = is_array($affiliate->metadata ?? null) ? $affiliate->metadata : [];

        return (float) ($meta['plus_discount_percent']
            ?? app(AffiliateSettingsService::class)->forForm()['default_plus_discount_percent']
            ?? config('affiliates.default_plus_discount_percent', 10));
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

    /**
     * Canonical Share & Earn invitation. Reads the configured member benefit;
     * never invents a discount.
     */
    public function shareInvitation(Vendor $affiliate, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $context = $this->messageContext($affiliate);
        $benefit = $this->configuredMemberBenefit($affiliate, $locale);
        $params = [
            'brand' => $context['brand'] ?? brand_name(),
            'code' => $context['affiliate_code'] ?? '',
            'link' => $context['affiliate_link'] ?? '',
            'benefit' => $benefit,
            'name' => $affiliate->name,
        ];

        if ($benefit !== '') {
            return __('site.affiliate_portal.share_invite_with_benefit', $params, $locale);
        }

        return __('site.affiliate_portal.share_invite_neutral', $params, $locale);
    }

    public function configuredMemberBenefit(Vendor $affiliate, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $settings = app(AffiliateSettingsService::class);
        $parts = [];

        if ($settings->appliesToFeeType('application_fee')) {
            $percent = $this->applicationDiscountPercent($affiliate);
            if ($percent > 0) {
                $parts[] = __('site.affiliate_portal.benefit_application_discount', [
                    'percent' => rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.'),
                ], $locale);
            }
        }

        if ($settings->appliesToFeeType('registration_fee')) {
            $percent = $this->registrationDiscountPercent($affiliate);
            if ($percent > 0) {
                $parts[] = __('site.affiliate_portal.benefit_registration_discount', [
                    'percent' => rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.'),
                ], $locale);
            }
        }

        if ($settings->appliesToFeeType('kopafasta_plus')) {
            $percent = $this->plusDiscountPercent($affiliate);
            if ($percent > 0) {
                $parts[] = __('site.affiliate_portal.benefit_plus_discount', [
                    'percent' => rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.'),
                ], $locale);
            }
        }

        return implode(' · ', $parts);
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

    public function updateCode(Vendor $affiliate, string $code): string
    {
        abort_unless($affiliate->isAffiliate(), 403);

        $code = $this->assertPromoCodeShape($code);

        $current = strtoupper((string) ($affiliate->affiliate_code ?? ''));
        if ($code === $current) {
            return $code;
        }

        if (! app(AffiliateSettingsService::class)->affiliateCanEditPromoCode()) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_locked_hint'));
        }

        if (! $this->canChangeCode($affiliate)) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_cooldown', [
                'days' => app(AffiliateSettingsService::class)->promoChangeCooldownDays(),
            ]));
        }

        if (! $this->codeIsUnique($code, $affiliate->id)) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_taken'));
        }

        $meta = is_array($affiliate->metadata ?? null) ? $affiliate->metadata : [];
        $aliases = is_array($meta['promo_code_aliases'] ?? null) ? $meta['promo_code_aliases'] : [];
        $graceDays = app(AffiliateSettingsService::class)->promoOldCodeGraceDays();
        if ($current !== '') {
            $aliases[] = [
                'code' => $current,
                'retired_at' => now()->toIso8601String(),
                'grace_until' => $graceDays > 0
                    ? now()->addDays($graceDays)->toIso8601String()
                    : now()->toIso8601String(),
            ];
        }
        $meta['promo_code_aliases'] = array_values($aliases);
        $meta['promo_alias_codes'] = array_values(array_unique(array_map(
            fn ($row) => strtoupper((string) ($row['code'] ?? '')),
            $aliases
        )));
        $meta['affiliate_code_changed_at'] = now()->toIso8601String();
        $history = is_array($meta['affiliate_code_history'] ?? null) ? $meta['affiliate_code_history'] : [];
        $history[] = [
            'from' => $current,
            'to' => $code,
            'changed_at' => now()->toIso8601String(),
        ];
        $meta['affiliate_code_history'] = $history;

        $affiliate->update([
            'affiliate_code' => $code,
            'metadata' => $meta,
        ]);

        AffiliateEvent::create([
            'vendor_id' => $affiliate->id,
            'event_type' => 'promo_code_changed',
            'referral_code' => $code,
        ]);

        return $code;
    }

    public function assertPromoCodeShape(string $code): string
    {
        $rules = app(AffiliateSettingsService::class)->promoCodeSettings();
        $code = strtoupper(trim($code));
        $pattern = (string) ($rules['allowed_pattern'] ?? 'A-Z0-9_-');
        $code = preg_replace('/[^'.$pattern.']/', '', $code) ?? '';

        $min = max(2, (int) ($rules['min_length'] ?? 3));
        $max = max($min, (int) ($rules['max_length'] ?? 24));

        if (strlen($code) < $min || strlen($code) > $max) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.code_length', [
                'min' => $min,
                'max' => $max,
            ]));
        }

        $reserved = $rules['reserved'] ?? [];
        foreach ($reserved as $word) {
            if ($code === $word || str_contains($code, $word)) {
                throw new \InvalidArgumentException(__('site.affiliate_portal.code_reserved'));
            }
        }

        return $code;
    }

    public function canChangeCode(Vendor $affiliate): bool
    {
        if (! app(AffiliateSettingsService::class)->affiliateCanEditPromoCode()) {
            return false;
        }

        $meta = is_array($affiliate->metadata ?? null) ? $affiliate->metadata : [];
        $changedAt = $meta['affiliate_code_changed_at'] ?? null;
        $cooldown = app(AffiliateSettingsService::class)->promoChangeCooldownDays();
        if (! $changedAt || $cooldown <= 0) {
            return true;
        }

        return now()->gte(\Illuminate\Support\Carbon::parse($changedAt)->addDays($cooldown));
    }

    public function nextCodeChangeAt(Vendor $affiliate): ?\Illuminate\Support\Carbon
    {
        $meta = is_array($affiliate->metadata ?? null) ? $affiliate->metadata : [];
        $changedAt = $meta['affiliate_code_changed_at'] ?? null;
        $cooldown = app(AffiliateSettingsService::class)->promoChangeCooldownDays();
        if (! $changedAt || $cooldown <= 0) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($changedAt)->addDays($cooldown);
    }

    public function codeIsUnique(string $code, ?int $exceptVendorId = null): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        $taken = Vendor::query()
            ->where('category', 'affiliate')
            ->when($exceptVendorId, fn ($q) => $q->where('id', '!=', $exceptVendorId))
            ->where(function ($query) use ($code) {
                $query->where('affiliate_code', $code)
                    ->orWhere('partner_number', $code)
                    ->orWhere('metadata->referral_token', $code)
                    ->orWhere('metadata->legacy_partner_number', $code)
                    ->orWhere('metadata->legacy_referral_token', $code);
            })
            ->exists();

        if ($taken) {
            return false;
        }

        return $this->resolveByAlias($code, $exceptVendorId) === null;
    }

    private function resolveByAlias(string $code, ?int $exceptVendorId = null): ?Vendor
    {
        $code = strtoupper(trim($code));
        $candidates = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', 'active')
            ->when($exceptVendorId, fn ($q) => $q->where('id', '!=', $exceptVendorId))
            ->where('metadata', 'like', '%'.$code.'%')
            ->get();

        foreach ($candidates as $affiliate) {
            $aliases = is_array($affiliate->metadata['promo_code_aliases'] ?? null)
                ? $affiliate->metadata['promo_code_aliases']
                : [];
            foreach ($aliases as $alias) {
                if (strtoupper((string) ($alias['code'] ?? '')) !== $code) {
                    continue;
                }
                $graceUntil = $alias['grace_until'] ?? null;
                if ($graceUntil && now()->lte(\Illuminate\Support\Carbon::parse($graceUntil))) {
                    return $affiliate;
                }
            }
        }

        return null;
    }

    /**
     * Affiliate discount quote (referral takes precedence elsewhere).
     *
     * @return array{base: float, discount: float, after_discount: float, commission: float, affiliate: Vendor|null, has_affiliate: bool}
     */
    public function quoteFee(Customer $customer, float $baseAmount, string $feeType): array
    {
        $feeType = \App\Models\CustomerPayment::canonicalType($feeType);
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
            'kopafasta_plus' => $this->plusDiscountPercent($affiliate),
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

        if (app(GrowthPointsService::class)->isNonEarnable($customer)) {
            return null;
        }

        $quote = $this->quoteFee($customer, $baseAmount, $feeType);
        if (! $quote['affiliate'] || $quote['commission'] <= 0) {
            return null;
        }

        if (! app(AffiliateEligibilityService::class)->canEarnFromNewBusiness($quote['affiliate'])) {
            return null;
        }

        $payment = ($refType === CustomerPayment::class && $refId)
            ? CustomerPayment::query()->find($refId)
            : null;
        $paymentKey = $payment ? 'payment:'.$payment->id : null;

        if ($payment?->reference) {
            $existingWallet = PartnerPayment::query()
                ->where('source_type', 'affiliate_commission')
                ->where('reference', $payment->reference)
                ->first();
            if ($existingWallet) {
                return AffiliateEvent::query()->find($existingWallet->source_id);
            }
        }

        if ($paymentKey) {
            $existingEvent = AffiliateEvent::query()
                ->where('customer_id', $customer->id)
                ->where('event_type', 'commission_'.$feeType)
                ->where('landing_page', $paymentKey)
                ->first();
            if ($existingEvent) {
                return $existingEvent;
            }
        }

        return DB::transaction(function () use ($quote, $customer, $feeType, $payment, $paymentKey): AffiliateEvent {
            $event = AffiliateEvent::create([
                'vendor_id'           => $quote['affiliate']->id,
                'event_type'          => 'commission_'.$feeType,
                'customer_id'         => $customer->id,
                'commission_amount'   => $quote['commission'],
                'landing_page'        => $paymentKey,
            ]);

            app(PartnerSettlementService::class)->accrue(
                $quote['affiliate'],
                (int) round($quote['commission']),
                'affiliate_commission',
                $event->id,
                'Affiliate commission on '.str_replace('_', ' ', $feeType),
                null,
                $payment?->reference,
            );

            app(NotificationService::class)->notifyPartnerOnce($quote['affiliate'], 'affiliate_commission_earned', [
                'partner' => $quote['affiliate']->name,
                'amount' => format_money($quote['commission']),
                '_fallback_subject' => __('site.affiliate_portal.notify_commission_subject'),
                '_fallback_body' => __('site.affiliate_portal.notify_commission_body', [
                    'amount' => format_money($quote['commission']),
                ]),
            ], route('site.affiliate.wallet'), 'evt:'.$event->id);

            return $event;
        });
    }
}
