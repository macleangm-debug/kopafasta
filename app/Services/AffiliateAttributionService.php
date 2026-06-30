<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateAttributionService
{
    public const SESSION_KEY = 'affiliate_attribution';

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
    }

    /** @param  array<string, mixed>  $attribution */
    public function attributesForEvent(array $attribution = []): array
    {
        $source = $attribution !== [] ? $attribution : $this->fromSession();

        return array_filter([
            'referral_code' => $source['referral_code'] ?? null,
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
}
