<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\Lender;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\Celebration;
use App\Support\NationalIdValidator;
use App\Support\PartnerPerformanceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Drives the category-first partner profile hub (affiliate / supplier / vendor / investor),
 * mirroring the borrower profile hub UX: hub shows categories -> user opens a category ->
 * views/edits accordion cards inside it.
 */
class PartnerProfileService
{
    /** All possible section keys (subset shown per partner type). */
    public const SECTIONS = ['personal', 'company', 'face', 'residence', 'activity', 'payment'];

    /**
     * Profile sections for this partner.
     * Company (insurance + company affiliate/valuer): personal, company, residence (address), payment — no face/activity.
     * Individual (affiliate/valuer person): personal, face, residence, payment — no activity/company.
     *
     * @return list<string>
     */
    public function sectionsFor(Partner|Lender $entity): array
    {
        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            return ['personal', 'company', 'residence', 'payment'];
        }

        return ['personal', 'face', 'residence', 'payment'];
    }

    public function frontPhotoPath(Partner|Lender $entity): ?string
    {
        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            return null;
        }

        $meta = $entity->metadata ?? [];
        $front = $meta['face_captures']['front'] ?? null;

        if (filled($front)) {
            return $front;
        }

        if ($entity instanceof Partner && filled($entity->affiliate_selfie_path)) {
            return $entity->affiliate_selfie_path;
        }

        return null;
    }

    public function frontPhotoUrl(Partner|Lender $entity): ?string
    {
        $path = $this->frontPhotoPath($entity);

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * Borrower face-wizard payload for a partner, using FaceVerificationService
     * angle keys/copy and partner metadata persistence.
     *
     * @return array{
     *   customer: object,
     *   angles: array<string, mixed>,
     *   wizard: array{order: list<string>, current_index: int, current_angle: string|null, complete: bool, total: int},
     *   photos: Collection,
     *   steps: list<array<string, mixed>>,
     *   complete: bool
     * }
     */
    public function faceWizardViewData(Partner|Lender $entity): array
    {
        $faces = app(FaceVerificationService::class);
        $keys = $this->requiredFaceAngleKeys($entity);
        $steps = $this->faceWizardSteps($entity);
        $wizard = $this->faceWizardState($entity, $steps);

        return [
            'customer' => (object) [
                'face_verification_status' => $wizard['complete'] ? 'pending' : 'incomplete',
            ],
            'angles' => collect($faces->angles())->only($keys)->all(),
            'wizard' => $wizard,
            'photos' => collect(),
            'steps' => $steps,
            'complete' => $wizard['complete'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function storeFaceAngle(Partner|Lender $entity, string $angle, UploadedFile $file): array
    {
        $this->assertFaceSectionAvailable($entity);
        $angle = $this->normalizeFaceAngleKey($angle);
        if (! in_array($angle, $this->requiredFaceAngleKeys($entity), true)) {
            throw new \InvalidArgumentException('Invalid face capture angle.');
        }

        $storageKey = $this->faceStorageKey($angle);
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];
        $captures = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
        $previous = $captures[$storageKey] ?? null;
        $path = $file->store($this->storageFolder($entity), 'public');

        if (filled($previous) && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $captures[$storageKey] = $path;
        $meta['face_captures'] = $captures;
        $updates = array_merge(['metadata' => $meta], $this->derivedFaceUpdates($entity, $captures, $meta));
        $entity->update($updates);
        $entity->refresh();

        $steps = $this->faceWizardSteps($entity);
        $wizard = $this->faceWizardState($entity, $steps);
        $uploaded = collect($steps)->where('done', true)->count();
        $progress = [
            'required' => $wizard['total'],
            'uploaded' => $uploaded,
            'percent' => $wizard['total'] > 0 ? (int) round(($uploaded / $wizard['total']) * 100) : 0,
            'complete' => $wizard['complete'],
        ];

        return [
            'ok' => true,
            'angle' => $angle,
            'previewUrl' => asset('storage/'.$path),
            'progress' => $progress,
            'wizard' => $wizard,
            'status' => $wizard['complete'] ? 'pending' : 'incomplete',
            'message' => __('borrower.document_upload.saved'),
            'complete' => $wizard['complete'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeFaceAngle(Partner|Lender $entity, string $angle): array
    {
        $this->assertFaceSectionAvailable($entity);
        $angle = $this->normalizeFaceAngleKey($angle);
        if (! in_array($angle, $this->requiredFaceAngleKeys($entity), true)) {
            throw new \InvalidArgumentException('Invalid face capture angle.');
        }

        $storageKey = $this->faceStorageKey($angle);
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];
        $captures = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
        $previous = $captures[$storageKey] ?? null;

        if ($angle === 'front' && blank($previous) && $entity instanceof Partner) {
            $previous = $entity->affiliate_selfie_path;
        }

        if (blank($previous)) {
            throw new \InvalidArgumentException('No photo found for this angle.');
        }

        Storage::disk('public')->delete($previous);
        unset($captures[$storageKey]);
        $meta['face_captures'] = $captures;
        $updates = ['metadata' => $meta];
        if ($entity instanceof Partner && $angle === 'front') {
            $updates['affiliate_selfie_path'] = null;
        }
        $entity->update($updates);
        $entity->refresh();

        $wizard = $this->faceWizardState($entity);
        $uploaded = collect($this->faceWizardSteps($entity))->where('done', true)->count();

        return [
            'ok' => true,
            'angle' => $angle,
            'progress' => [
                'required' => $wizard['total'],
                'uploaded' => $uploaded,
                'percent' => $wizard['total'] > 0 ? (int) round(($uploaded / $wizard['total']) * 100) : 0,
                'complete' => $wizard['complete'],
            ],
            'wizard' => $wizard,
            'status' => $wizard['complete'] ? 'pending' : 'incomplete',
            'message' => 'Photo removed.',
            'complete' => $wizard['complete'],
        ];
    }

    /** @return array{celebrate: bool} */
    public function submitFace(Partner|Lender $entity): array
    {
        $this->assertFaceSectionAvailable($entity);
        $wizard = $this->faceWizardState($entity);
        if (! $wizard['complete']) {
            throw new \InvalidArgumentException('Upload all required face photos before submitting.');
        }

        $alreadyCelebrated = filled(($entity->metadata ?? [])['profile_complete_celebrated_at'] ?? null);
        $justCompleted = $this->isComplete($entity) && ! $alreadyCelebrated;
        if ($justCompleted) {
            $this->rememberProfileCompleteCelebration($entity);
            Celebration::flashOne('profile_complete');
            $this->finalizeRegistration($entity);
        }

        return ['celebrate' => $justCompleted];
    }

    /** @return list<string> */
    public function requiredFaceAngleKeys(Partner|Lender $entity): array
    {
        $keys = app(FaceVerificationService::class)->requiredAngleKeys();
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];

        if ((bool) ($identity['no_physical_nida_card'] ?? false)) {
            return array_values(array_filter($keys, fn (string $key) => $key !== 'holding_nida'));
        }

        return $keys;
    }

    /** @return list<array<string, mixed>> */
    public function faceWizardSteps(Partner|Lender $entity): array
    {
        $captures = $this->faceCaptures($entity);

        return collect($this->requiredFaceAngleKeys($entity))->map(function (string $key) use ($entity, $captures) {
            $path = $captures[$this->faceStorageKey($key)] ?? null;
            if ($key === 'front' && blank($path)) {
                $path = $this->frontPhotoPath($entity);
            }

            return [
                'key' => $key,
                'label' => __('borrower.face_verification_page.angles.'.$key.'.label'),
                'step_title' => __('borrower.face_verification_page.angles.'.$key.'.label'),
                'instruction' => __('borrower.face_verification_page.angles.'.$key.'.instruction'),
                'pose' => match ($key) {
                    'left' => 'left',
                    'right' => 'right',
                    default => 'front',
                },
                'done' => filled($path),
                'previewUrl' => filled($path) ? asset('storage/'.$path) : null,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>|null  $steps
     * @return array{order: list<string>, current_index: int, current_angle: string|null, complete: bool, total: int}
     */
    public function faceWizardState(Partner|Lender $entity, ?array $steps = null): array
    {
        $steps ??= $this->faceWizardSteps($entity);
        $order = array_values(array_map(fn (array $step) => $step['key'], $steps));
        $total = count($order);
        $currentIndex = 0;

        foreach ($steps as $index => $step) {
            if (! ($step['done'] ?? false)) {
                $currentIndex = $index;
                break;
            }
            $currentIndex = $index + 1;
        }

        $complete = $total > 0 && collect($steps)->every(fn (array $step) => (bool) ($step['done'] ?? false));
        $activeIndex = min($currentIndex, max($total - 1, 0));

        return [
            'order' => $order,
            'current_index' => $activeIndex,
            'current_angle' => $order[$activeIndex] ?? null,
            'complete' => $complete,
            'total' => $total,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function hubCards(Partner|Lender $entity, string $profileRouteName): array
    {
        $meta = [
            'personal' => [
                'icon' => '👤',
                'label' => __('site.partner_account.personal_section'),
                'hint' => ($entity instanceof Partner && $entity->isCompanyApplicant())
                    ? __('site.partner_account.hint_personal_company')
                    : __('site.partner_account.hint_personal'),
            ],
            'company' => ['icon' => '🏢', 'label' => __('site.partner_account.company_section'), 'hint' => __('site.partner_account.hint_company')],
            'face' => ['icon' => '🤳', 'label' => __('site.partner_account.face_section'), 'hint' => __('site.partner_account.hint_face')],
            'residence' => [
                'icon' => '🏠',
                'label' => ($entity instanceof Partner && $entity->isCompanyApplicant())
                    ? __('site.partner_account.company_address_section')
                    : __('site.partner_account.residence_section'),
                'hint' => ($entity instanceof Partner && $entity->isCompanyApplicant())
                    ? __('site.partner_account.hint_company_address')
                    : __('site.partner_account.hint_residence'),
            ],
            'activity' => ['icon' => '💼', 'label' => __('site.partner_account.activity_section'), 'hint' => __('site.partner_account.hint_activity')],
            'payment' => ['icon' => '💳', 'label' => __('site.partner_account.payment_section'), 'hint' => __('site.partner_account.hint_payment')],
        ];

        return collect($this->sectionsFor($entity))->map(function (string $key) use ($meta, $entity, $profileRouteName) {
            $status = $this->sectionStatus($entity, $key);
            $info = $meta[$key];

            return [
                'key' => $key,
                'icon' => $info['icon'],
                'label' => $info['label'],
                'description' => $status['complete'] ? null : $info['hint'],
                'status' => $status['status'],
                'status_label' => $this->statusLabel($status['status']),
                'action_label' => $status['complete'] ? __('borrower.profile.hub.view_edit') : __('borrower.profile.hub.add'),
                'url' => route($profileRouteName, ['section' => $key]),
                'required' => true,
                'count' => null,
                'missing' => $status['missing'] ?? [],
                'progress' => $status['progress'] ?? null,
            ];
        })->when(
            $entity instanceof Partner && $entity->isAffiliate(),
            function (Collection $cards) use ($entity, $profileRouteName) {
                $premium = $entity->isPremiumAffiliate();
                $terms = app(AffiliateTermsService::class);
                $accepted = $terms->hasAccepted($entity);
                $acceptance = $terms->latestAcceptance($entity);
                $cards->push([
                    'key' => 'agreement',
                    'icon' => '📜',
                    'label' => __('site.affiliate_portal.agreement_terms_section'),
                    'description' => $accepted
                        ? __('site.affiliate_portal.accepted_on', [
                            'date' => $acceptance?->accepted_at?->format('d M Y') ?: '—',
                        ])
                        : __('site.affiliate_portal.lock_terms_body'),
                    'status' => $accepted ? 'complete' : 'in_progress',
                    'status_label' => $this->statusLabel($accepted ? 'complete' : 'in_progress'),
                    'action_label' => __('borrower.profile.hub.view_edit'),
                    'url' => route($profileRouteName, ['section' => 'agreement']),
                    'required' => true,
                    'count' => null,
                    'missing' => [],
                ]);

                if (! $premium) {
                    $active = app(AffiliateMembershipService::class)->isActive($entity);
                    $cards->push([
                        'key' => 'membership',
                        'icon' => '🪪',
                        'label' => __('site.affiliate_portal.membership_title'),
                        'description' => __('site.affiliate_portal.membership_hub_hint'),
                        'status' => $active ? 'complete' : 'in_progress',
                        'status_label' => $this->statusLabel($active ? 'complete' : 'in_progress'),
                        'action_label' => __('borrower.profile.hub.view_edit'),
                        'url' => route($profileRouteName, ['section' => 'membership']),
                        'required' => true,
                        'count' => null,
                        'missing' => [],
                    ]);
                }

                return $cards;
            }
        )->values()->all();
    }

    /**
     * @return array{
     *   status: string,
     *   complete: bool,
     *   missing: list<array{key: string, label: string}>,
     *   progress: array{done: int, total: int, remaining: int}
     * }
     */
    public function sectionStatus(Partner|Lender $entity, string $key): array
    {
        $meta = $entity->metadata ?? [];

        return match ($key) {
            'personal' => $this->personalStatus($entity, $meta),
            'company' => $this->companyStatus($entity),
            'face' => $this->faceStatus($entity, $meta),
            'residence' => $this->residenceStatus($meta),
            'activity' => $this->activityStatus($meta),
            'payment' => $this->paymentStatus($meta),
            default => $this->statusFromItems([]),
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function sectionGaps(Partner|Lender $entity, string $key): array
    {
        return $this->sectionStatus($entity, $key)['missing'] ?? [];
    }

    public function completionPercent(Partner|Lender $entity): int
    {
        $sections = $this->sectionsFor($entity);
        if ($sections === []) {
            return 100;
        }

        $complete = collect($sections)
            ->map(fn (string $key) => $this->sectionStatus($entity, $key)['complete'] ? 1 : 0);

        return (int) round(((float) $complete->avg()) * 100);
    }

    public function remainingItemCount(Partner|Lender $entity): int
    {
        $count = 0;
        foreach ($this->sectionsFor($entity) as $key) {
            $count += count($this->sectionGaps($entity, $key));
        }

        return $count;
    }

    public function firstIncompleteSection(Partner|Lender $entity): ?string
    {
        foreach ($this->sectionsFor($entity) as $key) {
            if (! ($this->sectionStatus($entity, $key)['complete'] ?? false)) {
                return $key;
            }
        }

        return null;
    }

    public function hasPayoutAccount(Partner|Lender $entity): bool
    {
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];

        return (bool) ($this->paymentStatus($meta)['complete'] ?? false);
    }

    public function payoutAccountLabel(Partner|Lender $entity): string
    {
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];
        $payout = is_array($meta['payout_account'] ?? null) ? $meta['payout_account'] : [];
        $type = (string) ($payout['type'] ?? '');
        if ($type === 'mobile_money') {
            $number = (string) ($payout['mobile_number'] ?? '');
            $tail = $number !== '' ? substr($number, -4) : '';

            return trim(($payout['mobile_provider'] ?? __('site.affiliate_portal.payout_account')).($tail !== '' ? ' · ···'.$tail : ''));
        }
        if ($type === 'bank') {
            $number = (string) ($payout['account_number'] ?? '');
            $tail = $number !== '' ? substr($number, -4) : '';

            return trim(($payout['bank_name'] ?? __('site.affiliate_portal.payout_account')).($tail !== '' ? ' · ···'.$tail : ''));
        }

        return $this->payoutAccountName($entity) ?: '—';
    }

    /**
     * Document upload types for the documents tab (company vs personal).
     *
     * @return array<string, string>
     */
    public function documentTypesFor(Partner|Lender $entity): array
    {
        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            return [
                'brela' => __('site.partner_account.doc_types.brela'),
                'tin_certificate' => __('site.partner_account.doc_types.tin_certificate'),
                'business_licence' => __('site.partner_account.doc_types.business_licence'),
                'vat_certificate' => __('site.partner_account.doc_types.vat_certificate'),
                'national_id_front' => __('site.partner_account.doc_types.national_id_front'),
                'national_id_back' => __('site.partner_account.doc_types.national_id_back'),
                'other' => __('site.partner_account.doc_types.other'),
            ];
        }

        return [
            'national_id_front' => __('site.partner_account.doc_types.national_id_front'),
            'national_id_back' => __('site.partner_account.doc_types.national_id_back'),
            'other' => __('site.partner_account.doc_types.other'),
        ];
    }

    /** @return array{celebrate: bool} */
    public function updateSection(Partner|Lender $entity, string $section, Request $request): array
    {
        if (! in_array($section, $this->sectionsFor($entity), true)) {
            throw new \InvalidArgumentException("Section [{$section}] is not available for this partner.");
        }

        $wasComplete = $this->isComplete($entity);

        match ($section) {
            'personal' => $this->savePersonal($entity, $request),
            'company' => null, // admin-managed; read-only in portal
            'face' => $this->saveFace($entity, $request),
            'residence' => $this->saveResidence($entity, $request),
            'activity' => $this->saveActivity($entity, $request),
            'payment' => $this->savePayment($entity, $request),
            default => throw new \InvalidArgumentException("Unknown partner profile section [{$section}]."),
        };

        $entity->refresh();
        $justCompleted = ! $wasComplete && $this->isComplete($entity);
        if ($justCompleted) {
            $this->rememberProfileCompleteCelebration($entity);
            if (! $this->isAutosaveRequest($request)) {
                Celebration::flashOne('profile_complete');
            }
            $this->finalizeRegistration($entity);
        }

        return ['celebrate' => $justCompleted];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSavedPayload(Partner|Lender $entity, string $section, bool $celebrate): array
    {
        $payload = [
            'ok' => true,
            'saved' => true,
            'section' => $section,
        ];

        if ($celebrate) {
            $payload['celebrate'] = $this->profileCompleteCelebrationCopy();
        }

        $payload['completion'] = $this->autosaveCompletion($entity, $section);

        if ($entity instanceof Partner && $entity->isAffiliate()) {
            $affiliates = app(AffiliateService::class);
            $links = $affiliates->messageContext($entity);
            $payload['promo'] = [
                'code' => $links['affiliate_code'],
                'link' => $links['affiliate_link'],
                'message' => $affiliates->shareInvitation($entity),
                'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.urlencode($links['affiliate_link']),
            ];
        }

        return $payload;
    }

    /**
     * Canonical Partner completion snapshot for autosave JSON.
     * Browser only renders this payload; it must not invent a second engine.
     *
     * @return array{
     *     percent: int,
     *     remaining: int,
     *     section: string,
     *     section_remaining: int,
     *     section_done: int,
     *     section_total: int,
     *     section_complete: bool,
     *     gaps: list<array{key: string, label: string, url: string}>,
     *     categories: array<string, array{remaining: int, complete: bool}>,
     *     cards: array<string, bool>
     * }
     */
    public function autosaveCompletion(Partner|Lender $entity, string $section): array
    {
        $status = $this->sectionStatus($entity, $section);
        $categories = [];
        foreach ($this->sectionsFor($entity) as $key) {
            $row = $this->sectionStatus($entity, $key);
            $categories[$key] = [
                'remaining' => count($row['missing'] ?? []),
                'complete' => (bool) ($row['complete'] ?? false),
            ];
        }

        return [
            'percent' => $this->completionPercent($entity),
            'remaining' => $this->remainingItemCount($entity),
            'section' => $section,
            'section_remaining' => (int) ($status['progress']['remaining'] ?? 0),
            'section_done' => (int) ($status['progress']['done'] ?? 0),
            'section_total' => (int) ($status['progress']['total'] ?? 0),
            'section_complete' => (bool) ($status['complete'] ?? false),
            'gaps' => array_map(static fn (array $gap) => [
                'key' => (string) ($gap['key'] ?? ''),
                'label' => (string) ($gap['label'] ?? ''),
                'url' => '',
            ], $status['missing'] ?? []),
            'categories' => $categories,
            'cards' => [
                'section-'.$section => (bool) ($status['complete'] ?? false),
            ],
        ];
    }

    /** @return array{tone: string, title: string, message: string, okLabel: string} */
    public function profileCompleteCelebrationCopy(): array
    {
        return [
            'tone' => 'success',
            'title' => __('site.partner_account.profile_complete_title'),
            'message' => __('site.partner_account.profile_complete_body'),
            'okLabel' => __('site.partner_account.profile_complete_cta'),
        ];
    }

    /**
     * Identity document types allowed for this partner's country.
     * Tanzania stays NIDA-only unless Settings adds more codes.
     *
     * @return array<string, string>
     */
    public function allowedIdentityTypes(Partner|Lender $entity): array
    {
        $country = strtoupper((string) (
            $entity->country_code
            ?? $entity->country
            ?? session('country')
            ?? app(PartnerCodeService::class)->defaultCountryCode()
        ));

        $configured = Setting::get('partners.identity_document_types');
        $codes = is_array($configured[$country] ?? null)
            ? array_values(array_filter($configured[$country]))
            : ($country === 'TZ' ? ['nida'] : ['nida']);

        if ($codes === []) {
            $codes = ['nida'];
        }

        $catalog = $this->identityTypeCatalog();

        return array_filter(
            array_map(fn (string $code) => $catalog[$code] ?? null, array_combine($codes, $codes) ?: []),
            fn ($label) => filled($label)
        );
    }

    /** @return array<string, string> */
    public function identityTypeCatalog(): array
    {
        $labels = [
            'nida' => __('site.partner_account.identity_type_nida'),
            'passport' => __('site.partner_account.identity_type_passport'),
            'driving_license' => __('site.partner_account.identity_type_driving_license'),
            'voter_id' => __('site.partner_account.identity_type_voter_id'),
            'other_id' => __('site.partner_account.identity_type_other'),
            'residence_permit' => __('site.partner_account.identity_type_residence_permit'),
        ];

        try {
            $rows = DocumentType::query()
                ->where('is_active', true)
                ->whereIn('code', ['passport', 'driving_license', 'voter_id', 'other_id', 'residence_permit'])
                ->get();
            foreach ($rows as $row) {
                $labels[$row->code] = $row->localizedName();
            }
        } catch (\Throwable) {
            // Catalog table may be absent in a fresh test DB — keep lang fallbacks.
        }

        return $labels;
    }

    private function isAutosaveRequest(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || (bool) $request->header('X-KF-Autosave');
    }

    private function rememberProfileCompleteCelebration(Partner|Lender $entity): void
    {
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];
        if (filled($meta['profile_complete_celebrated_at'] ?? null)) {
            return;
        }

        $meta['profile_complete_celebrated_at'] = now()->toIso8601String();
        $entity->forceFill(['metadata' => $meta])->save();
    }

    public function isComplete(Partner|Lender $entity): bool
    {
        return $this->completionPercent($entity) >= 100;
    }

    /**
     * If admin already captured NIDA on create, copy it into the portal identity
     * record so the supplier is not asked to type the number again.
     */
    public function hydrateCanonicalIdentity(Partner|Lender $entity): void
    {
        if (! $entity instanceof Partner) {
            return;
        }

        $meta = is_array($entity->metadata) ? $entity->metadata : [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $changed = false;

        $canonical = trim((string) ($identity['national_id'] ?? $entity->getAttribute('national_id') ?? ''));
        if ($canonical !== '' && blank($identity['national_id'] ?? null)) {
            $identity['national_id'] = NationalIdValidator::format($canonical) ?? $canonical;
            $changed = true;
        }

        $docLabels = [
            'national_id_front' => __('site.partner_account.doc_types.national_id_front'),
            'national_id_back' => __('site.partner_account.doc_types.national_id_back'),
        ];
        foreach ($docLabels as $metaKey => $label) {
            if (filled($identity[$metaKey] ?? null)) {
                continue;
            }
            $doc = $entity->documents()
                ->where(function ($q) use ($metaKey, $label) {
                    $q->where('doc_type', $metaKey)->orWhere('label', $label);
                })
                ->latest()
                ->first();
            if ($doc && filled($doc->file_path)) {
                $identity[$metaKey] = $doc->file_path;
                $changed = true;
            }
        }

        if (! $changed) {
            return;
        }

        $meta['identity'] = $identity;
        $entity->forceFill(['metadata' => $meta])->save();
    }

    /**
     * Why this partner cannot receive, accept, or start a job yet.
     *
     * Paying types (Settings → Partner membership) need a complete profile and
     * an active membership. Governed types also need current Terms and must not
     * be performance-suspended. Other types only need a complete profile.
     *
     * @return 'profile'|'payment'|'terms'|'performance'|'compliance'|'suspended'|'inactive'|null
     */
    public function jobBlockReason(Partner $partner): ?string
    {
        $status = (string) ($partner->status ?? '');
        if ($status === 'inactive') {
            return 'inactive';
        }
        if ($status === 'suspended') {
            return match ((string) ($partner->suspend_kind ?? 'admin')) {
                'performance' => 'performance',
                'compliance', 'fraud' => 'compliance',
                default => 'suspended',
            };
        }
        if (($partner->performance_status ?? '') === PartnerPerformanceStatus::SUSPENDED) {
            return 'performance';
        }

        if (! $this->isComplete($partner)) {
            return 'profile';
        }

        $terms = app(PartnerTermsService::class);
        if ($terms->appliesTo($partner) && ! $terms->hasSatisfiedTerms($partner)) {
            return 'terms';
        }

        if ($partner->isAffiliate()) {
            return app(AffiliateMembershipService::class)->isActive($partner) ? null : 'payment';
        }

        $membership = app(PartnerMembershipService::class);
        if ($membership->requiresPayment($partner) && ! $membership->isActive($partner)) {
            return 'payment';
        }

        return null;
    }

    /**
     * @return array{can_receive: bool, reason: ?string, reason_label: string}
     */
    public function jobEligibility(Partner $partner): array
    {
        $reason = $this->jobBlockReason($partner);

        return [
            'can_receive' => $reason === null && ($partner->status ?? '') === 'active',
            'reason' => $reason,
            'reason_label' => $reason
                ? __('site.partner_portal.job_block_'.$reason)
                : __('site.partner_portal.can_receive_jobs_yes'),
        ];
    }

    public function canReceiveJobs(Partner $partner): bool
    {
        $partner->refresh();

        return $this->jobBlockReason($partner) === null;
    }

    /**
     * @param  Collection<int, Partner>  $partners
     * @return Collection<int, Partner>
     */
    public function onlyReadyForJobs(Collection $partners): Collection
    {
        return $partners
            ->filter(fn (Partner $partner) => $this->canReceiveJobs($partner))
            ->values();
    }

    public function assertCanReceiveJobs(Partner $partner, string $field = 'vendor_id'): void
    {
        $partner->refresh();
        $reason = $this->jobBlockReason($partner);
        if ($reason === 'profile') {
            throw ValidationException::withMessages([
                $field => __('site.partner_portal.job_requires_profile'),
            ]);
        }
        if ($reason === 'payment') {
            throw ValidationException::withMessages([
                $field => __('site.partner_portal.job_requires_payment'),
            ]);
        }
        if ($reason === 'terms') {
            throw ValidationException::withMessages([
                $field => __('site.partner_portal.job_requires_terms'),
            ]);
        }
        if (in_array($reason, ['performance', 'compliance', 'suspended', 'inactive'], true)) {
            throw ValidationException::withMessages([
                $field => __('site.partner_portal.job_block_'.$reason),
            ]);
        }
    }

    public function payoutAccountName(Partner|Lender $entity): string
    {
        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            return trim((string) ($entity->legal_name ?: $entity->name));
        }

        return trim((string) ($entity->name ?? ''));
    }

    /**
     * Portal login can exist before the card. The verification card goes live
     * once the partner finishes profile (and pays membership when required).
     */
    private function finalizeRegistration(Partner|Lender $entity): void
    {
        if (! $entity instanceof Partner) {
            return;
        }

        if (($entity->status ?? '') !== 'active') {
            return;
        }

        if ($entity->isAffiliate()) {
            return;
        }

        $membership = app(PartnerMembershipService::class);
        if (! $membership->requiresPayment($entity) && ! $membership->isActive($entity)) {
            $membership->activate($entity);
        }

        if ($entity->isValuer() && $this->canReceiveJobs($entity)) {
            try {
                app(ValuationPartnerService::class)->assignWaitingJobsCoveredBy($entity);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Status calculators */
    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $meta */
    private function personalStatus(Partner|Lender $entity, array $meta): array
    {
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);
        $items = [];

        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            $items[] = ['key' => 'contact_name', 'label' => __('site.partner_account.contact_person_name'), 'filled' => filled($entity->contactPersonName())];
            $items[] = ['key' => 'phone', 'label' => __('site.partner_account.phone'), 'filled' => filled($entity->phone)];
            $items[] = ['key' => 'email', 'label' => __('site.partner_account.email'), 'filled' => filled($entity->email)];
        } else {
            $items[] = ['key' => 'name', 'label' => __('site.partner_account.display_name'), 'filled' => filled($entity->name)];
            $items[] = ['key' => 'phone', 'label' => __('site.partner_account.phone'), 'filled' => filled($entity->phone)];
        }

        $idType = (string) ($identity['document_type'] ?? 'nida');
        if ($idType === 'nida') {
            $items[] = ['key' => 'national_id', 'label' => __('site.partner_account.nida_number'), 'filled' => filled($identity['national_id'] ?? null)];
            if (! $noPhysicalCard) {
                $items[] = ['key' => 'nida_front', 'label' => __('site.partner_account.nida_front'), 'filled' => filled($identity['national_id_front'] ?? null)];
                $items[] = ['key' => 'nida_back', 'label' => __('site.partner_account.nida_back'), 'filled' => filled($identity['national_id_back'] ?? null)];
            }
        } else {
            $items[] = ['key' => 'document_number', 'label' => __('site.partner_account.document_number'), 'filled' => filled($identity['document_number'] ?? $identity['national_id'] ?? null)];
            $items[] = ['key' => 'document_front', 'label' => __('site.partner_account.document_front'), 'filled' => filled($identity['national_id_front'] ?? null)];
        }

        if ($entity instanceof Partner && $entity->isAffiliate() && ! $entity->isCompanyApplicant()) {
            $reference = is_array($meta['reference_contact'] ?? null) ? $meta['reference_contact'] : [];
            $items[] = ['key' => 'reference_name', 'label' => __('site.affiliate_portal.reference_name'), 'filled' => filled($reference['name'] ?? null)];
            $items[] = ['key' => 'reference_relationship', 'label' => __('site.affiliate_portal.reference_relationship'), 'filled' => filled($reference['relationship'] ?? null)];
            $items[] = ['key' => 'reference_phone', 'label' => __('site.affiliate_portal.reference_phone'), 'filled' => filled($reference['phone'] ?? null)];
        }

        return $this->statusFromItems($items);
    }

    private function companyStatus(Partner|Lender $entity): array
    {
        return $this->statusFromItems([
            ['key' => 'legal_name', 'label' => __('site.partner_account.legal_name'), 'filled' => filled($entity->legal_name ?? null) || filled($entity->name)],
            ['key' => 'registration', 'label' => __('site.partner_account.registration'), 'filled' => filled($entity->registration_number ?? null) || filled($entity->tin ?? null)],
        ]);
    }

    /** @param array<string, mixed> $meta */
    private function faceStatus(Partner|Lender $entity, array $meta): array
    {
        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            return $this->statusFromItems([]);
        }

        $faces = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);

        $items = [
            ['key' => 'face_front', 'label' => __('site.partner_account.face_front'), 'filled' => filled($faces['front'] ?? null) || ($entity instanceof Partner && filled($entity->affiliate_selfie_path))],
            ['key' => 'face_left', 'label' => __('site.partner_account.face_left'), 'filled' => filled($faces['left'] ?? null)],
            ['key' => 'face_right', 'label' => __('site.partner_account.face_right'), 'filled' => filled($faces['right'] ?? null)],
        ];
        if (! $noPhysicalCard) {
            $items[] = ['key' => 'face_holding_id', 'label' => __('site.partner_account.face_holding_id'), 'filled' => filled($faces['holding_id'] ?? null)];
        }

        return $this->statusFromItems($items);
    }

    /** @param array<string, mixed> $meta */
    private function residenceStatus(array $meta): array
    {
        $residence = is_array($meta['residence'] ?? null) ? $meta['residence'] : [];

        return $this->statusFromItems([
            ['key' => 'region', 'label' => __('site.partner_account.region'), 'filled' => filled($residence['region'] ?? null)],
            ['key' => 'district', 'label' => __('site.partner_account.district'), 'filled' => filled($residence['district'] ?? null)],
            ['key' => 'street', 'label' => __('site.partner_account.street'), 'filled' => filled($residence['street'] ?? null)],
        ]);
    }

    /** @param array<string, mixed> $meta */
    private function activityStatus(array $meta): array
    {
        $activity = is_array($meta['activity'] ?? null) ? $meta['activity'] : [];
        $result = $this->statusFromItems([
            ['key' => 'activity_type', 'label' => __('site.partner_account.activity_type'), 'filled' => filled($activity['type'] ?? null)],
        ]);
        if (! $result['complete'] && filled($activity['details'] ?? null)) {
            $result['status'] = 'in_progress';
        }

        return $result;
    }

    /** @param array<string, mixed> $meta */
    private function paymentStatus(array $meta): array
    {
        $payout = is_array($meta['payout_account'] ?? null) ? $meta['payout_account'] : [];

        return $this->statusFromItems([
            ['key' => 'payout', 'label' => __('site.partner_account.payment_section'), 'filled' => ! empty($payout) && filled($payout['type'] ?? null)],
        ]);
    }

    /**
     * @param  list<array{key: string, label: string, filled: bool}>  $items
     * @return array{status: string, complete: bool, missing: list<array{key: string, label: string}>, progress: array{done: int, total: int, remaining: int}}
     */
    private function statusFromItems(array $items): array
    {
        $total = count($items);
        $done = count(array_filter($items, fn (array $item) => $item['filled']));
        $missing = array_values(array_map(
            fn (array $item) => ['key' => $item['key'], 'label' => $item['label']],
            array_filter($items, fn (array $item) => ! $item['filled'])
        ));
        $complete = $total === 0 || $done === $total;

        return [
            'status' => $complete ? 'complete' : ($done > 0 ? 'in_progress' : 'not_started'),
            'complete' => $complete,
            'missing' => $missing,
            'progress' => [
                'done' => $done,
                'total' => $total,
                'remaining' => max(0, $total - $done),
            ],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'complete' => __('borrower.profile.status.complete'),
            'in_progress' => __('borrower.profile.status.in_progress'),
            default => __('borrower.profile.status.not_started'),
        };
    }

    /* ------------------------------------------------------------------ */
    /* Section updaters */
    /* ------------------------------------------------------------------ */

    private function savePersonal(Partner|Lender $entity, Request $request): void
    {
        $focus = (string) $request->input('focus', 'contact');

        if ($focus === 'identity') {
            $this->saveIdentity($entity, $request);

            return;
        }

        if ($focus === 'promo' && $entity instanceof Partner) {
            $code = trim((string) $request->input('affiliate_code', ''));
            if (filled($code)) {
                app(AffiliateService::class)->updateCode($entity, $code);
            }

            return;
        }

        if ($focus === 'reference' && $entity instanceof Partner && $entity->isAffiliate() && ! $entity->isCompanyApplicant()) {
            $data = $request->validate([
                'nok_first_name' => ['nullable', 'string', 'max:80'],
                'nok_middle_name' => ['nullable', 'string', 'max:80'],
                'nok_last_name' => ['nullable', 'string', 'max:80'],
                'nok_relationship' => ['nullable', 'string', 'max:40'],
                'nok_phone' => ['nullable', 'string', 'max:30'],
                'nok_email' => ['nullable', 'email', 'max:120'],
                'reference_name' => ['nullable', 'string', 'max:120'],
                'reference_relationship' => ['nullable', 'string', 'max:40'],
                'reference_phone' => ['nullable', 'string', 'max:30'],
                'reference_email' => ['nullable', 'email', 'max:120'],
            ]);
            $first = trim((string) ($data['nok_first_name'] ?? ''));
            $middle = trim((string) ($data['nok_middle_name'] ?? ''));
            $last = trim((string) ($data['nok_last_name'] ?? ''));
            $name = trim(implode(' ', array_filter([$first, $middle, $last], fn ($part) => $part !== '')));
            if ($name === '') {
                $name = trim((string) ($data['reference_name'] ?? ''));
            }
            $relationship = trim((string) ($data['nok_relationship'] ?? $data['reference_relationship'] ?? ''));
            $phone = trim((string) ($data['nok_phone'] ?? $data['reference_phone'] ?? ''));
            $email = trim((string) ($data['nok_email'] ?? $data['reference_email'] ?? ''));

            $request->validate([
                'contact_name' => [filled($name) ? 'nullable' : 'required'],
            ], [
                'contact_name.required' => __('site.affiliate_portal.reference_name'),
            ]);
            if ($name === '' || $relationship === '' || $phone === '') {
                throw ValidationException::withMessages(array_filter([
                    'nok_first_name' => $name === '' ? __('site.affiliate_portal.reference_name') : null,
                    'nok_relationship' => $relationship === '' ? __('site.affiliate_portal.reference_relationship') : null,
                    'nok_phone' => $phone === '' ? __('site.affiliate_portal.reference_phone') : null,
                ]));
            }

            $meta = $entity->metadata ?? [];
            $meta['reference_contact'] = array_filter([
                'name' => $name,
                'first_name' => $first ?: null,
                'middle_name' => $middle ?: null,
                'last_name' => $last ?: null,
                'relationship' => $relationship,
                'phone' => $phone,
                'email' => $email !== '' ? $email : null,
            ], fn ($value) => $value !== null && $value !== '');
            $entity->update(['metadata' => $meta]);

            return;
        }

        if ($focus === 'preferences' && $entity instanceof Lender) {
            $data = $request->validate([
                'risk_preference' => ['nullable', 'in:low,medium,high'],
                'auto_invest' => ['nullable', 'boolean'],
            ]);

            $entity->update([
                'risk_preference' => $data['risk_preference'] ?? $entity->risk_preference,
                'auto_invest' => $request->boolean('auto_invest'),
            ]);

            return;
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            $meta = $entity->metadata ?? [];
            $meta['contact_person'] = array_filter([
                'name' => $data['name'] ?? null,
            ]);
            $entity->update(array_filter([
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'metadata' => $meta,
            ], fn ($value) => $value !== null));

            return;
        }

        $entity->update(array_filter([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
        ], fn ($value) => $value !== null));
    }

    private function saveIdentity(Partner|Lender $entity, Request $request): void
    {
        $data = $request->validate([
            'identity_document_type' => ['nullable', 'string', 'max:40'],
            'national_id' => ['nullable', 'string', 'max:40'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'no_physical_nida_card' => ['nullable', 'boolean'],
            'national_id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'national_id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $meta = $entity->metadata ?? [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $allowed = array_keys($this->allowedIdentityTypes($entity));
        $type = strtolower(trim((string) ($data['identity_document_type'] ?? $identity['document_type'] ?? 'nida')));
        if (! in_array($type, $allowed, true)) {
            $type = in_array('nida', $allowed, true) ? 'nida' : ($allowed[0] ?? 'nida');
        }
        $identity['document_type'] = $type;

        if ($type === 'nida') {
            if (filled($data['national_id'] ?? null) && ! NationalIdValidator::isValid($data['national_id'])) {
                throw ValidationException::withMessages([
                    'national_id' => NationalIdValidator::message(),
                ]);
            }

            // National ID is sensitive: allow first entry only, never overwrite once saved.
            if (filled($data['national_id'] ?? null) && blank($identity['national_id'] ?? null)) {
                $identity['national_id'] = NationalIdValidator::format($data['national_id'])
                    ?? strtoupper(trim($data['national_id']));
            }
        } elseif (filled($data['document_number'] ?? $data['national_id'] ?? null) && blank($identity['document_number'] ?? $identity['national_id'] ?? null)) {
            $identity['document_number'] = strtoupper(trim((string) ($data['document_number'] ?? $data['national_id'])));
            $identity['national_id'] = $identity['document_number'];
        }

        $identity['no_physical_nida_card'] = $request->boolean('no_physical_nida_card');

        $folder = $this->storageFolder($entity);

        if ($request->hasFile('national_id_front')) {
            $identity['national_id_front'] = $request->file('national_id_front')->store($folder, 'public');
        }

        if ($request->hasFile('national_id_back')) {
            $identity['national_id_back'] = $request->file('national_id_back')->store($folder, 'public');
        }

        $meta['identity'] = $identity;
        $updates = ['metadata' => $meta];

        // Keep legacy affiliate_id_path in sync from the NIDA front card for admin review screens.
        if ($entity instanceof Partner && $entity->isAffiliate() && filled($identity['national_id_front'] ?? null)) {
            $updates['affiliate_id_path'] = $identity['national_id_front'];
        }

        $entity->update($updates);
    }

    private function assertFaceSectionAvailable(Partner|Lender $entity): void
    {
        if (! in_array('face', $this->sectionsFor($entity), true)) {
            throw new \InvalidArgumentException('Face capture is not available for this partner.');
        }
    }

    /** @return array<string, string|null> */
    private function faceCaptures(Partner|Lender $entity): array
    {
        $meta = is_array($entity->metadata ?? null) ? $entity->metadata : [];

        return is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
    }

    private function normalizeFaceAngleKey(string $angle): string
    {
        return $angle === 'holding_id' ? 'holding_nida' : $angle;
    }

    private function faceStorageKey(string $angle): string
    {
        return $angle === 'holding_nida' ? 'holding_id' : $angle;
    }

    /**
     * @param  array<string, mixed>  $captures
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function derivedFaceUpdates(Partner|Lender $entity, array $captures, array $meta): array
    {
        if (! $entity instanceof Partner || blank($captures['front'] ?? null)) {
            return [];
        }

        $updates = ['affiliate_selfie_path' => $captures['front']];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);
        $facesReady = filled($captures['front'] ?? null)
            && filled($captures['left'] ?? null)
            && filled($captures['right'] ?? null)
            && ($noPhysicalCard || filled($captures['holding_id'] ?? null));
        $idDoc = $entity->affiliate_id_path;

        if ($facesReady && $idDoc) {
            $updates['affiliate_kyc_status'] = 'submitted';
        }

        return $updates;
    }

    private function saveFace(Partner|Lender $entity, Request $request): void
    {
        $request->validate([
            'face_front' => ['nullable', 'image', 'max:5120'],
            'face_left' => ['nullable', 'image', 'max:5120'],
            'face_right' => ['nullable', 'image', 'max:5120'],
            'face_holding_id' => ['nullable', 'image', 'max:5120'],
        ]);

        $meta = $entity->metadata ?? [];
        $faces = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
        $folder = $this->storageFolder($entity);

        foreach ([
            'face_front' => 'front',
            'face_left' => 'left',
            'face_right' => 'right',
            'face_holding_id' => 'holding_id',
        ] as $field => $key) {
            if ($request->hasFile($field)) {
                $faces[$key] = $request->file($field)->store($folder, 'public');
            }
        }

        $meta['face_captures'] = $faces;
        $updates = ['metadata' => $meta];

        if ($entity instanceof Partner && filled($faces['front'] ?? null)) {
            // Keep legacy selfie path as the front-facing capture for admin review screens
            // and public verification pages.
            $updates['affiliate_selfie_path'] = $faces['front'];

            $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
            $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);
            $facesReady = filled($faces['front'] ?? null)
                && filled($faces['left'] ?? null)
                && filled($faces['right'] ?? null)
                && ($noPhysicalCard || filled($faces['holding_id'] ?? null));
            $idDoc = $updates['affiliate_id_path'] ?? $entity->affiliate_id_path;

            if ($facesReady && $idDoc) {
                $updates['affiliate_kyc_status'] = 'submitted';
            }
        }

        $entity->update($updates);
    }

    private function saveResidence(Partner|Lender $entity, Request $request): void
    {
        $data = $request->validate([
            'residence_region' => ['nullable', 'string', 'max:80'],
            'residence_district' => ['nullable', 'string', 'max:80'],
            'residence_ward' => ['nullable', 'string', 'max:80'],
            'residence_street' => ['nullable', 'string', 'max:160'],
        ]);

        $meta = $entity->metadata ?? [];
        $residence = array_filter([
            'region' => $data['residence_region'] ?? null,
            'district' => $data['residence_district'] ?? null,
            'ward' => $data['residence_ward'] ?? null,
            'street' => $data['residence_street'] ?? null,
        ]);
        $meta['residence'] = $residence;

        $line = collect([
            $residence['street'] ?? null,
            $residence['ward'] ?? null,
            $residence['district'] ?? null,
            $residence['region'] ?? null,
        ])->filter()->implode(', ');

        $entity->update(array_filter([
            'metadata' => $meta,
            'address' => $line !== '' ? $line : null,
        ], fn ($value) => $value !== null));
    }

    private function saveActivity(Partner|Lender $entity, Request $request): void
    {
        $data = $request->validate([
            'activity_type' => ['nullable', 'string', 'max:80'],
            'activity_details' => ['nullable', 'string', 'max:2000'],
        ]);

        $meta = $entity->metadata ?? [];
        $meta['activity'] = array_filter([
            'type' => $data['activity_type'] ?? null,
            'details' => $data['activity_details'] ?? null,
        ]);

        $entity->update(['metadata' => $meta]);
    }

    private function savePayment(Partner|Lender $entity, Request $request): void
    {
        $data = $request->validate([
            'payout_type' => ['nullable', 'in:mobile_money,bank'],
            'payout_account_name' => ['nullable', 'string', 'max:120'],
            'payout_mobile_provider' => ['nullable', 'string', 'max:40'],
            'payout_mobile_number' => ['nullable', 'string', 'max:30'],
            'payout_bank_name' => ['nullable', 'string', 'max:120'],
            'payout_account_number' => ['nullable', 'string', 'max:60'],
        ]);

        $meta = $entity->metadata ?? [];
        $meta['payout_account'] = array_filter([
            'type' => $data['payout_type'] ?? null,
            'account_name' => $this->payoutAccountName($entity),
            'mobile_provider' => $data['payout_mobile_provider'] ?? null,
            'mobile_number' => $data['payout_mobile_number'] ?? null,
            'bank_name' => $data['payout_bank_name'] ?? null,
            'account_number' => $data['payout_account_number'] ?? null,
        ]);

        $entity->update(['metadata' => $meta]);
    }

    private function storageFolder(Partner|Lender $entity): string
    {
        return $entity instanceof Partner
            ? "partners/{$entity->id}/kyc"
            : "lenders/{$entity->id}/kyc";
    }
}
