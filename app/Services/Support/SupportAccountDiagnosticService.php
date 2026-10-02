<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ApplicationBorrowerStatusService;
use App\Services\BorrowerApplicationsDashboardService;
use App\Services\PartnerWalletService;
use App\Services\Plus\PlusService;
use App\Services\ProfileCompletionService;

/**
 * Account-aware Support diagnostics — read layer over existing domain services.
 * Does not invent lending, payment, Plus, or Partner business logic.
 */
class SupportAccountDiagnosticService
{
    public function __construct(
        private readonly BorrowerApplicationsDashboardService $applications,
        private readonly ApplicationBorrowerStatusService $borrowerStatus,
        private readonly ProfileCompletionService $profile,
        private readonly PlusService $plus,
        private readonly PartnerWalletService $wallets,
    ) {}

    /**
     * @return array{
     *   found: bool,
     *   body: string,
     *   cta_url?: string|null,
     *   cta_label?: string|null,
     *   handover?: bool,
     *   context?: array<string, mixed>,
     *   choices?: list<array{action:string,key:string,label:string}>
     * }|null
     */
    public function diagnose(
        ?Customer $customer,
        ?User $user,
        string $audience,
        string $categoryKey,
        string $slug,
        ?string $locale = null,
        ?string $workspace = null,
        ?int $selectedApplicationId = null,
    ): ?array {
        $isSw = $this->isSw($locale);

        if ($audience === 'member' && $customer) {
            return $this->diagnoseMember($customer, $categoryKey, $slug, $isSw, $selectedApplicationId);
        }

        if ($audience === 'partner' && $user) {
            $partner = $user->partner ?? null;
            if ($partner instanceof Vendor) {
                return $this->diagnosePartner($partner, $categoryKey, $slug, $isSw, $workspace);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function diagnoseMember(
        Customer $customer,
        string $categoryKey,
        string $slug,
        bool $isSw,
        ?int $selectedApplicationId,
    ): ?array {
        $loanish = in_array($categoryKey, ['apply-loan', 'fees-payments', 'repayments', 'guarantors', 'collateral', 'group-loans'], true)
            || in_array($slug, [
                'application-stage', 'application-rejected', 'screening-meaning',
                'upload-documents', 'offer-accept', 'post-approval-fees',
                'next-repayment', 'how-to-repay', 'payment-failed', 'payment-pending',
                'guarantor-invite', 'guarantor-pending',
            ], true);

        if ($loanish) {
            return $this->memberLoanDiagnostic($customer, $slug, $isSw, $selectedApplicationId);
        }

        if ($categoryKey === 'plus' || str_starts_with($slug, 'plus') || in_array($slug, ['what-is-plus', 'join-plus'], true)) {
            return $this->memberPlusDiagnostic($customer, $isSw);
        }

        if (in_array($categoryKey, ['profile', 'account', 'registration'], true)
            || str_contains($slug, 'profile')
            || str_contains($slug, 'kyc')) {
            return $this->memberProfileDiagnostic($customer, $isSw);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function memberLoanDiagnostic(Customer $customer, string $slug, bool $isSw, ?int $selectedApplicationId): array
    {
        $apps = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->with(['product', 'loan'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        if ($apps->isEmpty()) {
            $rows = $this->applications->applicationsForCustomer($customer);
            if ($rows === []) {
                return [
                    'found' => true,
                    'body' => $this->compose(
                        $isSw
                            ? 'Sijaona ombi au mkopo wowote kwenye akaunti yako bado.'
                            : 'I do not see any application or loan on your account yet.',
                        $isSw
                            ? 'Unaweza kuanza ombi jipya kutoka Bidhaa / Mikopo.'
                            : 'You can start a new application from Products / Loans.',
                        $isSw
                            ? 'Chagua bidhaa na uanze ombi.'
                            : 'Choose a product and start an application.',
                        $isSw,
                    ),
                    'cta_url' => route('site.borrower.loans'),
                    'cta_label' => $isSw ? 'Fungua Mikopo' : 'Open Loans',
                    'context' => ['kind' => 'member_no_loans'],
                ];
            }
        }

        if ($selectedApplicationId) {
            $selected = $apps->firstWhere('id', $selectedApplicationId);
            if ($selected) {
                return $this->explainApplication($selected, $slug, $isSw);
            }
        }

        $active = $apps->reject(fn (LoanApplication $a) => in_array((string) $a->status, [
            'withdrawn', 'offer_declined', 'closed', 'disbursed_closed',
        ], true))->values();

        if ($active->count() > 1 && ! $selectedApplicationId) {
            $choices = $active->take(5)->map(function (LoanApplication $app) use ($isSw) {
                $status = $this->borrowerStatus->forApplication($app);
                $amount = (int) ($app->requested_amount ?? $app->recommended_amount ?? 0);
                $label = trim(implode(' · ', array_filter([
                    $app->application_number ?: ('#'.$app->id),
                    $app->product?->name,
                    $amount > 0 ? format_money($amount) : null,
                    $status['label'] ?? $app->status,
                ])));

                return [
                    'action' => 'pick_record',
                    'key' => 'application:'.$app->id,
                    'label' => $label !== '' ? $label : ($isSw ? 'Ombi' : 'Application'),
                ];
            })->all();

            return [
                'found' => true,
                'body' => $isSw
                    ? "Nimeona maombi/mikopo kadhaa kwenye akaunti yako.\n\nJe, ni lipi unahitaji msaada nalo?"
                    : "I found several applications/loans on your account.\n\nWhich one do you need help with?",
                'choices' => $choices,
                'context' => ['kind' => 'member_pick_loan', 'pending_slug' => $slug],
            ];
        }

        $target = $active->first() ?? $apps->first();
        if (! $target) {
            return [
                'found' => true,
                'body' => $isSw
                    ? 'Sijaweza kubaini ombi maalum. Fungua Mikopo au Ongea na Usaidizi.'
                    : 'I could not identify a specific application. Open Loans or Talk to Support.',
                'cta_url' => route('site.borrower.loans'),
                'cta_label' => $isSw ? 'Fungua Mikopo' : 'Open Loans',
                'handover' => true,
                'context' => ['kind' => 'member_loan_gap'],
            ];
        }

        return $this->explainApplication($target, $slug, $isSw);
    }

    /**
     * @return array<string, mixed>
     */
    private function explainApplication(LoanApplication $application, string $slug, bool $isSw): array
    {
        $application->loadMissing(['product', 'loan', 'documentRequests']);
        $status = $this->borrowerStatus->forApplication($application);
        $code = (string) ($status['code'] ?? $application->status);
        $label = (string) ($status['label'] ?? $application->status);
        $ref = $application->application_number ?: ('#'.$application->id);
        $product = $application->product?->localizedName() ?: $application->product?->name;
        $amount = (int) ($application->requested_amount ?? $application->recommended_amount ?? 0);
        $amountLabel = $amount > 0 ? format_money($amount) : null;

        $found = trim(implode(' · ', array_filter([$ref, $product, $amountLabel, $label])));

        $customer = $application->customer;
        $missingProfile = [];
        if ($customer) {
            $sections = $this->profile->displaySections($customer, true);
            $missingProfile = collect($sections)
                ->filter(fn (array $s) => ($s['status'] ?? '') !== 'complete')
                ->pluck('label')
                ->filter()
                ->take(6)
                ->values()
                ->all();
        }

        $ctaUrl = route('site.borrower.application', $application);
        $ctaLabel = $isSw ? 'Fungua Ombi' : 'Open application';
        $handover = false;
        $means = '';
        $next = '';
        $preSubmit = in_array((string) $application->status, LoanApplication::PRE_SUBMIT_STATUSES, true);

        if ($code === 'rejected' || (string) $application->status === 'rejected') {
            $reason = $this->customerFacingRejection($application, $isSw);
            $means = $reason ?: ($isSw
                ? 'Ombi hili limekataliwa kulingana na uamuzi uliorekodiwa.'
                : 'This application was rejected per the recorded decision.');
            $next = $isSw
                ? 'Soma sababu ya mteja na barua ya uamuzi ikiwa ipo.'
                : 'Read the customer-facing reason and the decision letter if available.';
            $letter = $this->rejectionLetterUrl($application);
            if ($letter) {
                $ctaUrl = $letter;
                $ctaLabel = $isSw ? 'Angalia barua ya uamuzi' : 'View rejection letter';
            }
        } elseif (in_array($code, ['documents_requested', 'pending_documents'], true)
            || (string) $application->status === 'pending_documents') {
            $pending = $application->documentRequests
                ->whereIn('status', ['pending', 'rejected'])
                ->pluck('label')
                ->filter()
                ->values();
            $means = $isSw
                ? 'Ombi linasubiri taarifa/nyaraka kutoka kwako.'
                : 'The application is waiting for information/documents from you.';
            $next = $pending->isNotEmpty()
                ? ($isSw
                    ? 'Inayohitajika: '.$pending->implode(', ').'.'
                    : 'Required: '.$pending->implode(', ').'.')
                : ($isSw ? 'Fungua ombi kuona kilichohitajika.' : 'Open the application to see what is required.');
        } elseif ($missingProfile !== [] && ($preSubmit || in_array($code, ['draft', 'awaiting_profile', 'incomplete'], true))) {
            $means = $isSw
                ? 'Ombi linasubiri ukamilishe wasifu wako.'
                : 'Your application is waiting for you to complete your profile.';
            $next = $isSw
                ? 'Vipengele vinavyokosekana: '.implode(' · ', $missingProfile).'.'
                : 'Missing sections: '.implode(' · ', $missingProfile).'.';
            $ctaUrl = route('site.borrower.profile');
            $ctaLabel = $isSw ? 'Endelea wasifu' : 'Continue profile';
        } elseif (in_array($code, ['under_review', 'submitted', 'screening', 'credit_appraisal'], true)
            || in_array((string) $application->current_stage, ['screening', 'credit_appraisal', 'committee', 'management'], true)
            || in_array((string) $application->status, ['submitted', 'under_review'], true)) {
            $means = $isSw
                ? 'Ombi lako linakaguliwa sasa. Hakuna unachohitajika kufanya kwa sasa. Tutawasiliana nawe tukihitaji chochote.'
                : 'Your application is currently under review. There is nothing you need to do right now. We will contact you if we need anything else.';
            $next = $isSw ? 'Unaweza kufuatilia maendeleo kwenye Ombi.' : 'You can track progress on the application.';
        } elseif (in_array($code, ['awaiting_guarantor', 'guarantor_pending'], true)
            || (string) $application->status === 'awaiting_guarantor') {
            $means = $isSw
                ? 'Ombi linasubiri hatua ya mdhamini.'
                : 'The application is waiting on a guarantor step.';
            $next = $isSw
                ? 'Fungua sehemu ya Wadhamini kwenye ombi — bila kufichua taarifa binafsi za mdhamini.'
                : 'Open Guarantors on the application — without exposing private guarantor details.';
        } elseif (in_array($code, ['offer_ready', 'awaiting_offer', 'offer_pending'], true)
            || $application->offer_status === 'pending_borrower') {
            $means = $isSw
                ? 'Ofa iko tayari kwa ukaguzi wako.'
                : 'An offer is ready for your review.';
            $next = $isSw ? 'Fungua ofa ili ukubali au ukatae.' : 'Open the offer to accept or decline.';
            $ctaUrl = route('site.borrower.application.offer', $application);
            $ctaLabel = $isSw ? 'Angalia ofa' : 'View offer';
        } elseif ($application->loan instanceof Loan) {
            $loan = $application->loan;
            $means = $isSw
                ? 'Mkopo umeanzishwa. Hali: '.str_replace('_', ' ', (string) $loan->status).'.'
                : 'A loan is active. Status: '.str_replace('_', ' ', (string) $loan->status).'.';
            $next = $isSw
                ? 'Kwa malipo, tumia skrini za Malipo zilizoidhinishwa — Usaidizi hauthibitishi fedha peke yake.'
                : 'For payments, use the approved Payments screens — Support never independently confirms money received.';
            $ctaUrl = route('site.borrower.loans');
            $ctaLabel = $isSw ? 'Fungua Mikopo' : 'Open Loans';
        } else {
            $means = $isSw
                ? 'Hali ya sasa: '.$label.'.'
                : 'Current status: '.$label.'.';
            $next = $isSw
                ? 'Fungua ombi kwa maelezo kamili, au Ongea na Usaidizi ikiwa hali haiko wazi.'
                : 'Open the application for full detail, or Talk to Support if the state is unclear.';
            $handover = in_array($code, ['unknown', ''], true);
        }

        if (in_array($slug, ['payment-failed', 'payment-pending', 'next-repayment', 'how-to-repay'], true)) {
            $means .= $isSw
                ? "\n\nKwa hali ya malipo, tumia Malipo / risiti kwenye akaunti — Usaidizi hausemi fedha zimepokelewa mpaka mfumo uthibitishe."
                : "\n\nFor payment state, use Payments / receipts in your account — Support never claims money was received until the system confirms it.";
            $ctaUrl = route('site.borrower.payments');
            $ctaLabel = $isSw ? 'Fungua Malipo' : 'Open Payments';
        }

        return [
            'found' => true,
            'body' => $this->compose($found, $means, $next, $isSw),
            'cta_url' => $ctaUrl,
            'cta_label' => $ctaLabel,
            'handover' => $handover,
            'context' => [
                'kind' => 'member_application',
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'status_code' => $code,
                'slug' => $slug,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPlusDiagnostic(Customer $customer, bool $isSw): array
    {
        $active = $this->plus->isActive($customer);
        $current = $this->plus->current($customer);

        if ($active && $current) {
            $expires = $current->expires_at?->format('d M Y') ?? '—';

            return [
                'found' => true,
                'body' => $this->compose(
                    $isSw ? 'Kopafasta Plus: inatumika.' : 'Kopafasta Plus: active.',
                    $isSw
                        ? "Uanachama wako wa Plus uko hai. Unaisha {$expires}."
                        : "Your Plus membership is active. It expires {$expires}.",
                    $isSw
                        ? 'Fungua Plus kwa biashara, malengo, ripoti na Learn.'
                        : 'Open Plus for businesses, goals, reports and Learn.',
                    $isSw,
                ),
                'cta_url' => route('site.borrower.plus.home'),
                'cta_label' => 'Kopafasta Plus',
                'context' => ['kind' => 'plus_active', 'expires_at' => $expires],
            ];
        }

        return [
            'found' => true,
            'body' => $this->compose(
                $isSw ? 'Kopafasta Plus: haijaamilishwa.' : 'Kopafasta Plus: not active.',
                $isSw
                    ? 'Akaunti yako haina Plus hai kwa sasa.'
                    : 'Your account does not have an active Plus membership right now.',
                $isSw
                    ? 'Fungua Plus kuona njia ya kuamilisha inayopatikana.'
                    : 'Open Plus to see the available activation journey.',
                $isSw,
            ),
            'cta_url' => route('site.borrower.plus.home'),
            'cta_label' => $isSw ? 'Fungua Plus' : 'Open Plus',
            'context' => ['kind' => 'plus_inactive'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberProfileDiagnostic(Customer $customer, bool $isSw): array
    {
        $sections = $this->profile->displaySections($customer, true);
        $missing = collect($sections)
            ->filter(fn (array $s) => ($s['status'] ?? '') !== 'complete')
            ->pluck('label')
            ->filter()
            ->take(8)
            ->values()
            ->all();

        if ($missing === []) {
            return [
                'found' => true,
                'body' => $this->compose(
                    $isSw ? 'Wasifu: umekamilika kwa sehemu zinazohitajika.' : 'Profile: required sections look complete.',
                    $isSw ? 'Sijaona sehemu muhimu inayokosekana sasa.' : 'I do not see a required section missing right now.',
                    $isSw ? 'Hakuna unachohitajika kufanya kwenye wasifu kwa sasa.' : 'Nothing is required on your profile right now.',
                    $isSw,
                ),
                'cta_url' => route('site.borrower.profile'),
                'cta_label' => $isSw ? 'Fungua Wasifu' : 'Open Profile',
                'context' => ['kind' => 'profile_complete'],
            ];
        }

        return [
            'found' => true,
            'body' => $this->compose(
                $isSw ? 'Wasifu: baadhi ya sehemu bado hazijakamilika.' : 'Profile: some sections are still incomplete.',
                $isSw
                    ? 'Vinavyokosekana: '.implode(' · ', $missing).'.'
                    : 'Missing: '.implode(' · ', $missing).'.',
                $isSw ? 'Endelea wasifu kukamilisha sehemu hizo.' : 'Continue profile to complete those sections.',
                $isSw,
            ),
            'cta_url' => route('site.borrower.profile'),
            'cta_label' => $isSw ? 'Endelea wasifu' : 'Continue profile',
            'context' => ['kind' => 'profile_incomplete', 'missing' => $missing],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function diagnosePartner(
        Vendor $partner,
        string $categoryKey,
        string $slug,
        bool $isSw,
        ?string $workspace,
    ): ?array {
        $walletish = str_contains($slug, 'wallet')
            || str_contains($slug, 'withdrawal')
            || str_contains($slug, 'earnings')
            || str_contains($slug, 'commission')
            || str_contains($categoryKey, 'affiliate')
            || str_contains($categoryKey, 'wallet')
            || $workspace === 'affiliate';

        if (! $walletish) {
            return null;
        }

        try {
            $summary = $this->wallets->summary($partner);
        } catch (\Throwable) {
            return [
                'found' => true,
                'body' => $isSw
                    ? "Sijaweza kusoma pochi yako sasa.\n\nOngea na Usaidizi — tutaendelea na muktadha wa akaunti yako."
                    : "I could not read your wallet right now.\n\nTalk to Support — we will continue with your account context.",
                'handover' => true,
                'context' => ['kind' => 'partner_wallet_gap', 'vendor_id' => $partner->id],
            ];
        }

        $available = format_money((float) ($summary['available'] ?? 0));
        $pending = format_money((float) ($summary['pending'] ?? 0));
        $tier = '';
        if (method_exists($partner, 'isPremiumAffiliate') && method_exists($partner, 'isAffiliate') && $partner->isAffiliate()) {
            $tier = $partner->isPremiumAffiliate() ? 'Premium' : 'Standard';
        }

        $found = $isSw
            ? 'Pochi'.($tier !== '' ? " ({$tier})" : '').": inayopatikana {$available}; inayosubiri {$pending}."
            : 'Wallet'.($tier !== '' ? " ({$tier})" : '').": available {$available}; pending {$pending}.";

        return [
            'found' => true,
            'body' => $this->compose(
                $found,
                $isSw
                    ? 'Hizi ni salio kutoka injini ya pochi ya Mshirika — si hesabu ya chatbot.'
                    : 'These balances come from the Partner wallet engine — not a chatbot calculation.',
                $isSw
                    ? 'Fungua pochi ya nafasi yako kwa historia ya malipo/utoaji.'
                    : 'Open your workspace wallet for payment/withdrawal history.',
                $isSw,
            ),
            'cta_url' => \Illuminate\Support\Facades\Route::has('site.affiliate.wallet')
                ? route('site.affiliate.wallet')
                : (\Illuminate\Support\Facades\Route::has('site.partner.wallet') ? route('site.partner.wallet') : null),
            'cta_label' => $isSw ? 'Fungua pochi' : 'Open wallet',
            'context' => [
                'kind' => 'partner_wallet',
                'vendor_id' => $partner->id,
                'available' => $summary['available'] ?? null,
                'pending' => $summary['pending'] ?? null,
                'tier' => $tier !== '' ? $tier : null,
            ],
        ];
    }

    private function customerFacingRejection(LoanApplication $application, bool $isSw): ?string
    {
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $reason = (string) (
            data_get($payload, 'rejection.customer_reason')
            ?? data_get($payload, 'rejection_reason_customer')
            ?? data_get($payload, 'customer_rejection_reason')
            ?? $application->rejection_reason
            ?? ''
        );
        $reason = trim($reason);
        if ($reason === '') {
            return null;
        }

        // Never echo internal-looking keys.
        if (preg_match('/^[A-Z0-9_]{6,}$/', $reason)) {
            return $isSw
                ? 'Sababu ya mteja inapatikana kwenye barua/uamuzi wa ombi.'
                : 'The customer-facing reason is available on the application decision letter.';
        }

        return $reason;
    }

    private function rejectionLetterUrl(LoanApplication $application): ?string
    {
        try {
            $exists = \App\Models\LoanAgreement::query()
                ->where('loan_application_id', $application->id)
                ->where('document_type', 'rejection_letter')
                ->exists();
            if ($exists && \Illuminate\Support\Facades\Route::has('site.borrower.application.rejection-letter')) {
                return route('site.borrower.application.rejection-letter', $application);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function compose(string $found, string $means, string $next, bool $isSw): string
    {
        $h1 = $isSw ? 'Nimegundua' : 'What I found';
        $h2 = $isSw ? 'Maana yake' : 'What this means';
        $h3 = $isSw ? 'Unachohitajika kufanya' : 'What you need to do';

        return "{$h1}\n{$found}\n\n{$h2}\n{$means}\n\n{$h3}\n{$next}";
    }

    private function isSw(?string $locale): bool
    {
        return str_starts_with(strtolower((string) ($locale ?: app()->getLocale())), 'sw');
    }

    /**
     * Staff Status/Diagnostic template — read-only Support 360 text for composer preview.
     * Never returns models/arrays/JSON; always customer-safe prose via the same diagnose path.
     */
    public function staffDiagnosticDraft(
        ?Customer $customer,
        ?User $user,
        string $categoryKey,
        string $slug,
        ?string $locale = null,
        ?string $workspace = null,
    ): string {
        $locale = $locale ?: app()->getLocale();
        $audience = $customer ? 'member' : ($user ? 'partner' : 'guest');
        if ($audience === 'guest') {
            return $this->isSw($locale)
                ? 'Hii ni mazungumzo ya mgeni — diagnostiki za akaunti zinahitaji Mwanachama/Mshirika aliyeingia.'
                : 'This is a guest conversation — account diagnostics require an authenticated Member/Partner.';
        }

        try {
            $result = $this->diagnose(
                $customer,
                $user,
                $audience,
                $categoryKey,
                $slug,
                $locale,
                $workspace,
                null,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('support.diagnostic.service_failed', [
                'audience' => $audience,
                'category' => $categoryKey,
                'slug' => $slug,
                'customer_id' => $customer?->id,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->isSw($locale)
                ? 'Sijaweza kuandaa muhtasari wa diagnostiki sasa. Angalia Member/Partner 360 kwa maelezo.'
                : 'I could not prepare a diagnostic summary right now. Check Member/Partner 360 for details.';
        }

        // null = no diagnostic applies for this audience/template — honest no-data, not a failure.
        if ($result === null) {
            $walletish = str_contains($slug, 'wallet')
                || str_contains($slug, 'earnings')
                || str_contains($slug, 'commission')
                || str_contains($categoryKey, 'affiliate')
                || str_contains($categoryKey, 'wallet');

            if ($walletish && $audience === 'member') {
                return $this->isSw($locale)
                    ? 'Akaunti hii ni ya Mwanachama — hakuna pochi/mapato ya Mshirika (Affiliate/Supplier) yaliyopatikana hapa. Kwa malipo ya mkopo, tumia kiolezo cha Hali ya malipo au Fungua Malipo kwenye akaunti.'
                    : 'This is a Member account — no Partner wallet/earnings (Affiliate/Supplier) apply here. For loan payments, use the Repayment status template or Open Payments in the account.';
            }

            if ($walletish && $audience === 'partner') {
                return $this->isSw($locale)
                    ? 'Hakuna salio au mapato yaliyopatikana kwenye akaunti hii kwa sasa.'
                    : 'No wallet balance or earnings were found on this account right now.';
            }

            return $this->isSw($locale)
                ? 'Hakuna taarifa za diagnostiki zinazotumika kwa kiolezo hiki kwenye akaunti hii kwa sasa.'
                : 'No diagnostic information applies for this template on this account right now.';
        }

        $body = is_array($result) ? ($result['body'] ?? null) : null;
        $safe = app(SupportConversationService::class)->safeChatText($body, $locale);
        $fallback = app(SupportConversationService::class)->unsafePayloadFallback($locale);
        if ($safe === '' || $safe === $fallback) {
            \Illuminate\Support\Facades\Log::warning('support.diagnostic.draft_failed', [
                'audience' => $audience,
                'category' => $categoryKey,
                'slug' => $slug,
                'customer_id' => $customer?->id,
                'user_id' => $user?->id,
            ]);

            return $this->isSw($locale)
                ? 'Sijaweza kuandaa muhtasari wa diagnostiki sasa. Angalia Member/Partner 360 kwa maelezo.'
                : 'I could not prepare a diagnostic summary right now. Check Member/Partner 360 for details.';
        }

        return $safe;
    }
}
