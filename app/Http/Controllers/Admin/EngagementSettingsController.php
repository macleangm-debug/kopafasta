<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GamificationSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EngagementSettingsController extends Controller
{
    public function __construct(
        private readonly GamificationSettingsService $gamification,
    ) {}

    public function index(): View
    {
        return view('admin.settings.engagement.index');
    }

    public function referralLevels(): View
    {
        return view('admin.settings.engagement.referral-levels', [
            'levels'   => $this->gamification->group('referral_levels')['levels'] ?? config('gamification.referral_levels'),
            'benefits' => $this->gamification->group('referral_level_benefits'),
            'milestones' => $this->gamification->group('referral_milestones')['milestones'] ?? config('gamification.referral_milestones'),
        ]);
    }

    public function saveReferralLevels(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'levels'              => ['required', 'array'],
            'levels.*.key'        => ['required', 'string', 'max:30'],
            'levels.*.label'      => ['required', 'string', 'max:60'],
            'levels.*.min_referrals'=> ['required', 'integer', 'min:0'],
            'levels.*.max_referrals'=> ['nullable', 'integer', 'min:0'],
            'milestones'          => ['nullable', 'array'],
            'milestones.*.target' => ['required_with:milestones', 'integer', 'min:1'],
            'milestones.*.reward_label' => ['required_with:milestones', 'string', 'max:120'],
        ]);

        Setting::set('gamification.referral_levels.levels', $data['levels']);
        Setting::set('gamification.referral_milestones.milestones', $data['milestones'] ?? []);

        return back()->with('status', 'Referral levels saved.');
    }

    public function trustScore(): View
    {
        return view('admin.settings.engagement.trust-score', [
            'values' => $this->gamification->group('trust_score'),
        ]);
    }

    public function saveTrustScore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_stars'                   => ['required', 'integer', 'min:1', 'max:10'],
            'weights.on_time_payments'    => ['required', 'integer', 'min:0', 'max:100'],
            'weights.profile_completion'    => ['required', 'integer', 'min:0', 'max:100'],
            'weights.referrals'           => ['required', 'integer', 'min:0', 'max:100'],
            'weights.account_age'         => ['required', 'integer', 'min:0', 'max:100'],
            'weights.successful_loans'    => ['required', 'integer', 'min:0', 'max:100'],
            'benefits'                    => ['nullable', 'string'],
        ]);

        $data['benefits'] = collect(preg_split("/\r\n|\n|\r/", (string) ($data['benefits'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        Setting::set('gamification.trust_score', $data);

        return back()->with('status', 'Trust score settings saved.');
    }

    public function milestones(): View
    {
        return view('admin.settings.engagement.milestones', [
            'milestones' => $this->gamification->group('community_milestones')['milestones']
                ?? config('gamification.community_milestones'),
        ]);
    }

    public function saveMilestones(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'milestones'              => ['required', 'array'],
            'milestones.*.key'        => ['required', 'string', 'max:40'],
            'milestones.*.target'     => ['required', 'integer', 'min:1'],
            'milestones.*.title'      => ['required', 'string', 'max:120'],
            'milestones.*.rewards'    => ['nullable', 'string'],
        ]);

        $normalized = collect($data['milestones'])->map(function (array $row) {
            $row['rewards'] = collect(preg_split("/\r\n|\n|\r/", (string) ($row['rewards'] ?? '')))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all();

            return $row;
        })->all();

        Setting::set('gamification.community_milestones.milestones', $normalized);

        return back()->with('status', 'Community milestones saved.');
    }

    public function repaymentStreak(): View
    {
        return view('admin.settings.engagement.repayment-streak', [
            'values' => $this->gamification->group('repayment_streak'),
        ]);
    }

    public function saveRepaymentStreak(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'              => ['nullable', 'boolean'],
            'reward_label'         => ['required', 'string', 'max:120'],
            'fee_type'             => ['nullable', 'string', 'max:60'],
            'max_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'milestone_rows'       => ['nullable', 'array'],
            'milestone_rows.*.count'   => ['nullable', 'integer', 'min:1'],
            'milestone_rows.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $defaults = config('gamification.repayment_streak', []);
        $milestones = collect($data['milestone_rows'] ?? [])
            ->map(fn (array $row) => [
                'count'   => (int) ($row['count'] ?? 0),
                'percent' => (float) ($row['percent'] ?? 0),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();

        Setting::set('gamification.repayment_streak', [
            'enabled'              => $request->boolean('enabled'),
            'reward_label'         => $data['reward_label'],
            'fee_type'             => $data['fee_type'] ?? ($defaults['fee_type'] ?? 'application_fee'),
            'max_discount_percent' => (float) ($data['max_discount_percent'] ?? ($defaults['max_discount_percent'] ?? 30)),
            'milestones'           => $milestones ?: ($defaults['milestones'] ?? []),
        ]);

        return back()->with('status', 'Repayment streak settings saved.');
    }

    public function profileStrength(): View
    {
        return view('admin.settings.engagement.profile-strength', [
            'tiers' => $this->gamification->group('profile_strength')['tiers']
                ?? config('gamification.profile_strength'),
        ]);
    }

    public function saveProfileStrength(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tiers'                 => ['required', 'array'],
            'tiers.*.key'           => ['required', 'string', 'max:30'],
            'tiers.*.label'         => ['required', 'string', 'max:60'],
            'tiers.*.min_percent'   => ['required', 'integer', 'min:0', 'max:100'],
            'tiers.*.max_percent'   => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        Setting::set('gamification.profile_strength.tiers', $data['tiers']);

        return back()->with('status', 'Profile strength tiers saved.');
    }

    public function loyaltyPoints(): View
    {
        return view('admin.settings.engagement.loyalty-points', [
            'values' => $this->gamification->group('loyalty_points'),
        ]);
    }

    public function saveLoyaltyPoints(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'actions'                         => ['required', 'array'],
            'actions.*.label'                 => ['required', 'string', 'max:80'],
            'actions.*.points'                => ['required', 'integer', 'min:0'],
            'redemption_options'              => ['nullable', 'array'],
            'redemption_options.*.key'        => ['required_with:redemption_options', 'string', 'max:60'],
            'redemption_options.*.label'      => ['required_with:redemption_options', 'string', 'max:120'],
            'redemption_options.*.label_sw'   => ['nullable', 'string', 'max:120'],
            'redemption_options.*.description'=> ['nullable', 'string', 'max:255'],
            'redemption_options.*.description_sw' => ['nullable', 'string', 'max:255'],
            'redemption_options.*.points'     => ['required_with:redemption_options', 'integer', 'min:1'],
            'redemption_options.*.benefit_type' => ['required_with:redemption_options', 'string', 'max:40'],
            'redemption_options.*.benefit_value'=> ['nullable', 'numeric', 'min:0'],
            'redemption_options.*.fee_type'   => ['nullable', 'string', 'max:40'],
            'redemption_options.*.expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $existing = $this->gamification->group('loyalty_points');
        $payload = [
            'actions'            => $data['actions'],
            'redemption_options' => array_values($data['redemption_options'] ?? ($existing['redemption_options'] ?? config('gamification.loyalty_points.redemption_options', []))),
        ];

        Setting::set('gamification.loyalty_points', $payload);

        return back()->with('status', 'Loyalty points settings saved.');
    }

    public function underwriting(): View
    {
        $stored = Setting::get('gamification.underwriting_boosts');
        $values = is_array($stored) && $stored !== []
            ? array_replace_recursive(config('gamification.underwriting_boosts', []), $stored)
            : config('gamification.underwriting_boosts', []);

        return view('admin.settings.engagement.underwriting', [
            'values' => $values,
            'levels' => $this->gamification->group('referral_levels')['levels'] ?? config('gamification.referral_levels'),
        ]);
    }

    public function saveUnderwriting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'referral_level'                               => ['required', 'array'],
            'referral_level.*.limit_multiplier'            => ['required', 'numeric', 'min:1', 'max:2'],
            'referral_level.*.rate_discount'               => ['required', 'numeric', 'min:0', 'max:0.05'],
            'referral_level.*.processing_priority'         => ['required', 'integer', 'min:0', 'max:10'],
            'trust_score.limit_multiplier_per_step'        => ['required', 'numeric', 'min:0', 'max:0.1'],
            'trust_score.rate_discount_per_step'           => ['required', 'numeric', 'min:0', 'max:0.01'],
            'trust_score.processing_priority_per_step'     => ['required', 'numeric', 'min:0', 'max:2'],
        ]);

        Setting::set('gamification.underwriting_boosts', $data);

        return back()->with('status', 'Underwriting boosts saved.');
    }

    public function notifications(): View
    {
        return view('admin.settings.engagement.notifications', [
            'values' => $this->gamification->group('notifications'),
            'leaderboard' => $this->gamification->group('leaderboard'),
        ]);
    }

    public function saveNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categories'        => ['required', 'array'],
            'categories.*'      => ['string', 'max:40'],
            'leaderboard_enabled'=> ['nullable', 'boolean'],
            'leaderboard_limit' => ['required', 'integer', 'min:1', 'max:50'],
            'mask_names'        => ['nullable', 'boolean'],
        ]);

        Setting::set('gamification.notifications.categories', $data['categories']);
        Setting::set('gamification.leaderboard', [
            'enabled'    => $request->boolean('leaderboard_enabled'),
            'limit'      => (int) $data['leaderboard_limit'],
            'mask_names' => $request->boolean('mask_names'),
        ]);

        return back()->with('status', 'Notification center settings saved.');
    }
}
