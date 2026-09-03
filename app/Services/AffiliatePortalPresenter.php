<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Vendor;
use App\Support\AffiliatePerformanceStatus;
use Illuminate\Support\Collection;

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
        $eligibility = $this->eligibility->for($vendor);
        $standing = $this->evaluation->currentStanding($vendor);
        $commercial = $this->membership->summary($vendor);
        $funnel = $this->referralFunnel($vendor);
        $progress = $this->assessmentProgress($vendor, $standing);
        $activity = $this->recentActivity($vendor);
        $earnings = $this->earningsExplanation($vendor);
        $attention = $this->needsAttention($vendor, $eligibility, $commercial);

        return [
            'vendor' => $vendor,
            'links' => $links,
            'share' => $this->affiliates->renderMessage($vendor, 'share_template'),
            'wallet' => $walletSummary,
            'available' => $available,
            'pending' => (int) ($walletSummary['pending'] ?? 0),
            'minPayout' => $this->settings->minimumPayoutAmount(),
            'eligibility' => $eligibility,
            'standing' => $standing,
            'commercial' => $commercial,
            'funnel' => $funnel,
            'progress' => $progress,
            'activity' => $activity,
            'earnings' => $earnings,
            'attention' => $attention,
            'hero' => $this->hero($vendor, $links, $available, $walletSummary, $standing, $commercial, $eligibility, $attention),
            'recentReferrals' => $this->recentReferrals($vendor),
            'walletActivity' => $this->walletActivity($vendor),
        ];
    }

    /** @return array<string, mixed> */
    public function share(Vendor $vendor): array
    {
        $this->affiliates->ensureCode($vendor);
        $links = $this->affiliates->messageContext($vendor);
        $locale = app()->getLocale();

        return [
            'vendor' => $vendor,
            'links' => $links,
            'shareMessage' => $this->affiliates->renderMessage($vendor, 'share_template'),
            'smsMessage' => $this->settings->message('referral_sms', $links, $locale),
            'attributionWindow' => $this->settings->attributionWindowDays(),
            'eligibility' => $this->eligibility->for($vendor),
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

        return [
            'vendor' => $vendor,
            'standing' => $standing,
            'progress' => $this->assessmentProgress($vendor, $standing),
            'warningLadder' => [
                ['label' => __('site.affiliate_portal.performance_needs_attention'), 'periods' => $this->settings->volumeMissesBeforeNudge()],
                ['label' => __('site.affiliate_portal.performance_at_risk'), 'periods' => $this->settings->volumeMissesBeforeWatchlist()],
                ['label' => __('site.affiliate_portal.performance_suspended'), 'periods' => $this->settings->volumeMissesBeforeSuspend()],
            ],
            'recovery' => ($settings['auto_recover'] ?? true)
                ? __('site.affiliate_portal.recovery_enabled')
                : __('site.affiliate_portal.recovery_disabled'),
            'rampUpDays' => $this->settings->volumeMinActiveDays(),
            'nextAssessment' => $standing['period_end'] ?? now()->endOfDay(),
        ];
    }

    /** @return array<string, mixed> */
    public function referrals(Vendor $vendor): array
    {
        return [
            'vendor' => $vendor,
            'funnel' => $this->referralFunnel($vendor),
            'pipeline' => $this->referralPipeline($vendor),
        ];
    }

    /** @return array<string, mixed> */
    public function wallet(Vendor $vendor): array
    {
        $summary = $this->wallet->summary($vendor);
        $available = $this->payouts->availableBalance($vendor, 'affiliate_commission');
        $approved = (int) ($summary['approved'] ?? 0);
        $paid = (int) ($summary['paid'] ?? 0);

        return [
            'vendor' => $vendor,
            'summary' => $summary,
            'payments' => $this->wallet->paginated($vendor),
            'available' => $available,
            'minPayout' => $this->settings->minimumPayoutAmount(),
            'totals' => [
                'available' => $available,
                'pending' => (int) ($summary['pending'] ?? 0),
                'earned' => $approved + $paid + (int) ($summary['pending'] ?? 0),
                'withdrawn' => $paid,
            ],
            'earnings' => $this->earningsExplanation($vendor),
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
        $greeting = __('site.affiliate_portal.greeting', [
            'name' => strtok($vendor->name, ' ') ?: $vendor->name,
        ]);
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
            'cta_label' => $attention['cta_label'] ?? ($eligibility['can_share'] ? __('site.affiliate_portal.nav_share') : null),
            'cta_url' => $attention['cta_url'] ?? ($eligibility['can_share'] ? route('site.affiliate.share') : null),
            'secondary_cta_label' => $eligibility['can_share'] ? __('site.affiliate_portal.nav_performance') : null,
            'secondary_cta_url' => $eligibility['can_share'] ? route('site.affiliate.performance') : null,
            'tertiary_cta_label' => ($available >= $this->settings->minimumPayoutAmount() && $eligibility['can_share'])
                ? __('site.affiliate_portal.request_payout')
                : null,
            'tertiary_cta_url' => ($available >= $this->settings->minimumPayoutAmount() && $eligibility['can_share'])
                ? route('site.affiliate.wallet').'#payout-form'
                : null,
            'compact_mobile' => true,
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
                'title' => __('site.affiliate_portal.attention_terms_title'),
                'body' => __('site.affiliate_portal.attention_terms_body'),
                'cta_label' => __('affiliate_terms.accept_button'),
                'cta_url' => route('site.affiliate.terms'),
            ];
        }
        if (in_array('kyc_unverified', $reasons, true)) {
            return [
                'title' => __('site.affiliate_portal.attention_kyc_title'),
                'body' => __('site.affiliate_portal.attention_kyc_body'),
                'cta_label' => __('site.affiliate_portal.complete_kyc'),
                'cta_url' => route('site.affiliate.profile', ['section' => 'face']),
            ];
        }
        if (in_array('agreement_inactive', $reasons, true) || in_array('membership_inactive', $reasons, true)) {
            if ($commercial['premium'] ?? false) {
                return [
                    'title' => __('site.affiliate_portal.attention_agreement_title'),
                    'body' => __('site.affiliate_portal.attention_agreement_body'),
                    'cta_label' => __('site.affiliate_portal.view_agreement'),
                    'cta_url' => route('site.affiliate.agreement'),
                ];
            }

            return [
                'title' => __('site.affiliate_portal.attention_membership_title'),
                'body' => __('site.affiliate_portal.attention_membership_body'),
                'cta_label' => __('site.affiliate_portal.membership_pay'),
                'cta_url' => route('site.affiliate.membership.pay'),
            ];
        }
        if (in_array('performance_suspended', $reasons, true)) {
            return [
                'title' => __('site.affiliate_portal.attention_performance_title'),
                'body' => __('site.affiliate_portal.attention_performance_body'),
                'cta_label' => __('site.affiliate_portal.nav_performance'),
                'cta_url' => route('site.affiliate.performance'),
            ];
        }

        return [
            'title' => __('site.affiliate_portal.attention_generic_title'),
            'body' => __('site.affiliate_portal.eligibility_blocked'),
            'cta_label' => __('site.affiliate_portal.nav_profile'),
            'cta_url' => route('site.affiliate.profile'),
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

    /** @return array<string, int> */
    private function referralFunnel(Vendor $vendor): array
    {
        $events = AffiliateEvent::query()->where('partner_id', $vendor->id);
        $customerIds = AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique();

        $approved = $customerIds->isEmpty() ? 0 : LoanApplication::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['approved', 'pre_approved', 'awaiting_offer', 'disbursed'])
            ->count();

        $disbursed = $customerIds->isEmpty() ? 0 : Loan::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotNull('disbursement_date')
            ->count();

        $commission = (clone $events)->where('event_type', 'like', 'commission_%')->count();

        return [
            'visited' => (clone $events)->where('event_type', 'click')->count(),
            'registered' => (clone $events)->where('event_type', 'registration')->count(),
            'applied' => (clone $events)->where('event_type', 'application')->count(),
            'approved' => $approved,
            'qualifying' => $disbursed,
            'commission' => $commission,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function referralPipeline(Vendor $vendor): Collection
    {
        return AffiliateEvent::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('event_type', ['registration', 'application'])
            ->with(['customer', 'loanApplication'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(function (AffiliateEvent $event): array {
                $customer = $event->customer;
                $name = $customer
                    ? trim(mb_substr((string) $customer->first_name, 0, 1).'. '.(string) $customer->last_name)
                    : __('site.affiliate_portal.anonymous_visitor');
                $stage = match ($event->event_type) {
                    'registration' => __('site.affiliate_portal.stage_registered'),
                    'application' => __('site.affiliate_portal.stage_applied'),
                    default => ucfirst(str_replace('_', ' ', (string) $event->event_type)),
                };

                return [
                    'name' => $name,
                    'stage' => $stage,
                    'date' => $event->created_at,
                ];
            });
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
        $applies = collect($this->settings->appliesTo())
            ->filter()
            ->keys()
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
