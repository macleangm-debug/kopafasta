<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use App\Services\Plus\PlusService;

class GrowthPointsService
{
    public function settings(): array
    {
        $group = Setting::group('referrals');

        return [
            'register_points' => (int) ($group['register_points'] ?? config('referrals.register_points', 5)),
            'application_points' => (int) ($group['application_points'] ?? config('referrals.application_points', 25)),
            'profile_points' => (int) (app(GamificationSettingsService::class)->loyaltyActionPoints()['complete_profile'] ?? 10),
        ];
    }

    public function isNonEarnable(Customer $customer): bool
    {
        $number = strtoupper((string) ($customer->customer_number ?? ''));
        if (str_starts_with($number, 'DEMO') || str_contains($number, '-DEMO')) {
            return true;
        }

        $email = strtolower((string) ($customer->user?->email ?? ''));

        return str_contains($email, '.demo@')
            || str_ends_with($email, '@demo.kopafasta.local')
            || str_contains($email, '+demo@');
    }

    public function awardRegistration(Customer $invitee): int
    {
        $referrer = app(ReferralService::class)->referrer($invitee);
        if (! $referrer || $this->isNonEarnable($referrer) || $this->isNonEarnable($invitee)) {
            return 0;
        }

        $points = max(0, $this->settings()['register_points']);
        if ($points <= 0) {
            return 0;
        }

        $name = trim(($invitee->first_name ?? '').' '.($invitee->last_name ?? '')) ?: 'A friend';

        return app(LoyaltyPointsService::class)->earnCustom(
            $referrer,
            $points,
            'refer_register',
            $name.' joined Kopafasta',
            Customer::class,
            (int) $invitee->id,
        );
    }

    public function awardFirstApplicationFee(Customer $invitee): int
    {
        $referrer = app(ReferralService::class)->referrer($invitee);
        if (! $referrer || $this->isNonEarnable($referrer) || $this->isNonEarnable($invitee)) {
            return 0;
        }

        $points = max(0, $this->settings()['application_points']);
        if ($points <= 0) {
            return 0;
        }

        $name = trim(($invitee->first_name ?? '').' '.($invitee->last_name ?? '')) ?: 'A friend';

        return app(LoyaltyPointsService::class)->earnCustom(
            $referrer,
            $points,
            'refer_application',
            $name.' submitted an application',
            Customer::class,
            (int) $invitee->id,
        );
    }

    public function awardOwnerAction(
        Customer $customer,
        string $actionKey,
        ?string $description = null,
        ?string $refType = null,
        ?int $refId = null,
    ): int {
        if ($this->isNonEarnable($customer)) {
            return 0;
        }

        return app(LoyaltyPointsService::class)->earn($customer, $actionKey, $description, $refType, $refId);
    }

    public function awardMonthlyMoneyCheckIn(Customer $customer): int
    {
        return $this->awardOwnerAction(
            $customer,
            'plus_money_checkin',
            null,
            'plus_money_month',
            (int) now()->format('Ym'),
        );
    }

    public function reverseUnusedCredits(
        Customer $customer,
        string $actionKey,
        string $refType,
        int $refId,
        string $reason = 'Reversed',
    ): int {
        return app(LoyaltyPointsService::class)->reverseUnused($customer, $actionKey, $refType, $refId, $reason);
    }

    public function allowRewardAndPromo(): bool
    {
        $stored = Setting::get('gamification.loyalty_points.stack_with_promo');
        if ($stored === null) {
            return (bool) config('gamification.loyalty_points.stack_with_promo', false);
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }

    public function plusIsActive(?Customer $customer): bool
    {
        return $customer ? app(PlusService::class)->isActive($customer) : false;
    }
}
