<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class AffiliateAttributionService
{
    public const SESSION_KEY = 'affiliate_attribution';

    public const CLAIM_SESSION_KEY = 'affiliate_claim';

    public const COOKIE_KEY = 'kf_aff';

    public const CUSTOMER_META_KEY = 'affiliate_attribution';

    /** @return array<string, string|null> */
    public function captureFromRequest(Request $request): array
    {
        $campaign = $request->query('campaign') ?: $request->query('utm_campaign');

        return array_filter([
            'referral_code' => filled($request->query('ref'))
                ? strtoupper(trim((string) $request->query('ref')))
                : null,
            'campaign'      => filled($campaign) ? Str::limit((string) $campaign, 120, '') : null,
            'landing_page'  => Str::limit($request->fullUrl(), 500, ''),
            'referrer_url'  => Str::limit((string) $request->headers->get('referer'), 500, ''),
            'utm_source'    => Str::limit((string) $request->query('utm_source', ''), 120, '') ?: null,
            'utm_medium'    => Str::limit((string) $request->query('utm_medium', ''), 120, '') ?: null,
            'utm_campaign'  => Str::limit((string) $request->query('utm_campaign', ''), 120, '') ?: null,
            'utm_term'      => Str::limit((string) $request->query('utm_term', ''), 120, '') ?: null,
            'utm_content'   => Str::limit((string) $request->query('utm_content', ''), 120, '') ?: null,
            'ip_address'    => $request->ip(),
            'user_agent'    => Str::limit((string) $request->userAgent(), 255, ''),
            'device_type'   => $this->detectDevice((string) $request->userAgent()),
            'device_fingerprint' => app(AffiliateDeviceFingerprintService::class)->fromRequest($request),
        ], fn ($value) => filled($value));
    }

    /** @return array<string, string|null> */
    public function mergeIntoSession(Request $request): array
    {
        $incoming = $this->captureFromRequest($request);
        if ($incoming === []) {
            return session(self::SESSION_KEY, []);
        }

        $merged = array_merge(session(self::SESSION_KEY, []), $incoming);
        session([self::SESSION_KEY => $merged]);

        return $merged;
    }

    /** @return array<string, string|null> */
    public function fromSession(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public function clearSession(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->forget(self::CLAIM_SESSION_KEY);
        session()->forget('affiliate_code');
    }

    /**
     * Record a first-touch Affiliate claim from a referral link (or equivalent).
     *
     * @return array<string, mixed>
     */
    public function establishClaim(Request $request, Vendor $affiliate, string $source, ?string $codeUsed = null): array
    {
        $settings = app(AffiliateSettingsService::class);
        $existing = $this->pendingClaim($request);
        $model = $settings->attributionModel();
        $canReplace = $settings->allowReplacementBeforeLock();

        if ($existing && (int) ($existing['affiliate_id'] ?? 0) !== (int) $affiliate->id) {
            if ($model === 'first_valid' && ! $canReplace) {
                return $existing;
            }
        }

        $now = now();
        $claim = [
            'affiliate_id' => (int) $affiliate->id,
            'code_used' => strtoupper(trim((string) ($codeUsed ?: $affiliate->affiliate_code))),
            'source' => $source,
            'attributed_at' => $now->toIso8601String(),
            'expires_at' => $now->copy()->addDays($settings->attributionWindowDays())->toIso8601String(),
            'policy_version' => $settings->policyVersion(),
            'window_days' => $settings->attributionWindowDays(),
        ];

        session([
            self::CLAIM_SESSION_KEY => $claim,
            'affiliate_code' => $claim['code_used'],
        ]);

        $utm = $this->mergeIntoSession($request);
        session([self::SESSION_KEY => array_merge($utm, [
            'referral_code' => $claim['code_used'],
            'affiliate_id' => $claim['affiliate_id'],
        ])]);

        if ($settings->cookieEnabled()) {
            cookie()->queue($this->claimCookie($claim));
        }

        return $claim;
    }

    /** @return array<string, mixed>|null */
    public function pendingClaim(?Request $request = null): ?array
    {
        $request = $request ?: request();
        $claim = session(self::CLAIM_SESSION_KEY);
        if (! is_array($claim) || empty($claim['affiliate_id'])) {
            $claim = $this->claimFromCookie($request);
        }

        if (! is_array($claim) || empty($claim['affiliate_id'])) {
            $code = session('affiliate_code') ?: $request?->cookie(self::COOKIE_KEY);
            if (is_string($code) && $code !== '' && ! str_contains($code, '{')) {
                $affiliate = app(AffiliateService::class)->findByCode($code);
                if ($affiliate) {
                    $claim = [
                        'affiliate_id' => (int) $affiliate->id,
                        'code_used' => strtoupper($code),
                        'source' => 'session',
                        'attributed_at' => now()->toIso8601String(),
                        'expires_at' => now()->addDays(app(AffiliateSettingsService::class)->attributionWindowDays())->toIso8601String(),
                        'policy_version' => app(AffiliateSettingsService::class)->policyVersion(),
                    ];
                }
            }
        }

        if (! is_array($claim) || empty($claim['affiliate_id'])) {
            return null;
        }

        $expiresAt = $claim['expires_at'] ?? null;
        if (filled($expiresAt) && now()->gt(\Illuminate\Support\Carbon::parse($expiresAt))) {
            return null;
        }

        return $claim;
    }

    public function pendingAffiliate(?Request $request = null): ?Vendor
    {
        $claim = $this->pendingClaim($request);
        if (! $claim) {
            return null;
        }

        return Vendor::query()
            ->where('category', 'affiliate')
            ->whereKey((int) $claim['affiliate_id'])
            ->first();
    }

    /** @return array<string, mixed>|null */
    public function customerClaim(Customer $customer): ?array
    {
        $details = is_array($customer->activity_details) ? $customer->activity_details : [];
        $claim = $details[self::CUSTOMER_META_KEY] ?? null;

        return is_array($claim) && ! empty($claim['affiliate_id']) ? $claim : null;
    }

    public function isLocked(Customer $customer): bool
    {
        $claim = $this->customerClaim($customer);

        return filled($claim['locked_at'] ?? null);
    }

    /**
     * Persist a customer-level Affiliate relationship. Returns true when attribution was written.
     */
    public function persistOnCustomer(Customer $customer, Vendor $affiliate, array $claim, bool $lock = false): bool
    {
        $settings = app(AffiliateSettingsService::class);
        $existingId = (int) ($customer->affiliate_vendor_id ?? 0);
        $alreadyLocked = $this->isLocked($customer);

        if ($existingId > 0 && $existingId !== (int) $affiliate->id) {
            if ($alreadyLocked && ! $settings->allowOverrideAfterLock()) {
                return false;
            }
            if (! $alreadyLocked && $settings->attributionModel() === 'first_valid' && ! $settings->allowReplacementBeforeLock()) {
                return false;
            }
        }

        if ($existingId > 0 && $existingId === (int) $affiliate->id && ! $lock) {
            return true;
        }

        if ($this->customerIsExistingBorrower($customer) && ! $settings->existingCustomerReferral() && $existingId === 0) {
            return false;
        }

        $now = now();
        $stored = array_merge($this->customerClaim($customer) ?? [], $claim, [
            'affiliate_id' => (int) $affiliate->id,
            'code_used' => strtoupper((string) ($claim['code_used'] ?? $affiliate->affiliate_code)),
            'transferred_at' => $now->toIso8601String(),
        ]);

        if ($lock) {
            $stored['locked_at'] = $stored['locked_at'] ?? $now->toIso8601String();
            $stored['lock_point'] = $settings->attributionLockAt();
        }

        $details = is_array($customer->activity_details) ? $customer->activity_details : [];
        $details[self::CUSTOMER_META_KEY] = $stored;

        $customer->update([
            'affiliate_vendor_id' => $affiliate->id,
            'activity_details' => $details,
        ]);

        return true;
    }

    public function lockToApplication(Customer $customer, LoanApplication $application): void
    {
        $affiliateId = (int) ($customer->affiliate_vendor_id ?? 0);
        if ($affiliateId <= 0) {
            return;
        }

        $affiliate = Vendor::query()->find($affiliateId);
        if ($affiliate) {
            $this->persistOnCustomer($customer, $affiliate, $this->customerClaim($customer) ?? [
                'affiliate_id' => $affiliateId,
                'code_used' => (string) $affiliate->affiliate_code,
                'source' => 'application',
            ], lock: true);
        }
    }

    public function customerIsExistingBorrower(Customer $customer): bool
    {
        return $customer->applications()->exists() || $customer->loans()->exists();
    }

    /** @param  array<string, mixed>  $attribution */
    public function attributesForEvent(array $attribution = []): array
    {
        $source = $attribution !== [] ? $attribution : $this->fromSession();
        $claim = is_array(session(self::CLAIM_SESSION_KEY)) ? session(self::CLAIM_SESSION_KEY) : [];

        return array_filter([
            'referral_code' => $source['referral_code'] ?? $claim['code_used'] ?? null,
            'campaign'      => $source['campaign'] ?? null,
            'landing_page'  => $source['landing_page'] ?? null,
            'referrer_url'  => $source['referrer_url'] ?? null,
            'utm_source'    => $source['utm_source'] ?? null,
            'utm_medium'    => $source['utm_medium'] ?? null,
            'utm_campaign'  => $source['utm_campaign'] ?? null,
            'utm_term'      => $source['utm_term'] ?? null,
            'utm_content'   => $source['utm_content'] ?? null,
            'ip_address'    => $source['ip_address'] ?? null,
            'user_agent'    => $source['user_agent'] ?? null,
            'device_type'   => $source['device_type'] ?? null,
            'device_fingerprint' => $source['device_fingerprint']
                ?? app(AffiliateDeviceFingerprintService::class)->fromAttributes($source),
        ], fn ($value) => filled($value));
    }

    public function detectDevice(?string $userAgent): ?string
    {
        if (blank($userAgent)) {
            return null;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /** @param  array<string, mixed>  $claim */
    public function claimCookie(array $claim): Cookie
    {
        $minutes = max(60, app(AffiliateSettingsService::class)->attributionWindowDays() * 1440);

        return cookie(
            self::COOKIE_KEY,
            json_encode($claim, JSON_UNESCAPED_SLASHES),
            $minutes,
            '/',
            null,
            config('session.secure'),
            true,
            false,
            config('session.same_site', 'lax')
        );
    }

    /** @return array<string, mixed>|null */
    private function claimFromCookie(?Request $request): ?array
    {
        if (! $request || ! app(AffiliateSettingsService::class)->cookieEnabled()) {
            return null;
        }

        $raw = $request->cookie(self::COOKIE_KEY);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && ! empty($decoded['affiliate_id']) ? $decoded : null;
    }
}
