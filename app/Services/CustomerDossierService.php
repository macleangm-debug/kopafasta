<?php

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\LoanApplicationDraft;

class CustomerDossierService
{
    public function __construct(
        private readonly ProfileCompletionService $profile,
        private readonly ApplicationRequirementsService $requirements,
        private readonly NidaVerificationService $nida,
        private readonly FaceVerificationService $face,
        private readonly MemberEngagementService $engagement,
        private readonly CrbCreditCheckService $crb,
        private readonly CrbFreshnessService $crbFreshness,
    ) {}

    /** @return array<string, mixed> */
    public function dossier(Customer $customer, ?string $paymentsQuery = null): array
    {
        $customer->loadMissing([
            'branch',
            'kyc',
            'documents.documentType',
            'documents.verifier',
            'applications.product',
            'applications.loan',
            'applications.customerGuarantors.guarantor',
            'applications.customerGuarantors.invitation.guarantorCustomer',
            'loans.product',
            'loans.application.product',
            'payments.loan',
            'assets',
            'notificationLogs',
            'guarantorInvitations.application',
        ]);

        $profile = $this->profile->calculate($customer);
        $eligibility = $this->requirements->checklist($customer);
        $faceProgress = $this->face->progress($customer);
        $engagement = $this->engagement->summary($customer);
        $tabs = $this->profile->tabStatuses($customer);
        $paymentAccounts = app(CustomerDisbursementDetailsService::class)->accountsForCustomer($customer);
        $paymentSnapshot = app(CustomerDisbursementDetailsService::class)->snapshotFromCustomer($customer);

        $documents = $customer->documents->sortByDesc('created_at')->values();
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();
        $documentsByContext = $this->documentsByContext($documents);

        $layperson = [
            'personal' => __('borrower.profile.hub.layperson.about_you'),
            'residence' => __('borrower.profile.hub.layperson.where_you_live'),
            'activity' => __('borrower.profile.hub.layperson.work_money'),
            'payment' => __('borrower.profile.hub.layperson.payment_accounts'),
            'assets' => __('borrower.profile.hub.layperson.your_assets'),
        ];

        $checklist = collect($profile['sections'])->map(function (array $section) use ($customer, $layperson) {
            $key = (string) ($section['key'] ?? '');
            $complete = (bool) ($section['complete'] ?? false);
            $gaps = $complete ? [] : $this->profile->sectionGaps($customer, $key);
            $gapLabels = collect($gaps)->pluck('label')->filter()->take(4)->values()->all();

            return [
                'key'    => $key,
                'label'  => $layperson[$key] ?? $section['label'],
                'detail' => $complete
                    ? 'Complete'
                    : ($gapLabels !== [] ? implode(', ', $gapLabels) : 'Incomplete'),
                'tone'   => $complete ? 'emerald' : 'amber',
                'gaps'   => $gapLabels,
            ];
        })->values()->all();

        $eligibilityItems = collect($eligibility['items'] ?? [])->map(function (array $item) use ($layperson) {
            $key = (string) ($item['key'] ?? '');
            $label = match ($key) {
                'personal', 'nida', 'face_submitted', 'face_approval', 'kin', 'legal_signature' => $layperson['personal'],
                'residence' => $layperson['residence'],
                'activity', 'income_proof', 'kyc_freshness' => $layperson['activity'],
                'payment' => $layperson['payment'],
                default => (string) ($item['label'] ?? $key),
            };

            return $item + [
                'category_key' => match ($key) {
                    'personal', 'nida', 'face_submitted', 'face_approval', 'kin', 'legal_signature' => 'personal',
                    'residence' => 'residence',
                    'activity', 'income_proof', 'kyc_freshness' => 'activity',
                    'payment' => 'payment',
                    default => $key,
                },
                'category_label' => $label,
            ];
        })->values()->all();

        $eligibilityByCategory = collect($eligibilityItems)
            ->groupBy('category_key')
            ->map(function ($items, $key) use ($layperson) {
                $complete = $items->every(fn (array $i) => (bool) ($i['complete'] ?? false));
                $gaps = $items->reject(fn (array $i) => (bool) ($i['complete'] ?? false))
                    ->pluck('detail')
                    ->filter()
                    ->take(3)
                    ->values()
                    ->all();

                return [
                    'key' => $key,
                    'label' => $layperson[$key] ?? ($items->first()['category_label'] ?? $key),
                    'complete' => $complete,
                    'detail' => $complete ? 'Ready' : ($gaps[0] ?? 'Needs attention'),
                    'gaps' => $gaps,
                ];
            })
            ->values()
            ->all();

        // Membership is not part of canonical Profile readiness.
        $membershipNote = [
            'key'    => 'membership',
            'label'  => 'Membership',
            'detail' => $customer->isMembershipActive() || $customer->isMembershipInGrace()
                ? 'Active'
                : 'Inactive or expired',
            'tone'   => ($customer->isMembershipActive() || $customer->isMembershipInGrace()) ? 'emerald' : 'amber',
            'profile_readiness' => false,
        ];

        $incompleteSections = collect($profile['sections'] ?? [])
            ->reject(fn (array $s) => (bool) ($s['complete'] ?? false))
            ->map(function (array $section) use ($customer) {
                $gaps = $this->profile->sectionGaps($customer, (string) ($section['key'] ?? ''));
                $section['gap_labels'] = collect($gaps)->pluck('label')->filter()->take(6)->values()->all();

                return $section;
            })
            ->values()
            ->all();

        $hasApplications = $customer->applications->isNotEmpty();
        $crbHistory = $hasApplications ? $this->crb->latest($customer) : null;
        $crbFresh = $crbHistory ? $this->crbFreshness->isFresh($crbHistory) : false;

        $facePhotoUrl = $this->face->avatarUrl($customer);

        $idDoc = $documents->first(function ($doc) {
            $code = (string) ($doc->documentType?->code ?? '');

            return in_array($code, ['national_id_front', 'passport', 'voter_id', 'driving_license'], true);
        });

        $currentApplication = $customer->applications
            ->sortByDesc(fn ($a) => $a->submitted_at ?? $a->created_at)
            ->first(fn ($a) => ! in_array((string) $a->status, ['rejected', 'expired', 'withdrawn', 'cancelled', 'closed', 'completed', 'settled'], true));

        $latestPayment = $customer->payments()->with(['loan', 'loanProduct', 'source'])->latest()->first();

        return [
            'customer'       => $customer,
            'profile'          => $profile,
            'eligibility'      => $eligibility + [
                'items' => $eligibilityItems,
                'by_category' => $eligibilityByCategory,
            ],
            'engagement'       => $engagement,
            'checklist'        => $checklist,
            'profile_tabs'     => $tabs,
            'membership_note'  => $membershipNote,
            'incomplete_sections' => $incompleteSections,
            'profile_incomplete'  => (int) ($profile['percent'] ?? 0) < 100,
            'face_progress'    => $faceProgress,
            'face_photo_url'   => $facePhotoUrl,
            'id_photo_url'     => $idDoc?->file_path ? asset('storage/'.$idDoc->file_path) : null,
            'nida_verified'    => $this->nida->isVerified($customer),
            'face_verified'    => $this->face->isVerified($customer),
            'documents'        => $documents,
            'documents_by_context' => $documentsByContext,
            'document_types'   => $documentTypes,
            'payment_accounts' => $paymentAccounts,
            'payment_snapshot' => $paymentSnapshot,
            'payment_complete' => app(CustomerDisbursementDetailsService::class)->isComplete($customer),
            'has_legal_signature' => app(BorrowerSignatureService::class)->hasProfileSignature($customer),
            'assets'           => $customer->assets->sortByDesc('updated_at')->values(),
            'asset_count'      => $customer->assets->count(),
            'applications'          => $customer->applications->sortByDesc('created_at')->values(),
            'application_drafts'  => LoanApplicationDraft::query()
                ->where('customer_id', $customer->id)
                ->whereIn('phase', ['details', 'application'])
                ->with('product')
                ->orderByDesc('saved_at')
                ->get(),
            'current_application' => $currentApplication,
            'loans'            => $customer->loans->sortByDesc('created_at')->values(),
            'payments'         => $this->memberPayments($customer, $paymentsQuery),
            'payments_query'   => $paymentsQuery,
            'latest_payment'   => $latestPayment,
            'notifications'    => $customer->notificationLogs()->latest()->limit(20)->get(),
            'guarantor_invitations' => $customer->guarantorInvitations()
                ->with(['application.product', 'guarantorCustomer', 'customerGuarantor.guarantor'])
                ->latest()
                ->limit(20)
                ->get(),
            'activity_label'   => activity_type_label($customer->activity_type) ?? $customer->activity_type,
            'income_label'     => income_range_label($customer->income_range) ?? $customer->income_range,
            'activity_types'   => activity_type_options(),
            'income_ranges'    => income_range_options(),
            'pending_documents'=> $documents->whereIn('status', ['pending', 'pending_review'])->count(),
            'has_applications' => $hasApplications,
            'crb'              => $this->crbSnapshot($crbHistory, $crbFresh, $hasApplications),
            'repayment_standing' => $this->repaymentStanding($engagement),
            'read_only'        => true,
        ];
    }

