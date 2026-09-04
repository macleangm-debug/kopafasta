<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;

class AccountWelcomeService
{
    public const PREF_KEY = 'account_welcome_completed';

    public function forUser(?User $user): ?array
    {
        if (! $user || ! config('account_welcome.enabled', true)) {
            return null;
        }

        $audience = $this->audienceFor($user);
        if (! $audience) {
            return null;
        }

        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $completed = $prefs[self::PREF_KEY] ?? [];
        if (is_string($completed) || ! empty($completed[$audience]) || ! empty($prefs['account_welcome_completed_at'])) {
            return null;
        }

        $cards = collect(config('account_welcome.audiences.'.$audience, []))
            ->map(function (array $card) {
                $variant = (string) ($card['variant'] ?? 'default');
                $body = __($card['body']);
                if ($variant === 'rewards') {
                    $hint = app(LoyaltyRedemptionService::class)->onboardingHint();
                    if (filled($hint)) {
                        $body = trim($body.' '.$hint);
                    }
                }

                return [
                    'title' => __($card['title']),
                    'body' => $body,
                    'illustration' => (string) ($card['illustration'] ?? 'wallet'),
                    'variant' => $variant,
                ];
            })
            ->filter(fn (array $card) => filled($card['title']))
            ->values()
            ->all();

        if ($cards === []) {
            return null;
        }

        return [
            'audience' => $audience,
            'cards' => $cards,
        ];
    }

    public function complete(User $user, ?string $audience = null): void
    {
        $audience = $audience ?: $this->audienceFor($user);
        if (! $audience) {
            return;
        }

        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $done = is_array($prefs[self::PREF_KEY] ?? null) ? $prefs[self::PREF_KEY] : [];
        $done[$audience] = now()->toIso8601String();
        $prefs[self::PREF_KEY] = $done;
        $user->forceFill(['preferences' => $prefs])->save();
    }

    public function homeUrl(User $user): string
    {
        return match ($user->role) {
            'borrower', 'customer' => route('site.borrower.dashboard'),
            'investor' => route('site.investor.dashboard'),
            'vendor' => app(PartnerPortalRedirectService::class)->homeUrl($user),
            default => route('site.home'),
        };
    }

    public function audienceFor(User $user): ?string
    {
        if (in_array($user->role, ['customer', 'borrower'], true)) {
            return 'borrower';
        }

        if (! in_array($user->role, ['vendor', 'investor'], true)) {
            return null;
        }

        $vendor = Vendor::query()->where('user_id', $user->id)->first();
        $category = (string) ($vendor?->category ?? '');

        return match ($category) {
            'affiliate' => 'affiliate',
            'valuer' => 'valuer',
            'gps_installer' => 'gps_installer',
            'insurance' => 'insurance',
            'call_center', 'debt_collector', 'auctioneer', 'legal_partner' => 'recovery',
            default => null,
        };
    }
}
