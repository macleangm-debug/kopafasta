<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApplicationDocumentRequestService
{
    public const REJECTED_UPLOAD_PREFIX = 'Previous upload rejected for this application: ';

    /** @var list<string> */
    public const PRESET_LABELS = [
        'Insurance About To Expire',
        'New Insurance Certificate',
        'New Ownership Document',
        'New Asset Photo',
        'Updated National ID',
        'Image Not Clear',
        'Ownership Certificate Missing Page',
        'Signature Not Visible',
        'Updated Bank Statement',
        'Updated Mobile Money Statement',
        'Business Registration Document',
        'Business Photos',
        'Supplier Invoices',
        'Tax Documents',
        'Employment Confirmation Letter',
        'Guarantor residence letter',
        'Updated employment contract',
        'Latest salary slip',
    ];

    /** @var list<string> */
    public const ASSET_BACKED_PRESET_LABELS = [
        'Insurance About To Expire',
        'New Insurance Certificate',
        'New Ownership Document',
        'New Asset Photo',
        'Updated National ID',
        'Image Not Clear',
    ];

    /** Collateral requests for loans that are not already asset-backed / asset-lending. */
    /** @var list<string> */
    public const COLLATERAL_PRESET_LABELS = [
        'Add collateral asset',
        'Updated collateral ownership document',
        'Updated collateral insurance certificate',
        'New collateral photo',
        'Collateral valuation document',
    ];

    /** @return array<string, string> preset => default borrower instructions */
    public static function presetInstructions(): array
    {
        return [
            'Insurance About To Expire' => 'Upload an updated insurance certificate.',
            'New Insurance Certificate' => 'Upload a clear insurance certificate.',
            'New Ownership Document' => 'Upload the ownership or logbook document.',
            'New Asset Photo' => 'Upload a clear photo of the asset.',
            'Updated National ID' => 'Upload a clear national ID photo.',
            'New National ID photo' => 'Upload a clearer national ID photo from your profile.',
            'New face verification photo' => 'Retake face photos in your profile.',
            'Identity verification photo' => 'Upload a photo of you holding your national ID.',
            'Image Not Clear' => 'Upload a sharper photo.',
            'Ownership Certificate Missing Page' => 'Upload every page of the ownership document.',
            'Signature Not Visible' => 'Update your signature in your profile.',
            'Updated Bank Statement' => 'Upload a recent bank statement.',
            'Updated Mobile Money Statement' => 'Upload a recent mobile money statement.',
            'Business Registration Document' => 'Upload your business registration.',
            'Business Photos' => 'Upload clear photos of your business.',
            'Supplier Invoices' => 'Upload the supplier invoices.',
            'Tax Documents' => 'Upload the requested tax documents.',
            'Employment Confirmation Letter' => 'Upload an employment confirmation letter.',
            'Guarantor residence letter' => 'Upload a residence letter for the guarantor.',
            'Updated employment contract' => 'Upload your updated employment contract.',
            'Latest salary slip' => 'Upload your latest salary slip.',
            'Add collateral asset' => 'Add collateral with ownership and insurance in your profile.',
            'Updated collateral ownership document' => 'Upload an updated ownership document.',
            'Updated collateral insurance certificate' => 'Upload an updated insurance certificate.',
            'New collateral photo' => 'Upload a clear photo of the collateral.',
            'Collateral valuation document' => 'Upload the valuation document.',
        ];
    }

    /**
     * Older English defaults still stored on existing requests.
     *
     * @return array<string, list<string>>
     */
    public static function legacyPresetInstructions(): array
    {
        return [
            'Insurance About To Expire' => [
                'Your asset insurance is expiring soon. Please upload an updated insurance certificate.',
            ],
            'New Insurance Certificate' => [
                'Please upload a clear copy of the current insurance certificate for this asset.',
            ],
            'New Ownership Document' => [
                'Please upload the ownership or logbook document for this asset.',
            ],
            'New Asset Photo' => [
                'Please upload a clear, recent photo of the asset.',
            ],
            'Updated National ID' => [
                'Please upload a clear copy of your national ID.',
            ],
            'New National ID photo' => [
                'Underwriting needs a clearer national ID photo. Please upload again from your profile.',
            ],
            'New face verification photo' => [
                'Underwriting needs new face verification photos. Please recapture them in your profile.',
            ],
            'Identity verification photo' => [
                'Please upload a new identity verification photo holding your national ID.',
            ],
            'Image Not Clear' => [
                'The uploaded image is not clear enough. Please re-upload a sharper photo.',
            ],
            'Ownership Certificate Missing Page' => [
                'The ownership certificate appears incomplete. Please upload all pages.',
            ],
            'Signature Not Visible' => [
                'Your signature is not clear enough for legal documents. Please update it once in your profile — it will be used on all contracts.',
            ],
            'Updated Bank Statement' => [
                'Please upload an updated bank statement covering the latest period.',
            ],
            'Updated Mobile Money Statement' => [
                'Please upload an updated mobile money statement.',
                'Please upload an updated mobile money statement covering the latest period.',
            ],
            'Additional Income Proof' => [
                'Please upload additional proof of income for this application.',
            ],
            'Business Registration Document' => [
                'Please upload your business registration document.',
            ],
            'Business Photos' => [
                'Please upload clear photos of your business premises or activity.',
            ],
            'Supplier Invoices' => [
                'Please upload the relevant supplier invoices for this loan.',
            ],
            'Tax Documents' => [
                'Please upload the requested tax documents.',
            ],
            'Employment Confirmation Letter' => [
                'Please upload an employment confirmation letter from your employer.',
                'Please upload an employment confirmation letter.',
            ],
            'Guarantor residence letter' => [
                'Please upload a residence letter for your guarantor.',
                'Please upload a residence confirmation letter for the guarantor.',
            ],
            'Updated employment contract' => [
                'Please upload your updated employment contract.',
            ],
            'Latest salary slip' => [
                'Please upload your latest salary slip.',
            ],
            'Add collateral asset' => [
                'Underwriting needs collateral for this loan. Please add a collateral asset in your profile with ownership and insurance documents.',
            ],
            'Updated collateral ownership document' => [
                'Please upload an updated ownership or logbook document for your collateral asset.',
                'Please upload an updated ownership document for the collateral.',
            ],
            'Updated collateral insurance certificate' => [
                'Please upload a current insurance certificate for your collateral asset.',
                'Please upload an updated insurance certificate for the collateral.',
            ],
            'New collateral photo' => [
                'Please upload a clear, recent photo of your collateral asset.',
                'Please upload a clear photo of the collateral.',
            ],
            'Collateral valuation document' => [
                'Please upload a valuation or appraisal document for your collateral.',
                'Please upload the collateral valuation document.',
            ],
        ];
    }

    /**
     * Notification CTA: loan application view, scrolled to this request.
     * The application card then links to the exact profile section.
     */
    public function borrowerNotificationUrl(LoanApplicationDocumentRequest $request, ?Customer $viewer = null): string
    {
        $application = $request->application;
        if (! $application) {
            return $this->borrowerActionUrl($request, $viewer);
        }

        return route('site.borrower.application', $application)
            .'?doc='.$request->id
            .'#request-'.$request->id;
    }

    /**
     * Deep-link borrowers to the exact profile card for identity/signature/face/income,
     * otherwise to the application document request anchor.
     */
    public function borrowerActionUrl(LoanApplicationDocumentRequest $request, ?Customer $viewer = null): string
    {
        $application = $request->application;

        // Owner assisting a member/guarantor: loan-file uploads stay on the application.
        // Profile-guided items (income, ID, face, collateral) are fulfilled on that person's profile.
        if ($viewer && $this->borrowerIsAssisting($viewer, $request) && $application && ! $this->isProfileGuidedRequest($request)) {
            return route('site.borrower.application', $application).'?doc='.$request->id.'#request-'.$request->id;
        }

        $kind = $this->borrowerActionKind($request);
        $applicationId = $application?->id;

        return match ($kind) {
            'signature' => $this->soloProfileUrl('personal', 'signature', 'profile-signature', [], $applicationId),
            'face' => $this->soloProfileUrl('personal', 'face', 'profile-face', [], $applicationId),
            'identity' => $this->soloProfileUrl('personal', 'id_images', 'profile-id-images', [], $applicationId),
            'income' => $this->soloProfileUrl('activity', 'income', 'profile-income-statement', [], $applicationId),
            'residence' => $this->soloProfileUrl('residence', 'verification', 'profile-residence-verification', [], $applicationId),
            'business' => $this->soloProfileUrl(
                'activity',
                'additional',
                'profile-additional-documents',
                $this->businessDocQuery($request),
                $applicationId,
            ),
            'collateral' => $this->collateralProfileUrl($request, $application),
            default => $application
                ? route('site.borrower.application', $application).'?doc='.$request->id.'#request-'.$request->id
                : route('site.borrower.loans', ['tab' => 'applications']),
        };
    }

    /**
     * @param  array<string, scalar>  $extra
     */
    private function soloProfileUrl(string $section, string $focus, string $hash, array $extra = [], ?int $applicationId = null): string
    {
        $query = array_merge(['focus' => $focus, 'solo' => 1], $extra);
        if ($applicationId) {
            $query['application'] = $applicationId;
        }

        return route('site.borrower.profile', ['section' => $section]).'?'.http_build_query($query).'#'.$hash;
    }

    /** @return array<string, string> */
    private function businessDocQuery(LoanApplicationDocumentRequest $request): array
    {
        $doc = app(ProfileRevisionService::class)->documentCodesForLabel((string) $request->label)[0] ?? null;

        return $doc ? ['doc' => $doc] : [];
    }

    private function collateralProfileUrl(LoanApplicationDocumentRequest $request, ?LoanApplication $application): string
    {
        $customer = $request->subjectCustomer
            ?? ($request->subject_customer_id ? Customer::query()->find($request->subject_customer_id) : null)
            ?? $application?->customer;

        $label = mb_strtolower((string) $request->label);
        $add = str_contains($label, 'add collateral') || ! $customer;

        if ($customer && ! $add) {
            $add = app(CustomerAssetService::class)->forCustomer($customer)->isEmpty();
        }

        return route('site.borrower.profile', array_filter([
            'section' => 'assets',
            'solo' => 1,
            'uw' => 1,
            'application' => $application?->id,
            'add' => $add ? 1 : null,
        ], fn ($value) => $value !== null && $value !== false));
    }

    public function isProfileGuidedRequest(LoanApplicationDocumentRequest $request): bool
    {
        return in_array($this->borrowerActionKind($request), [
            'signature', 'face', 'identity', 'collateral', 'income', 'residence', 'business',
        ], true);
    }

    /** Classify UW requests for borrower CTAs (docs, signature, face, identity, clarification). */
    public function borrowerActionKind(LoanApplicationDocumentRequest $request): string
    {
        $label = mb_strtolower((string) $request->label);

        if (str_contains($label, 'signature')) {
            return 'signature';
        }
        if (str_contains($label, 'face')
            || str_contains($label, 'selfie')
            || str_contains($label, 'identity verification photo')
            || str_contains($label, 'image not clear')) {
            return 'face';
        }
        if (str_contains($label, 'national id') || str_contains($label, 'nida')) {
            return 'identity';
        }
        if (
            str_contains($label, 'bank statement')
            || str_contains($label, 'mobile money')
            || str_contains($label, 'mobile statement')
            || str_contains($label, 'account statement')
            || str_contains($label, 'income verification')
            || str_contains($label, 'income proof')
            || str_contains($label, 'salary slip')
            || str_contains($label, 'employment confirmation')
            || str_contains($label, 'employment contract')
        ) {
            return 'income';
        }
        if (str_contains($label, 'residence') || str_contains($label, 'lga')) {
            return 'residence';
        }
        if ($this->isBusinessProfileLabel($label)) {
            return 'business';
        }
        if (str_contains($label, 'collateral') || str_contains($label, 'add collateral')
            || str_contains($label, 'asset photo') || str_contains($label, 'ownership document')
            || str_contains($label, 'insurance')) {
            return 'collateral';
        }
        if ($request->type === 'clarification') {
            return 'clarification';
        }

        return 'document';
    }

    public function screeningKindLabel(LoanApplicationDocumentRequest $request): string
    {
        return match ($this->borrowerActionKind($request)) {
            'income' => 'Income verification',
            'collateral' => 'Collateral',
            'face' => 'Face photos',
            'identity' => 'National ID',
            'signature' => 'Signature',
            'residence' => 'Residence',
            'business' => 'Business documents',
            default => (string) $request->label,
        };
    }

    /**
     * Deep-link into the profile tab where this submitted request should be reviewed.
     *
     * @param  list<array<string, mixed>>  $guarantorRows
     */
    public function screeningReviewUrl(
        LoanApplicationDocumentRequest $request,
        LoanApplication $application,
        array $guarantorRows = [],
    ): string {
        $kind = $this->borrowerActionKind($request);
        $tab = match ($kind) {
            'income' => 'documents',
            'collateral' => 'collateral',
            'face' => 'face',
            'identity' => 'personal',
            default => 'documents',
        };
        $person = in_array((string) $request->subject_kind, ['borrower', 'member', 'guarantor'], true)
            ? (string) $request->subject_kind
            : 'borrower';

        $m = $person === 'member' ? $request->loan_group_member_id : null;
        $g = null;
        if ($person === 'guarantor' && $request->subject_customer_id) {
            $match = collect($guarantorRows)->first(
                fn ($row) => (int) ($row['customer_id'] ?? 0) === (int) $request->subject_customer_id
            );
            $g = $match['link_id'] ?? null;
        }

        $params = array_filter([
            'loan_application' => $application,
            'workspace' => 'profiles',
            'tab' => $tab,
            'person' => $person,
            'm' => $m,
            'g' => $g,
            'review_person' => $person,
            'review_m' => $m,
            'review_g' => $g,
        ], fn ($v) => $v !== null && $v !== '');

        return route('admin.loan-applications.show', $params).($kind === 'income' ? '#review-documents' : '#borrower-file');
    }

    /**
     * Guided CTA payload for View Application / loan preview cards.
     *
     * @return array{id: int, kind: string, label: string, cta_label: string, url: string, status: string, rejected: bool, instructions: ?string}
     */
    public function borrowerGuidedAction(LoanApplicationDocumentRequest $request, ?Customer $viewer = null): array
    {
        $kind = $this->borrowerActionKind($request);
        $rejected = $request->status === 'rejected';
        $assisting = $viewer && $this->borrowerIsAssisting($viewer, $request);

        $collateralCta = __('borrower.loan_profile.uw_cta.add_collateral');
        if ($kind === 'collateral' && ! $assisting) {
            $customer = $request->subjectCustomer
                ?? ($request->subject_customer_id ? Customer::query()->find($request->subject_customer_id) : null)
                ?? $request->application?->customer;
            if ($customer && $customer->assets()->exists()) {
                $collateralCta = __('borrower.loan_profile.uw_cta.update_collateral');
            }
        }

        $ctaLabel = $assisting
            ? ($rejected
                ? __('borrower.loan_profile.reupload')
                : __('borrower.loan_profile.upload'))
            : match ($kind) {
                'signature' => __('borrower.loan_profile.uw_cta.update_signature'),
                'face' => __('borrower.loan_profile.uw_cta.recapture_face'),
                'identity' => __('borrower.loan_profile.uw_cta.update_identity'),
                'collateral' => $collateralCta,
                'income' => __('borrower.loan_profile.uw_cta.update_income'),
                'residence' => __('borrower.loan_profile.uw_cta.update_residence'),
                'business' => __('borrower.loan_profile.uw_cta.update_business'),
                'clarification' => __('borrower.loan_profile.uw_cta.respond'),
                default => $rejected
                    ? __('borrower.loan_profile.reupload')
                    : __('borrower.loan_profile.upload'),
            };

        return [
            'id' => (int) $request->id,
            'kind' => $assisting ? 'document' : $kind,
            'label' => $this->localizedLabel((string) $request->label),
            'cta_label' => $ctaLabel,
            'url' => $this->borrowerActionUrl($request, $viewer),
            'status' => (string) $request->status,
            'rejected' => $rejected,
            'instructions' => $this->localizedInstructions(
                (string) $request->label,
                $request->instructions
            ),
        ];
    }

    /**
     * Open underwriting requests as guided borrower CTAs (pending / rejected).
     *
     * @return list<array{id: int, kind: string, label: string, cta_label: string, url: string, status: string, rejected: bool, instructions: ?string}>
     */
    public function openGuidedActionsForApplication(LoanApplication $application): array
    {
        if (in_array((string) $application->status, ['withdrawn', 'rejected', 'disbursed'], true)) {
            return [];
        }

        $application->loadMissing('documentRequests');

        $owner = $application->customer;
        if (! $owner) {
            return [];
        }

        return $application->documentRequests
            ->filter(fn (LoanApplicationDocumentRequest $request) => $request->needsBorrowerAction())
            ->filter(fn (LoanApplicationDocumentRequest $request) => $this->isSubjectOfRequest($owner, $request))
            ->values()
            ->map(fn (LoanApplicationDocumentRequest $request) => $this->borrowerGuidedAction(
                $request,
                $owner
            ))
            ->all();
    }

    public function __construct(private readonly NotificationService $notifier) {}

    /**
     * Borrower-facing label for a stored (English) preset / DocumentType name / custom request label.
     */
    public function localizedLabel(string $label, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $key = 'borrower.document_request_presets.labels.'.$label;
        $translated = __($key, [], $locale);
        if ($translated !== $key) {
            return $translated;
        }

        $typeKey = 'borrower.document_type_names.'.$label;
        $byCode = __($typeKey, [], $locale);
        if ($byCode !== $typeKey) {
            return $byCode;
        }

        $code = $this->documentTypeCodeForEnglishName($label);
        if ($code !== null) {
            $fromType = __('borrower.document_type_names.'.$code, [], $locale);
            if ($fromType !== 'borrower.document_type_names.'.$code) {
                return $fromType;
            }
        }

        return $label;
    }

    public function localizedDocumentTypeName(?string $code, ?string $englishName = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if (filled($englishName)) {
            $fromLabel = $this->localizedLabel($englishName, $locale);
            if ($fromLabel !== $englishName) {
                return $fromLabel;
            }
        }
        if (filled($code)) {
            $key = 'borrower.document_type_names.'.$code;
            $translated = __($key, [], $locale);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $englishName ?: (string) __('borrower.document_review.document', [], $locale);
    }

    /** @return string|null seeder code when $label matches an English DocumentType name */
    private function documentTypeCodeForEnglishName(string $label): ?string
    {
        $names = __('borrower.document_type_names', [], 'en');
        if (! is_array($names)) {
            return null;
        }
        $code = array_search($label, $names, true);

        return $code === false ? null : (string) $code;
    }

    /**
     * Borrower-facing instructions. Custom admin text is kept as written;
     * preset English defaults are translated for the customer's locale.
     */
    public function localizedInstructions(string $label, ?string $instructions, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $instructions = trim((string) $instructions);
        $langKey = 'borrower.document_request_presets.instructions.'.$label;
        $knownEnglish = $this->knownEnglishInstructions($label);

        $isPresetEnglish = $instructions === '' || in_array($instructions, $knownEnglish, true);

        if ($isPresetEnglish) {
            $translated = __($langKey, [], $locale);
            if ($translated !== $langKey) {
                return $translated;
            }
            if ($knownEnglish !== []) {
                return $knownEnglish[0];
            }

            return (string) __('borrower.document_request_presets.default_instructions', [], $locale);
        }

        if (str_starts_with($instructions, self::REJECTED_UPLOAD_PREFIX)) {
            $reason = trim(substr($instructions, strlen(self::REJECTED_UPLOAD_PREFIX)));

            return (string) __('borrower.document_review.rejected_prefix', [
                'reason' => $this->localizedFailReason($reason, $locale),
            ], $locale);
        }

        return $instructions;
    }

    public function localizedFailReason(string $reason, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $notes = '';
        $main = $reason;
        if (str_contains($reason, ' — ')) {
            [$main, $notes] = explode(' — ', $reason, 2);
        }

        $reasons = config('application_document_review.fail_reasons', []);
        $code = array_search($main, $reasons, true);
        if ($code === false) {
            return $reason;
        }

        $translated = __('borrower.document_review.fail_reasons.'.$code, [], $locale);
        if ($translated === 'borrower.document_review.fail_reasons.'.$code) {
            $translated = $main;
        }

        return $notes !== '' ? $translated.' — '.$notes : $translated;
    }

    /** @return list<string> */
    private function knownEnglishInstructions(string $label): array
    {
        $langKey = 'borrower.document_request_presets.instructions.'.$label;
        $fromLang = __($langKey, [], 'en');
        $fromLang = $fromLang !== $langKey ? $fromLang : null;

        return array_values(array_unique(array_filter([
            self::presetInstructions()[$label] ?? null,
            $fromLang,
            ...(self::legacyPresetInstructions()[$label] ?? []),
        ])));
    }

    /** Borrower-facing "Name · Role" with a translated role. */
    public function localizedSubjectRoleLabel(LoanApplicationDocumentRequest $request, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $kind = (string) ($request->subject_kind ?? 'borrower');
        $name = $request->subjectCustomer?->full_name
            ?? $request->groupMember?->customer?->full_name
            ?? null;

        if ($kind === 'member') {
            $role = strtolower((string) ($request->groupMember?->role ?? 'member'));
            $roleKey = $role === 'leader'
                ? 'borrower.loan_profile.document_subject_leader'
                : 'borrower.loan_profile.document_subject_member';
            $name = $name ?: (string) __('borrower.notifications.document_request_member_fallback', [], $locale);

            return trim($name).' · '.__($roleKey, [], $locale);
        }

        if ($kind === 'guarantor') {
            $name = $name ?: (string) __('borrower.loan_profile.document_subject_guarantor', [], $locale);

            return trim($name).' · '.__('borrower.loan_profile.document_subject_guarantor', [], $locale);
        }

        $name = $name ?: ($request->application?->customer?->full_name ?? (string) __('borrower.loan_profile.document_subject_borrower', [], $locale));
        $isGroup = (bool) ($request->application?->loanGroup);
        $roleKey = $isGroup
            ? 'borrower.loan_profile.document_subject_leader'
            : 'borrower.loan_profile.document_subject_borrower';

        return trim($name).' · '.__($roleKey, [], $locale);
    }

    public function create(
        LoanApplication $application,
        User $requester,
        string $label,
        ?string $instructions = null,
        ?\DateTimeInterface $dueAt = null,
        string $type = 'document',
        string $subjectKind = 'borrower',
        ?int $subjectCustomerId = null,
        ?int $loanGroupMemberId = null,
    ): LoanApplicationDocumentRequest {
        $instructions ??= self::presetInstructions()[$label] ?? null;
        [$subjectKind, $subjectCustomerId, $loanGroupMemberId] = $this->resolveSubject(
            $application,
            $subjectKind,
            $subjectCustomerId,
            $loanGroupMemberId,
        );

        $request = LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'subject_kind' => $subjectKind,
            'subject_customer_id' => $subjectCustomerId,
            'loan_group_member_id' => $loanGroupMemberId,
            'requested_by' => $requester->id,
            'type' => $type,
            'label' => $label,
            'instructions' => $instructions,
            'status' => 'pending',
            'due_at' => $dueAt,
        ]);

        $this->syncApplicationStatus($application->fresh());
        $this->notifyBorrower($request);
        app(ProfileRevisionService::class)->applyForDocumentRequest($application->fresh(), $request, notify: false);

        return $request;
    }

    /**
     * @param  list<string>  $labels
     * @return Collection<int, LoanApplicationDocumentRequest>
     */
    public function createMany(
        LoanApplication $application,
        User $requester,
        array $labels,
        ?string $instructions = null,
        ?\DateTimeInterface $dueAt = null,
        string $type = 'document',
        string $subjectKind = 'borrower',
        ?int $subjectCustomerId = null,
        ?int $loanGroupMemberId = null,
    ): Collection {
        $labels = collect($labels)
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        [$subjectKind, $subjectCustomerId, $loanGroupMemberId] = $this->resolveSubject(
            $application,
            $subjectKind,
            $subjectCustomerId,
            $loanGroupMemberId,
        );

        $created = collect();

        foreach ($labels as $label) {
            $request = LoanApplicationDocumentRequest::create([
                'loan_application_id' => $application->id,
                'subject_kind' => $subjectKind,
                'subject_customer_id' => $subjectCustomerId,
                'loan_group_member_id' => $loanGroupMemberId,
                'requested_by' => $requester->id,
                'type' => $type,
                'label' => $label,
                'instructions' => $instructions ?: (self::presetInstructions()[$label] ?? null),
                'status' => 'pending',
                'due_at' => $dueAt,
            ]);
            $created->push($request);
            $this->notifyBorrower($request, inApp: false);
        }

        $application = $application->fresh();
        $this->syncApplicationStatus($application);

        if ($created->isNotEmpty()) {
            $revision = app(ProfileRevisionService::class);
            foreach ($created as $request) {
                $revision->applyForDocumentRequest($application, $request, notify: false);
            }
            $this->notifyBorrowerBatch($application->loadMissing('customer'), $created);
        }

        return $created;
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?int}
     */
    private function resolveSubject(
        LoanApplication $application,
        string $subjectKind = 'borrower',
        ?int $subjectCustomerId = null,
        ?int $loanGroupMemberId = null,
    ): array {
        if ($subjectKind === 'member') {
            $application->loadMissing('loanGroup.members');
            $member = $application->loanGroup?->members->firstWhere('id', $loanGroupMemberId);
            if (! $member) {
                throw new \InvalidArgumentException('Selected group member was not found on this application.');
            }

            return ['member', $member->customer_id ? (int) $member->customer_id : null, (int) $member->id];
        }

        if ($subjectKind === 'guarantor' && $subjectCustomerId) {
            return ['guarantor', $subjectCustomerId, null];
        }

        return ['borrower', $application->customer_id ? (int) $application->customer_id : null, null];
    }

    /**
     * True when this request is for the person on a screening desk (leader / member / guarantor).
     * Member and guarantor asks never attach to the leader, and never to a sibling on the file.
     */
    public function targetsReviewSubject(
        LoanApplicationDocumentRequest $request,
        string $panelPerson,
        ?int $subjectCustomerId = null,
        ?int $memberId = null,
        ?int $applicationOwnerId = null,
    ): bool {
        $kind = (string) ($request->subject_kind ?? 'borrower');
        if (! in_array($kind, ['borrower', 'member', 'guarantor'], true)) {
            $kind = 'borrower';
        }
        $reqCustomerId = (int) ($request->subject_customer_id ?? 0);
        $reqMemberId = (int) ($request->loan_group_member_id ?? 0);
        $subjectCustomerId = (int) ($subjectCustomerId ?? 0);
        $memberId = (int) ($memberId ?? 0);
        $applicationOwnerId = (int) ($applicationOwnerId ?? 0);

        if ($panelPerson === 'member') {
            if ($kind !== 'member') {
                return false;
            }
            if ($memberId > 0 && $reqMemberId > 0) {
                return $reqMemberId === $memberId;
            }

            return $subjectCustomerId > 0 && $reqCustomerId === $subjectCustomerId;
        }

        if ($panelPerson === 'guarantor') {
            if ($kind !== 'guarantor') {
                return false;
            }

            return $subjectCustomerId <= 0 || $reqCustomerId === $subjectCustomerId;
        }

        if (in_array($kind, ['member', 'guarantor'], true)) {
            return false;
        }
        if ($reqCustomerId > 0 && $subjectCustomerId > 0) {
            return $reqCustomerId === $subjectCustomerId;
        }
        if ($reqCustomerId > 0 && $applicationOwnerId > 0) {
            return $reqCustomerId === $applicationOwnerId;
        }

        return $reqCustomerId === 0 || $reqCustomerId === $applicationOwnerId;
    }

    /** The person who must replace the file — never the leader standing in for a member/guarantor. */
    public function isSubjectOfRequest(Customer $customer, LoanApplicationDocumentRequest $request): bool
    {
        $request->loadMissing('application');
        $kind = (string) ($request->subject_kind ?? 'borrower');

        if ((int) ($request->subject_customer_id ?? 0) > 0) {
            return (int) $request->subject_customer_id === (int) $customer->id;
        }

        if (in_array($kind, ['member', 'guarantor'], true)) {
            return false;
        }

        return (int) $request->application?->customer_id === (int) $customer->id;
    }

    public function notifyBorrower(LoanApplicationDocumentRequest $request, bool $inApp = true): void
    {
        $application = $request->application()->with(['customer', 'loanGroup'])->first();
        if (! $application) {
            return;
        }

        $request->loadMissing(['subjectCustomer', 'groupMember.customer']);

        $kind = (string) ($request->subject_kind ?? 'borrower');
        $subject = $request->subjectCustomer
            ?? ($request->subject_customer_id ? Customer::find($request->subject_customer_id) : null);
        if (! $subject && ! in_array($kind, ['member', 'guarantor'], true)) {
            $subject = $application->customer;
        }

        if ($subject) {
            $this->notifyDocumentRequestCustomer($subject, $application, $request, $inApp, forLeader: false);
        }

        $leader = $application->customer;
        if (
            in_array((string) ($request->subject_kind ?? ''), ['member', 'guarantor'], true)
            && $subject
            && $leader
            && (int) $leader->id !== (int) $subject->id
        ) {
            $this->notifyDocumentRequestCustomer($leader, $application, $request, $inApp, forLeader: true);
        }
    }

    private function notifyDocumentRequestCustomer(
        Customer $customer,
        LoanApplication $application,
        LoanApplicationDocumentRequest $request,
        bool $inApp = true,
        bool $forLeader = false,
    ): void {
        $uploadUrl = $this->borrowerNotificationUrl($request, $forLeader ? $customer : null);
        $locale = $customer->user?->locale
            ?? $customer->preferred_locale
            ?? $customer->locale
            ?? app()->getLocale();
        $label = $this->localizedLabel((string) $request->label, $locale);
        $instructions = $this->localizedInstructions(
            (string) $request->label,
            $request->instructions,
            $locale,
        );
        $cta = __('borrower.notifications.document_request_cta', [], $locale);

        $subjectName = $request->subjectCustomer?->full_name
            ?? $request->groupMember?->customer?->full_name
            ?? $request->subjectCustomer?->first_name
            ?? $request->groupMember?->customer?->first_name
            ?? __('borrower.notifications.document_request_member_fallback', [], $locale);

        $instructionText = $forLeader
            ? __('borrower.notifications.document_request_for_member', [
                'member' => $subjectName,
                'instructions' => $instructions,
            ], $locale)
            : $instructions;

        $fallbackBody = $forLeader
            ? __('borrower.notifications.document_request_sms_leader', [
                'name' => $customer->first_name ?? 'Customer',
                'label' => $label,
                'member' => $subjectName,
                'application' => $application->application_number,
                'url' => $uploadUrl,
            ], $locale)
            : __('borrower.notifications.document_request_sms', [
                'name' => $customer->first_name ?? 'Customer',
                'label' => $label,
                'application' => $application->application_number,
                'url' => $uploadUrl,
            ], $locale);

        $this->notifier->notifyCustomer($customer, 'application_document_request', [
            'name' => $customer->first_name ?? 'Customer',
            'application_number' => $application->application_number,
            'label' => $label,
            'instructions' => $instructionText,
            'due_date' => optional($request->due_at)->format('d M Y')
                ?? __('borrower.notifications.document_request_due_asap', [], $locale),
            'upload_url' => $uploadUrl,
            '_locale' => $locale,
            '_skip_in_app' => $inApp,
            '_fallback_body' => $fallbackBody,
            '_fallback_subject' => $forLeader
                ? __('borrower.notifications.document_request_subject_leader', [], $locale)
                : __('borrower.notifications.document_request_subject', [], $locale),
        ]);

        if ($inApp) {
            $params = [
                'application' => $application->application_number,
                'label' => $label,
            ];
            $body = $forLeader
                ? __('borrower.notifications.document_request_body', $params, $locale)
                    .' ('.$subjectName.')'
                : __('borrower.notifications.document_request_body', $params, $locale);

            $this->notifier->notifyInApp(
                $customer,
                $body,
                'document_request',
                'application_document_request',
                __('borrower.notifications.document_request_title', [], $locale),
                $uploadUrl,
                $cta,
                [
                    'title_key' => 'borrower.notifications.document_request_title',
                    'body_key' => 'borrower.notifications.document_request_body',
                    'params' => $params,
                    'loan_application_id' => $application->id,
                    'loan_application_document_request_id' => $request->id,
                ],
            );
        }
    }

    /** @param  Collection<int, LoanApplicationDocumentRequest>  $requests */
    private function notifyBorrowerBatch(LoanApplication $application, Collection $requests): void
    {
        $application->loadMissing('customer');
        $leader = $application->customer;
        $uploadUrl = route('site.borrower.application', $application);

        /** @var array<int, Collection<int, LoanApplicationDocumentRequest>> $byCustomerId */
        $byCustomerId = [];

        foreach ($requests as $req) {
            $subjectId = (int) ($req->subject_customer_id ?: $application->customer_id);
            if ($subjectId > 0) {
                $byCustomerId[$subjectId] ??= collect();
                $byCustomerId[$subjectId]->push($req);
            }

            if (
                in_array((string) ($req->subject_kind ?? ''), ['member', 'guarantor'], true)
                && $leader
                && (int) $leader->id !== $subjectId
            ) {
                $byCustomerId[(int) $leader->id] ??= collect();
                $byCustomerId[(int) $leader->id]->push($req);
            }
        }

        if ($byCustomerId === [] && $leader) {
            $byCustomerId[(int) $leader->id] = $requests;
        }

        foreach ($byCustomerId as $customerId => $customerRequests) {
            $customer = ((int) $leader?->id === (int) $customerId)
                ? $leader
                : Customer::find($customerId);

            if (! $customer) {
                continue;
            }

            $locale = $customer->user?->locale
                ?? $customer->preferred_locale
                ?? $customer->locale
                ?? app()->getLocale();
            $unique = $customerRequests->unique('id')->values();
            $count = $unique->count();
            $labels = $unique
                ->take(3)
                ->map(fn ($req) => $this->localizedLabel((string) $req->label, $locale))
                ->implode(', ');
            $suffix = $count > 3 ? '…' : '';
            $first = $unique->first();
            $actionUrl = ($count === 1 && $first)
                ? $this->borrowerNotificationUrl($first, $customer)
                : $uploadUrl.'#documents';

            $this->notifier->notifyInApp(
                $customer,
                __('borrower.notifications.document_request_batch_body', [
                    'application' => $application->application_number,
                    'count' => $count,
                    'labels' => $labels.$suffix,
                ], $locale),
                'document_request',
                'application_document_request',
                __('borrower.notifications.document_request_title', [], $locale),
                $actionUrl,
                __('borrower.notifications.document_request_cta', [], $locale),
                [
                    'title_key' => 'borrower.notifications.document_request_title',
                    'body_key' => 'borrower.notifications.document_request_batch_body',
                    'params' => [
                        'application' => $application->application_number,
                        'count' => $count,
                        'labels' => $labels.$suffix,
                    ],
                    'loan_application_id' => $application->id,
                    'loan_application_document_request_id' => $count === 1 ? $first?->id : null,
                ],
            );
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function recordUploads(
        LoanApplicationDocumentRequest $request,
        Customer $actor,
        array $files,
        ?Customer $subjectCustomer = null,
    ): Collection {
        $application = $request->application;
        $documentCustomerId = (int) (
            $request->subject_customer_id
            ?? $subjectCustomer?->id
            ?? $actor->id
        );

        $files = array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
        if ($files === []) {
            return collect();
        }

        $revision = app(ProfileRevisionService::class);
        $codes = $revision->documentCodesForLabel((string) $request->label);
        $type = $codes === []
            ? null
            : DocumentType::query()->whereIn('code', $codes)->where('is_active', true)->first();
        $profileGuided = $this->isProfileGuidedRequest($request);
        $basename = $type?->code ?: Str::slug((string) $request->label) ?: 'document';
        $path = count($files) === 1 && ($files[0]->getMimeType() ?? '') === 'application/pdf' && ! $profileGuided
            ? $files[0]->store("borrower/{$documentCustomerId}/applications/{$application->id}/requests/{$request->id}", 'public')
            : app(DocumentPageMerger::class)->merge($files, $documentCustomerId, $basename);

        if ($profileGuided && $type) {
            $existing = CustomerDocument::query()
                ->where('customer_id', $documentCustomerId)
                ->where('document_type_id', $type->id)
                ->whereNull('loan_application_id')
                ->whereNotIn('status', ['replaced', 'archived'])
                ->latest('id')
                ->first();
            if ($existing) {
                $existing->update(['status' => 'replaced']);
            }
        }

        $stored = collect([
            CustomerDocument::create([
                'customer_id' => $documentCustomerId,
                'loan_application_id' => $profileGuided ? null : $application->id,
                'loan_application_document_request_id' => $request->id,
                'document_type_id' => $type?->id,
                'file_path' => $path,
                'status' => 'pending_review',
                'notes' => json_encode(array_filter([
                    'page_count' => count($files),
                    'request_label' => $request->label,
                    'original_name' => count($files) === 1 ? $files[0]->getClientOriginalName() : null,
                ])),
            ]),
        ]);

        $request->update([
            'status' => 'uploaded',
            'admin_notes' => null,
            'uploaded_by_customer_id' => $actor->id,
        ]);

        $this->syncApplicationStatus($application->fresh());
        app(NotificationCtaService::class)->consumeDocumentRequestCtas((int) $application->id, (int) $request->id);

        return $stored;
    }

    public function recordClarification(
        LoanApplicationDocumentRequest $request,
        string $response,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'borrower_response' => $response,
            'status' => 'uploaded',
            'admin_notes' => null,
        ]);

        $this->syncApplicationStatus($request->application->fresh());

        return $request->fresh();
    }

    public function markSatisfied(
        LoanApplicationDocumentRequest $request,
        User $admin,
        ?string $notes = null,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'status' => 'satisfied',
            'satisfied_by' => $admin->id,
            'satisfied_at' => now(),
            'admin_notes' => $notes,
        ]);

        $request->uploads()->where('status', 'pending_review')->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->syncApplicationStatus($request->application->fresh());
        app(NotificationCtaService::class)->consumeDocumentRequestCtas(
            (int) $request->loan_application_id,
            (int) $request->id,
        );

        return $request->fresh();
    }

    public function reject(
        LoanApplicationDocumentRequest $request,
        User $admin,
        string $notes,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'status' => 'rejected',
            'admin_notes' => $notes,
        ]);

        $request->uploads()->where('status', 'pending_review')->update([
            'status' => 'rejected',
            'notes' => $notes,
        ]);

        $application = $request->application()->with('customer')->first();
        $customer = $application?->customer;

        if ($customer) {
            $uploadUrl = route('site.borrower.application', $application);

            $this->notifier->notifyCustomer($customer, 'application_document_request', [
                'name' => $customer->first_name ?? 'Customer',
                'application_number' => $application->application_number,
                'label' => $request->label,
                'instructions' => "Please re-upload. Reason: {$notes}",
                'due_date' => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
                'upload_url' => $uploadUrl,
                '_fallback_body' => "Hi {$customer->first_name}, your upload for \"{$request->label}\" was rejected. {$notes}. Open your application to re-upload: {$uploadUrl}",
                '_fallback_subject' => 'Document upload rejected — action required',
            ]);

            $this->notifier->notifyInApp(
                $customer,
                __('borrower.notifications.document_request_body', [
                    'application' => $application->application_number,
                    'label' => $request->label,
                ]).' '.$notes,
                'document_request',
                'application_document_request',
                __('borrower.notifications.document_request_title'),
                $uploadUrl,
                __('borrower.notifications.document_request_cta'),
            );
        }

        $this->syncApplicationStatus($application->fresh());

        return $request->fresh();
    }

    public function syncApplicationStatus(LoanApplication $application): void
    {
        if (in_array($application->status, ['rejected', 'approved', 'disbursed'], true)) {
            return;
        }

        $needsAction = $application->documentRequests()
            ->whereIn('status', ['pending', 'rejected'])
            ->exists();

        if ($needsAction) {
            if ($application->status !== 'pending_documents') {
                $application->update(['status' => 'pending_documents']);
            }

            return;
        }

        if ($application->status !== 'pending_documents') {
            return;
        }

        $status = match ($application->current_stage) {
            'credit_appraisal', 'pre_approval', 'approval', 'disbursement' => 'under_review',
            'screening' => 'submitted',
            default => 'submitted',
        };

        $application->update(['status' => $status]);
    }

    public function openRequestsForCustomer(Customer $customer): Collection
    {
        return LoanApplicationDocumentRequest::query()
            ->where(function ($q) use ($customer) {
                $q->where('subject_customer_id', $customer->id)
                    ->orWhere(function ($inner) use ($customer) {
                        $inner->whereNull('subject_customer_id')
                            ->whereHas('application', fn ($aq) => $aq->where('customer_id', $customer->id));
                    })
                    ->orWhere(function ($inner) use ($customer) {
                        // Application owner can see member + guarantor asks (assist).
                        $inner->whereIn('subject_kind', ['member', 'guarantor'])
                            ->whereHas('application', fn ($aq) => $aq->where('customer_id', $customer->id));
                    });
            })
            ->whereIn('status', ['pending', 'rejected'])
            ->with(['application.product', 'subjectCustomer'])
            ->latest()
            ->get();
    }

    public function customerCanViewApplication(Customer $customer, LoanApplication $application): bool
    {
        if ((int) $application->customer_id === (int) $customer->id) {
            return true;
        }

        $application->loadMissing('loanGroup.members');

        return (bool) $application->loanGroup?->members->contains(
            fn ($member) => (int) $member->customer_id === (int) $customer->id
                && ($member->member_status ?? 'active') === 'active'
        );
    }

    /**
     * True when the logged-in customer is the application owner helping a
     * member or guarantor fulfill a request targeted at someone else.
     */
    public function borrowerIsAssisting(Customer $customer, LoanApplicationDocumentRequest $request): bool
    {
        $request->loadMissing('application');

        if ((int) $request->application?->customer_id !== (int) $customer->id) {
            return false;
        }

        if (! in_array((string) ($request->subject_kind ?? ''), ['member', 'guarantor'], true)) {
            return false;
        }

        if ($request->subject_customer_id && (int) $request->subject_customer_id === (int) $customer->id) {
            return false;
        }

        return true;
    }

    /** Profile deep-links only for the subject; assistants upload on the application. */
    public function isProfileGuidedForCustomer(Customer $customer, LoanApplicationDocumentRequest $request): bool
    {
        if ($this->borrowerIsAssisting($customer, $request)) {
            return false;
        }

        return $this->isProfileGuidedRequest($request);
    }

    public function customerCanFulfillRequest(Customer $customer, LoanApplicationDocumentRequest $request): bool
    {
        $request->loadMissing('application');

        if ($request->subject_customer_id) {
            if ((int) $request->subject_customer_id === (int) $customer->id) {
                return true;
            }
        } elseif ((int) $request->application?->customer_id === (int) $customer->id) {
            return true;
        }

        // Primary borrower / group leader can assist members and guarantors.
        return in_array((string) ($request->subject_kind ?? ''), ['member', 'guarantor'], true)
            && (int) $request->application?->customer_id === (int) $customer->id;
    }

    public function pendingReviewCount(): int
    {
        return LoanApplicationDocumentRequest::where('status', 'uploaded')->count();
    }

    /**
     * Files to show for a request: loan uploads first, else profile-linked docs.
     *
     * @return Collection<int, CustomerDocument>
     */
    public function displayDocumentsForRequest(LoanApplicationDocumentRequest $request, Customer $customer): Collection
    {
        $request->loadMissing('uploads');
        if ($request->uploads->isNotEmpty()) {
            return $request->uploads->values();
        }

        if ($this->borrowerActionKind($request) !== 'income'
            && ! $this->isProfileGuidedRequest($request)) {
            return collect();
        }

        $codes = app(ProfileRevisionService::class)->documentCodesForLabel((string) $request->label);
        if ($codes === []) {
            if ($this->borrowerActionKind($request) === 'income') {
                $codes = ['bank_statement', 'mobile_money_statement', 'salary_slip', 'employment_contract'];
            } else {
                return collect();
            }
        }

        return app(ProfileDocumentService::class)
            ->latestByCodes($customer, $codes)
            ->values();
    }

    /**
     * When the borrower uploads profile-guided income docs, mark matching open
     * underwriting requests as uploaded so the loan profile stops showing Pending.
     *
     * @param  list<string>  $uploadedCodes
     */
    public function markIncomeRequestsUploadedFromProfile(Customer $customer, array $uploadedCodes): int
    {
        $uploadedCodes = array_values(array_filter(array_map('strval', $uploadedCodes)));
        if ($uploadedCodes === []) {
            return 0;
        }

        $revision = app(ProfileRevisionService::class);
        $marked = 0;

        $requests = LoanApplicationDocumentRequest::query()
            ->where(function ($q) use ($customer) {
                $q->where('subject_customer_id', $customer->id)
                    ->orWhere(function ($inner) use ($customer) {
                        $inner->where(function ($kind) {
                            $kind->whereNull('subject_kind')
                                ->orWhere('subject_kind', 'borrower');
                        })->where(function ($subject) {
                            $subject->whereNull('subject_customer_id')
                                ->orWhere('subject_customer_id', 0);
                        })->whereHas('application', fn ($app) => $app->where('customer_id', $customer->id));
                    });
            })
            ->whereHas('application', function ($app) {
                $app->whereNotIn('status', ['withdrawn', 'rejected', 'disbursed']);
            })
            ->whereIn('status', ['pending', 'rejected'])
            ->with('application')
            ->get();

        foreach ($requests as $request) {
            if (! $this->isSubjectOfRequest($customer, $request)) {
                continue;
            }
            $kind = $this->borrowerActionKind($request);
            if (! in_array($kind, ['income', 'residence', 'business'], true)) {
                continue;
            }

            $labelCodes = $revision->documentCodesForLabel((string) $request->label);
            $matches = $labelCodes === []
                ? $kind === 'income'
                : count(array_intersect($labelCodes, $uploadedCodes)) > 0;

            if (! $matches) {
                continue;
            }

            $request->update([
                'status' => 'uploaded',
                'admin_notes' => null,
            ]);
            $marked++;

            if ($application = $request->application) {
                $this->syncApplicationStatus($application->fresh());
                app(NotificationCtaService::class)->consumeDocumentRequestCtas(
                    (int) $application->id,
                    (int) $request->id,
                );
            }
        }

        if ($marked > 0) {
            $revision->clearForTarget($customer->fresh(), 'income');
        }

        return $marked;
    }

    private function isBusinessProfileLabel(string $label): bool
    {
        $label = mb_strtolower($label);

        return str_contains($label, 'business registration')
            || str_contains($label, 'business photo')
            || str_contains($label, 'business licence')
            || str_contains($label, 'business license')
            || str_contains($label, 'workshop')
            || str_contains($label, 'tin')
            || str_contains($label, 'vat certificate');
    }

    /**
     * When the borrower pledges a profile asset onto this loan, mark matching
     * open collateral requests as uploaded.
     */
    public function markCollateralRequestsUploadedFromProfile(Customer $customer, LoanApplication $application): int
    {
        $marked = 0;

        $requests = LoanApplicationDocumentRequest::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', ['pending', 'rejected'])
            ->get();

        foreach ($requests as $request) {
            if ($this->borrowerActionKind($request) !== 'collateral') {
                continue;
            }
            if (! $this->customerCanFulfillRequest($customer, $request)) {
                continue;
            }

            $request->update([
                'status' => 'uploaded',
                'admin_notes' => null,
            ]);
            $marked++;

            app(NotificationCtaService::class)->consumeDocumentRequestCtas(
                (int) $application->id,
                (int) $request->id,
            );
        }

        if ($marked > 0) {
            $this->syncApplicationStatus($application->fresh());
        }

        return $marked;
    }

    /**
     * In-app reminder for open document requests due tomorrow.
     * Lists the requested documents and links to the application.
     */
    public function sendDueTomorrowReminders(): int
    {
        $start = now()->addDay()->startOfDay();
        $end = now()->addDay()->endOfDay();
        $dueOn = $start->toDateString();
        $sent = 0;

        LoanApplicationDocumentRequest::query()
            ->with(['application.customer'])
            ->whereIn('status', ['pending', 'rejected'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('id')
            ->get()
            ->groupBy('loan_application_id')
            ->each(function (Collection $requests) use (&$sent, $dueOn) {
                $application = $requests->first()?->application;
                $customer = $application?->customer;
                if (! $application || ! $customer) {
                    return;
                }

                if (in_array($application->status, ['withdrawn', 'rejected', 'disbursed', 'expired'], true)) {
                    return;
                }

                $template = 'application_document_request_reminder_1';
                if (NotificationLog::query()
                    ->where('customer_id', $customer->id)
                    ->where('channel', 'in_app')
                    ->where('template', $template)
                    ->where('meta->loan_application_id', $application->id)
                    ->where('meta->due_on', $dueOn)
                    ->exists()) {
                    return;
                }

                $count = $requests->count();
                $labels = $requests->pluck('label')->filter()->take(7)->implode(', ');
                $suffix = $count > 7 ? '…' : '';
                $dueDate = optional($requests->first()->due_at)
                    ->timezone(config('app.timezone'))
                    ->format('d M Y');

                $params = [
                    'application' => $application->application_number,
                    'count' => $count,
                    'labels' => $labels.$suffix,
                    'date' => $dueDate ?: $dueOn,
                ];

                $uploadUrl = route('site.borrower.application', $application);

                $this->notifier->notifyInApp(
                    $customer,
                    __('borrower.notifications.document_request_reminder_body', $params),
                    'document_request',
                    $template,
                    __('borrower.notifications.document_request_reminder_title'),
                    $uploadUrl,
                    __('borrower.notifications.document_request_cta'),
                    [
                        'title_key' => 'borrower.notifications.document_request_reminder_title',
                        'body_key' => 'borrower.notifications.document_request_reminder_body',
                        'params' => $params,
                        'loan_application_id' => $application->id,
                        'due_on' => $dueOn,
                    ],
                );

                $sent++;
            });

        return $sent;
    }
}