    /**
     * Member-scoped payment list with optional Support search (reference / app / loan / type / amount / status).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\CustomerPayment>
     */
    private function memberPayments(Customer $customer, ?string $paymentsQuery)
    {
        $query = $customer->payments()->with(['loan', 'loanProduct', 'source'])->latest();
        $q = trim((string) $paymentsQuery);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $amount = is_numeric(str_replace([',', ' '], '', $q))
                ? (float) str_replace([',', ' '], '', $q)
                : null;
            $query->where(function ($builder) use ($like, $amount) {
                $builder->where('reference', 'like', $like)
                    ->orWhere('payment_type', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('amount', 'like', $like)
                    ->orWhereHas('loan', function ($loan) use ($like) {
                        $loan->where('loan_number', 'like', $like)
                            ->orWhere('reference', 'like', $like);
                    })
                    ->orWhereHasMorph('source', [\App\Models\LoanApplication::class], function ($app) use ($like) {
                        $app->where('application_number', 'like', $like)
                            ->orWhere('reference', 'like', $like);
                    });
                if ($amount !== null) {
                    $builder->orWhere('amount', $amount);
                }
            });
        }

        return $query->limit(40)->get();
    }

    /**
     * Context buckets for Profile IA — documents appear where they belong, not as a generic Documents tab.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $documents
     * @return array<string, \Illuminate\Support\Collection<int, mixed>>
     */
    private function documentsByContext($documents): array
    {
        $bucket = fn (callable $match) => $documents->filter($match)->values();

        return [
            'identity' => $bucket(function ($doc) {
                $code = strtolower((string) ($doc->documentType?->code ?? ''));
                $cat = strtolower((string) ($doc->documentType?->category ?? ''));

                return str_contains($code, 'national_id')
                    || str_contains($code, 'nida')
                    || in_array($code, ['passport', 'voter_id', 'driving_license'], true)
                    || $cat === 'identity';
            }),
            'residence' => $bucket(function ($doc) {
                $code = strtolower((string) ($doc->documentType?->code ?? ''));
                $name = strtolower((string) ($doc->documentType?->name ?? ''));

                return str_contains($code, 'residence')
                    || str_contains($code, 'utility')
                    || str_contains($name, 'residence')
                    || str_contains($name, 'proof of address');
            }),
            'activity' => $bucket(function ($doc) {
                $code = strtolower((string) ($doc->documentType?->code ?? ''));
                $name = strtolower((string) ($doc->documentType?->name ?? ''));
                $cat = strtolower((string) ($doc->documentType?->category ?? ''));

                return str_contains($code, 'income')
                    || str_contains($code, 'business')
                    || str_contains($code, 'employment')
                    || str_contains($name, 'income')
                    || in_array($cat, ['income', 'activity', 'employment'], true);
            }),
            'collateral' => $bucket(function ($doc) {
                $code = strtolower((string) ($doc->documentType?->code ?? ''));
                $cat = strtolower((string) ($doc->documentType?->category ?? ''));

                return str_contains($code, 'collateral')
                    || str_contains($code, 'ownership')
                    || str_contains($code, 'valuation')
                    || str_contains($code, 'insurance')
                    || in_array($cat, ['collateral', 'asset'], true);
            }),
        ];
    }

