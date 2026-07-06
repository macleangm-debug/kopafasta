<?php

namespace App\Services;

use App\Models\Setting;

class GamificationSettingsService
{
    public function group(string $section): array
    {
        $stored = Setting::group('gamification.'.$section);
        $defaults = config('gamification.'.$section, []);

        return array_replace_recursive(is_array($defaults) ? $defaults : [], $stored);
    }

    public function all(): array
    {
        $sections = [
            'referral_levels',
            'referral_level_benefits',
            'referral_milestones',
            'trust_score',
            'community_milestones',
            'repayment_streak',
            'profile_strength',
            'loyalty_points',
            'notifications',
            'leaderboard',
        ];

        $out = [];
        foreach ($sections as $section) {
            $out[$section] = $this->group($section);
        }

        return $out;
    }

    public function saveSection(string $section, array $data): void
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs['gamification.'.$section.'.'.$key] = $value;
        }

        if ($pairs !== []) {
            Setting::setMany($pairs);
        }
    }

    public function saveRaw(string $fullKey, mixed $value): void
    {
        Setting::set('gamification.'.$fullKey, $value);
    }

    /** @return list<array<string, mixed>> */
    public function referralLevels(): array
    {
        $stored = Setting::get('gamification.referral_levels.levels');

        return is_array($stored) && $stored !== []
            ? $stored
            : config('gamification.referral_levels', []);
    }

    /** @return list<array<string, mixed>> */
    public function referralMilestones(): array
    {
        $stored = Setting::get('gamification.referral_milestones.milestones');

        return is_array($stored) && $stored !== []
            ? $stored
            : config('gamification.referral_milestones', []);
    }

    /** @return list<array<string, mixed>> */
    public function profileStrengthTiers(): array
    {
        $stored = Setting::get('gamification.profile_strength.tiers');

        return is_array($stored) && $stored !== []
            ? $stored
            : config('gamification.profile_strength', []);
    }

    /** @return array<string, int> */
    public function loyaltyActionPoints(): array
    {
        $actions = $this->group('loyalty_points')['actions']
            ?? config('gamification.loyalty_points.actions', []);

        $out = [];
        foreach ($actions as $key => $action) {
            $out[$key] = (int) ($action['points'] ?? 0);
        }

        return $out;
    }

    /** @return list<string> */
    public function notificationCategories(): array
    {
        return $this->group('notifications')['categories']
            ?? config('gamification.notifications.categories', []);
    }
}
