<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\PartnerPayoutRequest;
use App\Models\Vendor;
use App\Support\AffiliatePerformanceStatus;
use App\Support\MemberNumberFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AffiliatePortalPresenter
{
    public function __construct(
        private readonly AffiliateService $affiliates,
        private readonly AffiliateMembershipService $membership,
        private readonly AffiliateEligibilityService $eligibility,
        private readonly AffiliateEvaluationService $evaluation,
        private readonly AffiliateCommissionWalletService $wallet,
        private readonly PartnerPayoutRequestService $payouts,
        private readonly AffiliateSettingsService $settings,
        private readonly AffiliateTermsService $terms,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(Vendor $vendor): array
    {
        $this->affiliates->ensureCode($vendor);
        if ($vendor->isPremiumAffiliate()) {
            $this->membership->ensurePremiumAgreement($vendor);
            $vendor = $vendor->fresh();
        }

        $links = $this->affiliates->messageContext($vendor);
        $walletSummary = $this->wallet->summary($vendor);
        $available = $this->payouts->availableBalance($vendor, 'affiliate_commission');
        $reserved = $this->wallet->reservedAmount($vendor);
        $minPayout = $this->settings->minimumPayoutAmount();
        $eligibility = $this->eligibility->for($vendor);
        $standing = $this->evaluation->currentStanding($vendor);
        $commercial = $this->membership->summary($vendor);
        $monthStart = now()->copy()->startOfMonth();
        $funnel = $this->referralFunnel($vendor, $monthStart, now());
        $progress = $this->assessmentProgress($vendor, $standing);
        $activity = $this->recentActivity($vendor);
        $attention = $this->needsAttention($vendor, $eligibility, $commercial);
        $impact = $this->impactSnapshot($vendor);

        return [
            'vendor' => $vendor,
            'links' => $links,
            'share' => $this->affiliates->renderMessage($vendor, 'share_template'),
            'wallet' => $walletSummary,
            'available' => $available,
            'pending' => (int) ($walletSummary['pending'] ?? 0),
            'inProgress' => $reserved,
            'minPayout' => $minPayout,
            'remainingToWithdraw' => max(0, $minPayout - $available),
            'eligibility' => $eligibility,
            'standing' => $standing,
            'commercial' => $commercial,
            'funnel' => $funnel,
            'funnelKeys' => $this->visibleFunnelKeys(),
            'progress' => $progress,
            'impact' => $impact,
            'activity' => $activity,
            'attention' => $attention,
            'hero' => $this->hero($vendor, $links, $available, $walletSummary, $standing, $commercial, $eligibility, $attention),
            'recentReferrals' => $this->recentReferrals($vendor),
            'walletActivity' => $this->walletActivity($vendor),
            'monthlyCard' => $this->monthlyPerformanceCard($vendor, $monthStart),
        ];
    }

    /** @return array<string, mixed> */
    public function share(Vendor $vendor): array
    {
        $this->affiliates->ensureCode($vendor);
        $links = $this->affiliates->messageContext($vendor);
        $locale = app()->getLocale();

        $eligibility = $this->eligibility->for($vendor);

        return [
            'vendor' => $vendor,
            'links' => $links,
            'shareMessage' => $this->affiliates->shareInvitation($vendor),
            'smsMessage' => $this->settings->message('referral_sms', $links, $locale),
            'attributionWindow' => $this->settings->attributionWindowDays(),
            'eligibility' => $eligibility,
            'shareLock' => $this->needsAttention($vendor, $eligibility, $this->membership->summary($vendor)),
            'canChangeCode' => $this->affiliates->canChangeCode($vendor),
            'nextCodeChangeAt' => $this->affiliates->nextCodeChangeAt($vendor),
            'qrUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.urlencode($links['affiliate_link']),
        ];
    }

    /** @return array<string, mixed> */
    public function performance(Vendor $vendor): array
    {
        $standing = $this->evaluation->currentStanding($vendor);
        $settings = $this->settings->evaluationSettings();
        $premium = $vendor->isPremiumAffiliate();
        $periodDays = $this->settings->evaluationPeriodDays();

        return array_merge($this->wallet($vendor), [
            'vendor' => $vendor,
            'premium' => $premium,
            'standing' => $standing,
            'kpiCard' => $premium ? null : $this->kpiCard($standing),
            'progress' => $this->assessmentProgress($vendor, $standing),
            'impact' => $this->impactSnapshot($vendor),
            'funnel' => $this->referralFunnel($vendor, now()->copy()->startOfMonth(), now()),
            'funnelKeys' => $this->visibleFunnelKeys(),
            'overviewKeys' => $this->overviewFunnelKeys(),
            'monthlyCard' => $this->monthlyPerformanceCard($vendor),
            'pipeline' => $this->recentEarnings($vendor),
            'warningLadder' => [
                ['label' => __('site.affiliate_portal.performance_needs_attention'), 'periods' => $this->settings->volumeMissesBeforeNudge()],
                ['label' => __('site.affiliate_portal.performance_at_risk'), 'periods' => $this->settings->volumeMissesBeforeWatchlist()],
                ['label' => __('site.affiliate_portal.performance_suspended'), 'periods' => $this->settings->volumeMissesBeforeSuspend()],
            ],
            'assessmentExplanation' => __('site.affiliate_portal.faq_assessed_body', [
                'days' => $periodDays,
                'ramp' => $this->settings->volumeMinActiveDays(),
            ]),
            'recovery' => ($settings['auto_recover'] ?? true)
                ? __('site.affiliate_portal.recovery_enabled')
                : __('site.affiliate_portal.recovery_disabled'),
            'rampUpDays' => $this->settings->volumeMinActiveDays(),
            'nextAssessment' => $standing['period_end'] ?? now()->endOfDay(),
        ]);
    }

    /** @return array<string, mixed> */
    public function referrals(Vendor $vendor): array
    {
        return [
            'vendor' => $vendor,
            'funnel' => $this->referralFunnel($vendor, now()->copy()->startOfMonth(), now()),
            'pipeline' => $this->referralPipeline($vendor),
        ];
    }

    /** @return array<string, mixed> */
    public function monthlyReport(Vendor $vendor, ?string $month = null): array
    {
        $start = $this->parseMonthStart($month);
        $end = $start->copy()->endOfMonth();
        $prevStart = $start->copy()->subMonth()->startOfMonth();
        $prevEnd = $start->copy()->subSecond();
        $thisPeriod = $this->referralFunnel($vendor, $start, $end);
        $prevPeriod = $this->referralFunnel($vendor, $prevStart, $prevEnd);
        $standing = $this->evaluation->currentStanding($vendor);
        $wallet = $this->wallet($vendor);
        $payingNow = (int) ($thisPeriod['paying'] ?? 0);
        $payingPrev = (int) ($prevPeriod['paying'] ?? 0);
        $delta = $payingPrev > 0
            ? round(100 * ($payingNow - $payingPrev) / $payingPrev, 1)
            : null;

        return [
            'vendor' => $vendor,
            'month' => $start->format('Y-m'),
            'month_label' => $start->translatedFormat('F Y'),
            'title' => __('site.affiliate_portal.report_title', ['month' => $start->translatedFormat('F Y')]),
            'partner_name' => $vendor->name,
            'partner_type' => $vendor->isPremiumAffiliate()
                ? __('site.affiliate_portal.premium_partner')
                : __('site.affiliate_portal.standard_partner'),
            'partner_number' => $vendor->partner_number ?? $vendor->vendor_number,
            'activity' => $thisPeriod,
            'funnelKeys' => $this->visibleFunnelKeys(),
            'kpiCard' => $vendor->isPremiumAffiliate() ? null : $this->kpiCard($standing),
            'earnings' => [
                'qualifying_payments' => (int) ($thisPeriod['commission_transactions'] ?? 0),
                'payment_value' => (float) ($thisPeriod['payment_value'] ?? 0),
                'commission_earned' => (float) ($thisPeriod['earned'] ?? 0),
                'withdrawn' => (float) ($thisPeriod['withdrawn'] ?? 0),
                'available' => (float) ($wallet['available'] ?? 0),
                'pending' => (float) ($wallet['inProgress'] ?? 0),
            ],
            'withdrawals' => $thisPeriod['withdrawals'],
            'comparison' => [
                'previous_month' => $prevStart->translatedFormat('F'),
                'previous_label' => $prevStart->translatedFormat('F Y'),
                'previous_registered' => $payingPrev,
                'current_month' => $start->translatedFormat('F'),
                'current_label' => $start->translatedFormat('F Y'),
                'current_registered' => $payingNow,
                'delta_percent' => $delta,
            ],
            'months' => $this->availableReportMonths($vendor),
        ];
    }

    /** @return array<string, mixed> */
    public function wallet(Vendor $vendor): array
    {
        $this->wallet->promoteVerifiedCommissions($vendor);
        $summary = $this->wallet->summary($vendor);
        $available = $this->payouts->availableBalance($vendor, 'affiliate_commission');
        $approved = (int) ($summary['approved'] ?? 0);
        $paid = (int) ($summary['paid'] ?? 0);
        $reserved = $this->wallet->reservedAmount($vendor);
        $profile = app(PartnerProfileService::class);
        $minPayout = $this->settings->minimumPayoutAmount();
        $perPage = $this->settings->walletTransactionsPerPage();

        return [
            'vendor' => $vendor,
            'summary' => $summary,
            'payments' => $this->wallet->paginated($vendor, $perPage),
            'commissions' => $this->wallet->paginatedLedger($vendor, $reserved, $perPage),
            'withdrawals' => $this->wallet->withdrawals($vendor),
            'available' => $available,
            'minPayout' => $minPayout,
            'remainingToWithdraw' => max(0, $minPayout - $available),
            'pending' => (int) ($summary['pending'] ?? 0),
            'inProgress' => $reserved,
            'payoutAccountLabel' => $profile->payoutAccountLabel($vendor),
            'hasPayoutAccount' => $profile->hasPayoutAccount($vendor),
            'totals' => [
                'available' => $available,
                'pending' => (int) ($summary['pending'] ?? 0),
                'in_progress' => $reserved,
                'earned' => $approved + $paid + (int) ($summary['pending'] ?? 0),
                'withdrawn' => $paid,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function agreementDocument(Vendor $vendor): array
    {
        $commercial = $this->membership->summary($vendor);
        $acceptance = $this->terms->latestAcceptance($vendor);

        return [
            'vendor' => $vendor,
            'commercial' => $commercial,
            'acceptance' => $acceptance,
            'rendered' => $acceptance?->rendered_text ?: $this->terms->render($vendor),
            'header' => $this->terms->documentHeader($vendor, $commercial, $acceptance),
            'sections' => $this->terms->documentSections($vendor, $acceptance),
        ];
    }

    /** @param  array<string, mixed>  $eligibility */
    /** @param  array<string, mixed>  $commercial */
    /** @param  array<string, mixed>|null  $attention */
    /** @param  array<string, mixed>  $standing */
    /** @param  array<string, mixed>  $walletSummary */
    /** @param  array<string, string>  $links */
    /** @return array<string, mixed> */
    private function hero(
        Vendor $vendor,
        array $links,
        float $available,
        array $walletSummary,
        array $standing,
        array $commercial,
        array $eligibility,
        ?array $attention,
    ): array {
        $greeting = localized_time_greeting(strtok($vendor->name, ' ') ?: $vendor->name);
        $statusLabel = $standing['status_label'] ?? AffiliatePerformanceStatus::label((string) ($standing['status'] ?? ''));
        $code = $links['affiliate_code'] ?? $vendor->affiliate_code;

        $metaParts = [
            __('site.affiliate_portal.hero_pending', ['amount' => format_money($walletSummary['pending'] ?? 0)]),
            $statusLabel,
        ];

        if ($commercial['premium'] ?? false) {
            $metaParts[] = $commercial['active']
                ? __('site.affiliate_portal.hero_agreement_until', ['date' => $commercial['expires_at']?->format('d M Y')])
                : __('site.affiliate_portal.agreement_inactive');
        } elseif (($commercial['enabled'] ?? false) && ($commercial['active'] ?? false)) {
            $metaParts[] = __('site.affiliate_portal.hero_membership_until', ['date' => $commercial['expires_at']?->format('d M Y')]);
        }

        $agreementPending = in_array('terms_unaccepted', $eligibility['reasons'] ?? [], true)
            || in_array('agreement_inactive', $eligibility['reasons'] ?? [], true);

        return [
            'variant' => 'applications',
            'greeting' => $greeting,
            'grade' => $vendor->isPremiumAffiliate() ? 'premium' : null,
            'grade_label' => $vendor->isPremiumAffiliate() ? $this->settings->premiumBadgeLabel() : null,
            'membership_no' => $vendor->partner_number ?? null,
            'title' => $vendor->isPremiumAffiliate()
                ? null
                : __('site.affiliate_portal.welcome'),
            'subtitle' => implode(' · ', array_filter($metaParts)),
            'amount' => format_money($available),
            'amount_label' => __('site.affiliate_portal.hero_available'),
            'meta' => $code,
            'amount_compact' => format_money_compact($available),
            'cta_label' => $agreementPending
                ? __('site.affiliate_portal.review_accept_agreement')
                : null,
            'cta_url' => $agreementPending
                ? route('site.affiliate.profile', ['section' => 'agreement'])
                : null,
            'secondary_cta_label' => null,
            'secondary_cta_url' => null,
            'tertiary_cta_label' => null,
            'tertiary_cta_url' => null,
            'compact_mobile' => true,
            'agreement_pending' => $agreementPending,
        ];
    }

    /** @param  array<string, mixed>  $eligibility */
    /** @param  array<string, mixed>  $commercial */
    /** @return array<string, mixed>|null */
    private function needsAttention(Vendor $vendor, array $eligibility, array $commercial): ?array
    {
        if ($eligibility['can_share'] ?? false) {
            return null;
        }

        $reasons = $eligibility['reasons'] ?? [];
        if (in_array('terms_unaccepted', $reasons, true)) {
            return [
                'kind' => 'terms',
                'title' => __('site.affiliate_portal.lock_terms_title'),
                'body' => __('site.affiliate_portal.lock_terms_body'),
                'cta_label' => __('site.affiliate_portal.lock_terms_cta'),
                'cta_url' => route('site.affiliate.profile', ['section' => 'agreement']),
                'hero_only' => true,
            ];
        }
        if (in_array('profile_incomplete', $reasons, true) || in_array('kyc_unverified', $reasons, true)) {
            return [
                'kind' => 'profile',
                'title' => __('site.affiliate_portal.lock_profile_title'),
                'body' => __('site.affiliate_portal.lock_profile_body'),
                'cta_label' => __('site.affiliate_portal.lock_profile_cta'),
                'cta_url' => $this->profileCompletionUrl($vendor),
            ];
        }
        if (in_array('agreement_inactive', $reasons, true) || in_array('membership_inactive', $reasons, true)) {
            if ($commercial['premium'] ?? false) {
                return [
                    'kind' => 'agreement',
                    'title' => __('site.affiliate_portal.attention_agreement_title'),
                    'body' => __('site.affiliate_portal.attention_agreement_body'),
                    'cta_label' => __('site.affiliate_portal.review_accept_agreement'),
                    'cta_url' => route('site.affiliate.profile', ['section' => 'agreement']),
                    'hero_only' => true,
                ];
            }

            return [
                'kind' => 'membership',
                'title' => __('site.affiliate_portal.attention_membership_title'),
                'body' => __('site.affiliate_portal.attention_membership_body'),
                'cta_label' => __('site.affiliate_portal.membership_pay'),
                'cta_url' => route('site.affiliate.membership.pay'),
            ];
        }
        if (in_array('performance_suspended', $reasons, true)) {
            return [
                'kind' => 'performance',
                'title' => __('site.affiliate_portal.attention_performance_title'),
                'body' => __('site.affiliate_portal.attention_performance_body'),
                'cta_label' => __('site.affiliate_portal.nav_performance'),
                'cta_url' => route('site.affiliate.performance', ['tab' => 'overview']),
            ];
        }

        return [
            'kind' => 'generic',
            'title' => __('site.affiliate_portal.attention_generic_title'),
            'body' => __('site.affiliate_portal.eligibility_blocked'),
            'cta_label' => __('site.affiliate_portal.nav_profile'),
            'cta_url' => $this->profileCompletionUrl($vendor),
        ];
    }

    private function profileCompletionUrl(Vendor $vendor): string
    {
        $section = app(PartnerProfileService::class)->firstIncompleteSection($vendor);

        return $section
            ? route('site.affiliate.profile', ['section' => $section])
            : route('site.affiliate.profile');
    }

    /** @param  array<string, mixed>  $standing */
    /** @return array{label: string, achieved: float, target: float, percent: int, remaining: float, key: string}|null */
    private function kpiCard(array $standing): ?array
    {
        $rows = collect($standing['kpi_results'] ?? []);
        $kpi = $rows->first(fn ($row) => ($row['key'] ?? '') === 'paying_members'
            && ($row['enabled'] ?? false)
            && (float) ($row['target'] ?? 0) > 0)
            ?? $rows->first(fn ($row) => ($row['enabled'] ?? false)
                && (float) ($row['target'] ?? 0) > 0
                && ($row['key'] ?? '') !== 'qualified_referrals')
            ?? $rows->first(fn ($row) => ($row['enabled'] ?? false) && (float) ($row['target'] ?? 0) > 0);
        if (! is_array($kpi)) {
            return null;
        }

        $target = (float) ($kpi['target'] ?? 0);
        $achieved = (float) ($kpi['actual'] ?? 0);
        $percent = $target > 0 ? min(100, (int) round(($achieved / $target) * 100)) : 0;

        return [
            'label' => (string) ($kpi['label'] ?? __('site.affiliate_portal.monthly_target')),
            'achieved' => $achieved,
            'target' => $target,
            'percent' => $percent,
            'remaining' => max(0, $target - $achieved),
            'key' => (string) ($kpi['key'] ?? ''),
        ];
    }

    /** @param  array<string, mixed>  $standing */
    /** @return array<string, mixed> */
    private function assessmentProgress(Vendor $vendor, array $standing): array
    {
        $daysRemaining = max(0, (int) now()->startOfDay()->diffInDays(($standing['period_end'] ?? now())->copy()->startOfDay(), false));
        $primary = collect($standing['kpi_results'] ?? [])
            ->first(fn ($kpi) => ($kpi['enabled'] ?? false) && ($kpi['target'] ?? 0) > 0);

        return [
            'days_remaining' => $daysRemaining,
            'status_label' => $standing['status_label'] ?? '',
            'primary_kpi' => $primary,
            'needed' => $standing['needed_referrals'] ?? 0,
            'premium' => $vendor->isPremiumAffiliate(),
        ];
    }

    /** @return array<string, mixed> */
    private function impactSnapshot(Vendor $vendor): array
    {
        $funnel = $this->referralFunnel($vendor);
        $now = now();
        $thisStart = $now->copy()->startOfMonth();
        $prevStart = $thisStart->copy()->subMonth();
        $prevEnd = $thisStart->copy()->subSecond();

        $count = function (string $type, $from, $to) use ($vendor): int {
            return AffiliateEvent::query()
                ->where('partner_id', $vendor->id)
                ->where('event_type', $type)
                ->whereBetween('created_at', [$from, $to])
                ->count();
        };

        $visitsNow = $count('click', $thisStart, $now);
        $appsNow = $count('application', $thisStart, $now);
        $appsPrev = $count('application', $prevStart, $prevEnd);
        $regsNow = $count('registration', $thisStart, $now);

        $earned = (int) AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->where('event_type', 'like', 'commission_%')
            ->sum('commission_amount');

        $insights = [];
        if ($appsPrev > 0 && $appsNow > $appsPrev) {
            $insights[] = __('site.affiliate_portal.insight_apps_up', [
                'percent' => (int) round(100 * ($appsNow - $appsPrev) / $appsPrev),
            ]);
        }
        if ($visitsNow > 0) {
            $insights[] = __('site.affiliate_portal.insight_visits', ['count' => number_format($visitsNow)]);
        }
        if ($appsNow > 0) {
            $insights[] = __('site.affiliate_portal.insight_progressed', ['count' => number_format($appsNow)]);
        }

        return [
            'visited' => $funnel['visited'],
            'registered' => $funnel['registered'],
            'applied' => $funnel['applied'],
            'qualifying' => $funnel['qualifying'],
            'paying' => $funnel['paying'] ?? 0,
            'completed' => $funnel['completed'] ?? 0,
            'earned' => $earned,
            'visits_this_month' => $visitsNow,
            'apps_this_month' => $appsNow,
            'regs_this_month' => $regsNow,
            'insights' => array_slice($insights, 0, 3),
        ];
    }

    /** @return list<string> */
    public function visibleFunnelKeys(): array
    {
        return ['visited', 'registered', 'paying', 'completed'];
    }

    /** @return list<string> */
    public function overviewFunnelKeys(): array
    {
        return ['visited', 'registered', 'paying', 'completed'];
    }

    /** @return array<string, mixed> */
    public function monthlyPerformanceCard(Vendor $vendor, ?Carbon $start = null): array
    {
        $start = ($start ?? now()->copy()->startOfMonth())->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $funnel = $this->referralFunnel($vendor, $start, $end);

        return [
            'title' => __('site.affiliate_portal.month_performance', ['month' => $start->translatedFormat('F')]),
            'month' => $start->format('Y-m'),
            'funnel' => $funnel,
            'report_url' => route('site.affiliate.reports', ['month' => $start->format('Y-m')]),
        ];
    }

    /** @return array<string, mixed> */
    private function referralFunnel(Vendor $vendor, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $events = AffiliateEvent::query()->where('partner_id', $vendor->id);
        if ($from && $to) {
            $events->whereBetween('created_at', [$from, $to]);
        }

        $customerIds = (clone $events)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique();

        $approvedQuery = $customerIds->isEmpty()
            ? null
            : LoanApplication::query()
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', ['approved', 'pre_approved', 'awaiting_offer', 'disbursed']);
        if ($approvedQuery && $from && $to) {
            $approvedQuery->whereBetween('updated_at', [$from, $to]);
        }

        $commissionEvents = (clone $events)->where('event_type', 'like', 'commission_%');
        $registered = (clone $events)->where('event_type', 'registration')->count();
        $paymentIds = (clone $commissionEvents)
            ->pluck('landing_page')
            ->map(function ($page): int {
                $page = (string) $page;

                return str_starts_with($page, 'payment:') ? (int) substr($page, 8) : 0;
            })
            ->filter()
            ->values();

        $withdrawals = $this->withdrawalCounts($vendor, $from, $to);

        return [
            'visited' => (clone $events)->where('event_type', 'click')->count(),
            'registered' => $registered,
            'applied' => (clone $events)->where('event_type', 'application')->count(),
            'approved' => $approvedQuery?->count() ?? 0,
            'qualifying' => $registered,
            'paying' => (clone $commissionEvents)
                ->whereNotNull('customer_id')
                ->distinct()
                ->count('customer_id'),
            'completed' => (clone $commissionEvents)->count(),
            'commission' => (clone $commissionEvents)->count(),
            'commission_transactions' => (clone $commissionEvents)->count(),
            'earned' => (float) (clone $commissionEvents)->sum('commission_amount'),
            'payment_value' => $paymentIds->isEmpty()
                ? 0.0
                : (float) CustomerPayment::query()->whereIn('id', $paymentIds->all())->sum('amount'),
            'withdrawn' => (float) ($withdrawals['paid_amount'] ?? 0),
            'withdrawals' => $withdrawals,
        ];
    }

    /** @return array{requested: int, processing: int, paid: int, paid_amount: float} */
    private function withdrawalCounts(Vendor $vendor, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = PartnerPayoutRequest::query()->where('partner_id', $vendor->id);
        if (Schema::hasColumn('partner_payout_requests', 'source_type')) {
            $query->where('source_type', 'affiliate_commission');
        } elseif (Schema::hasColumn('partner_payout_requests', 'wallet_type')) {
            $query->where('wallet_type', 'affiliate_commission');
        }
        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $rows = $query->get(['status', 'amount']);

        return [
            'requested' => $rows->whereIn('status', ['pending', 'review'])->count(),
            'processing' => $rows->where('status', 'approved')->count(),
            'paid' => $rows->where('status', 'paid')->count(),
            'paid_amount' => (float) $rows->where('status', 'paid')->sum('amount'),
        ];
    }

    private function parseMonthStart(?string $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::parse($month.'-01')->startOfMonth();
        }

        return now()->copy()->startOfMonth();
    }

    /** @return list<string> */
    private function availableReportMonths(Vendor $vendor): array
    {
        $first = AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->orderBy('created_at')
            ->value('created_at');
        $cursor = $first ? Carbon::parse($first)->startOfMonth() : now()->copy()->startOfMonth();
        $end = now()->copy()->startOfMonth();
        $months = [];
        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return array_values(array_reverse($months));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function referralPipeline(Vendor $vendor): Collection
    {
        return AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->whereNotNull('customer_id')
            ->whereIn('event_type', ['registration', 'application', 'commission_application_fee', 'commission_kopafasta_plus', 'commission_registration_fee', 'commission_post_approval_fee'])
            ->with(['customer', 'loanApplication'])
            ->latest()
            ->limit(80)
            ->get()
            ->unique('customer_id')
            ->take(10)
            ->values()
            ->map(function (AffiliateEvent $event) use ($vendor): array {
                $customer = $event->customer;
                $commission = AffiliateEvent::query()
                    ->where('partner_id', $vendor->id)
                    ->where('customer_id', $event->customer_id)
                    ->where('event_type', 'like', 'commission_%')
                    ->latest('id')
                    ->first();
                $commercial = $this->commercialCommissionState($vendor, $commission);

                return [
                    'member_no' => MemberNumberFormatter::raw($customer?->member_no ?: $customer?->customer_number),
                    'stage' => $commercial['label'],
                    'status' => $commercial['key'],
                    'status_label' => $commercial['label'],
                    'source' => $this->referralSourceLabel($event, $customer),
                    'date' => $event->created_at,
                    'commission_amount' => $commission ? (float) $commission->commission_amount : null,
                    'commission_status' => $commercial['label'],
                ];
            });
    }

    /**
     * Overview earnings slice — commission-producing referral rows with commercial status only.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function recentEarnings(Vendor $vendor): Collection
    {
        return $this->referralPipeline($vendor)
            ->filter(fn (array $row) => (float) ($row['commission_amount'] ?? 0) > 0)
            ->take(5)
            ->values();
    }

    /**
     * Map existing PartnerPayment / commission lifecycle — never borrower credit outcomes.
     *
     * @return array{key: string, label: string}
     */
    private function commercialCommissionState(Vendor $vendor, ?AffiliateEvent $commission): array
    {
        if (! $commission) {
            return [
                'key' => 'pending',
                'label' => __('site.affiliate_portal.commission_status_pending'),
            ];
        }

        $payment = \App\Models\PartnerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', AffiliateCommissionWalletService::SOURCE_TYPE)
            ->where('source_id', $commission->id)
            ->latest('id')
            ->first();

        if (! $payment) {
            if ((float) $commission->commission_amount > 0) {
                return [
                    'key' => 'earned',
                    'label' => __('site.affiliate_portal.commission_status_earned'),
                ];
            }

            return [
                'key' => 'pending',
                'label' => __('site.affiliate_portal.commission_status_pending'),
            ];
        }

        $key = $this->commercialStatusKey((string) $payment->status);

        return [
            'key' => $key,
            'label' => __('site.affiliate_portal.commission_status_'.$key),
        ];
    }

    private function commercialStatusKey(string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'disputed' => 'disputed',
            'pending' => 'pending',
            'reserved' => 'reserved',
            'complete', 'approved', 'earned' => 'earned',
            'cancelled', 'reversed' => 'reversed',
            default => 'pending',
        };
    }

    private function referralSourceLabel(AffiliateEvent $event, ?Customer $customer): string
    {
        $claim = $customer ? app(AffiliateAttributionService::class)->customerClaim($customer) : null;
        $source = (string) ($claim['source'] ?? '');
        $landing = strtolower((string) ($event->landing_page ?? ''));

        if (app(AffiliateAttributionService::class)->isPromoSource($source)) {
            return __('site.affiliate_portal.source_promo');
        }

        if (app(AffiliateAttributionService::class)->isRelationshipSource($source)
            || str_contains($landing, '/aff/')
            || str_contains($landing, 'aff=')) {
            return __('site.affiliate_portal.source_link');
        }

        return $customer && ! $customer->affiliate_vendor_id
            ? __('site.affiliate_portal.source_promo')
            : __('site.affiliate_portal.source_link');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function recentReferrals(Vendor $vendor): Collection
    {
        return $this->referralPipeline($vendor)->take(5);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function walletActivity(Vendor $vendor): Collection
    {
        return $this->wallet->paginated($vendor, 5)->getCollection()->map(fn ($payment) => [
            'label' => $payment->description ?: __('site.affiliate_portal.commission_payment'),
            'amount' => (int) $payment->amount,
            'status' => __('site.affiliate_portal.'.$payment->status),
            'date' => $payment->created_at,
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function recentActivity(Vendor $vendor): Collection
    {
        return AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('event_type', [
                'registration',
                'application',
                'promo_code_changed',
                'commission_application_fee',
                'commission_registration_fee',
                'commission_post_approval_fee',
                'commission_kopafasta_plus',
            ])
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (AffiliateEvent $event): array {
                $label = match ($event->event_type) {
                    'registration' => __('site.affiliate_portal.activity_registered'),
                    'application' => __('site.affiliate_portal.activity_application'),
                    'promo_code_changed' => __('site.affiliate_portal.activity_promo_changed', ['code' => $event->referral_code]),
                    default => str_starts_with((string) $event->event_type, 'commission_')
                        ? __('site.affiliate_portal.activity_commission', ['amount' => format_money($event->commission_amount ?? 0)])
                        : ucfirst(str_replace('_', ' ', (string) $event->event_type)),
                };

                return [
                    'label' => $label,
                    'date' => $event->created_at,
                ];
            });
    }

    /** @return array<string, mixed> */
    private function earningsExplanation(Vendor $vendor): array
    {
        $country = $this->settings->assessmentCountry($vendor);
        $applies = collect($this->settings->appliesTo())
            ->filter()
            ->keys()
            ->filter(fn ($key) => $this->settings->benefitAppliesInTerritory((string) $key, $country))
            ->map(fn ($key) => __('site.affiliate_portal.fee_'.$key))
            ->values()
            ->all();

        $mode = $this->settings->commissionMode();

        return [
            'commission_mode' => $mode,
            'commission_mode_label' => __('site.affiliate_portal.commission_mode_'.$mode),
            'commission_percent' => $this->affiliates->commissionPercent($vendor),
            'qualifying_events' => $applies,
            'settlement' => __('site.affiliate_portal.earnings_settlement'),
            'minimum_withdrawal' => format_money($this->settings->minimumPayoutAmount()),
            'attribution_window' => $this->settings->attributionWindowDays(),
        ];
    }
}