    /** @return array<string, mixed> */
    private function crbSnapshot(?CreditHistory $history, bool $fresh, bool $hasApplications): array
    {
        if (! $hasApplications) {
            return [
                'available' => false,
                'message'   => 'CRB is pulled when the borrower pays the application fee on a loan application.',
            ];
        }

        if (! $history) {
            return [
                'available' => false,
                'message'   => 'No CRB pull on file yet. It refreshes with the next application fee payment.',
            ];
        }

        return [
            'available'   => true,
            'fresh'       => $fresh,
            'score'       => $history->score,
            'risk_grade'  => $history->risk_grade,
            'checked_at'  => $history->checked_at,
            'source'      => $history->source,
            'message'     => $fresh
                ? 'Showing the latest bureau pull from an application.'
                : 'Bureau data is stale. It refreshes only when the borrower applies again and pays the application fee.',
        ];
    }

    /** @param  array<string, mixed>  $engagement */
    private function repaymentStanding(array $engagement): array
    {
        $trust = $engagement['trust_score'] ?? [];
        $streak = $engagement['repayment_streak'] ?? [];
        $percent = (int) ($trust['percent'] ?? 0);

        $label = match (true) {
            $percent >= 80 => 'Strong payer',
            $percent >= 60 => 'Reliable',
            $percent >= 40 => 'Developing',
            $percent > 0 => 'Needs attention',
            default => 'Not enough history',
        };

        return [
            'label'          => $label,
            'trust_percent'  => $percent,
            'trust_stars'    => $trust['filled'] ?? 0,
            'trust_max'      => $trust['max'] ?? 5,
            'streak'         => (int) ($streak['count'] ?? 0),
            'loyalty_points' => (int) ($engagement['loyalty_points'] ?? 0),
            'factors'        => $trust['factors'] ?? [],
        ];
    }
}
