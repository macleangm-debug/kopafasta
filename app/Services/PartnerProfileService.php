<?php

namespace App\Services;

use App\Models\Lender;
use App\Models\Partner;
use Illuminate\Http\Request;

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

    /** @return list<array<string, mixed>> */
    public function hubCards(Partner|Lender $entity, string $profileRouteName): array
    {
        $meta = [
            'personal'  => [
                'icon' => '👤',
                'label' => __('site.partner_account.personal_section'),
                'hint' => ($entity instanceof Partner && $entity->isCompanyApplicant())
                    ? __('site.partner_account.hint_personal_company')
                    : __('site.partner_account.hint_personal'),
            ],
            'company'   => ['icon' => '🏢', 'label' => __('site.partner_account.company_section'), 'hint' => __('site.partner_account.hint_company')],
            'face'      => ['icon' => '🤳', 'label' => __('site.partner_account.face_section'), 'hint' => __('site.partner_account.hint_face')],
            'residence' => [
                'icon' => '🏠',
                'label' => ($entity instanceof Partner && $entity->isCompanyApplicant())
                    ? __('site.partner_account.company_address_section')
                    : __('site.partner_account.residence_section'),
                'hint' => ($entity instanceof Partner && $entity->isCompanyApplicant())
                    ? __('site.partner_account.hint_company_address')
                    : __('site.partner_account.hint_residence'),
            ],
            'activity'  => ['icon' => '💼', 'label' => __('site.partner_account.activity_section'), 'hint' => __('site.partner_account.hint_activity')],
            'payment'   => ['icon' => '💳', 'label' => __('site.partner_account.payment_section'), 'hint' => __('site.partner_account.hint_payment')],
        ];

        return collect($this->sectionsFor($entity))->map(function (string $key) use ($meta, $entity, $profileRouteName) {
            $status = $this->sectionStatus($entity, $key);
            $info = $meta[$key];

            return [
                'key'          => $key,
                'icon'         => $info['icon'],
                'label'        => $info['label'],
                'description'  => $status['complete'] ? null : $info['hint'],
                'status'       => $status['status'],
                'status_label' => $this->statusLabel($status['status']),
                'action_label' => $status['complete'] ? __('borrower.profile.hub.view_edit') : __('borrower.profile.hub.add'),
                'url'          => route($profileRouteName, ['section' => $key]),
                'required'     => true,
                'count'        => null,
                'missing'      => [],
            ];
        })->values()->all();
    }

    /** @return array{status: string, complete: bool} */
    public function sectionStatus(Partner|Lender $entity, string $key): array
    {
        $meta = $entity->metadata ?? [];

        return match ($key) {
            'personal'  => $this->personalStatus($entity, $meta),
            'company'   => $this->companyStatus($entity),
            'face'      => $this->faceStatus($entity, $meta),
            'residence' => $this->residenceStatus($meta),
            'activity'  => $this->activityStatus($meta),
            'payment'   => $this->paymentStatus($meta),
            default     => ['status' => 'not_started', 'complete' => false],
        };
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

    public function updateSection(Partner|Lender $entity, string $section, Request $request): void
    {
        if (! in_array($section, $this->sectionsFor($entity), true)) {
            throw new \InvalidArgumentException("Section [{$section}] is not available for this partner.");
        }

        match ($section) {
            'personal'  => $this->savePersonal($entity, $request),
            'company'   => null, // admin-managed; read-only in portal
            'face'      => $this->saveFace($entity, $request),
            'residence' => $this->saveResidence($entity, $request),
            'activity'  => $this->saveActivity($entity, $request),
            'payment'   => $this->savePayment($entity, $request),
            default     => throw new \InvalidArgumentException("Unknown partner profile section [{$section}]."),
        };

        $entity->refresh();
        if ($this->completionPercent($entity) >= 100) {
            \App\Support\Celebration::flashOne('profile_complete');
            $this->finalizeRegistration($entity);
        }
    }

    public function isComplete(Partner|Lender $entity): bool
    {
        return $this->completionPercent($entity) >= 100;
    }

    /**
     * Why this partner cannot accept or start a job yet.
     *
     * @return 'profile'|'payment'|null
     */
    public function jobBlockReason(Partner $partner): ?string
    {
        if (! $this->isComplete($partner)) {
            return 'profile';
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
    }

    /* ------------------------------------------------------------------ */
    /* Status calculators                                                  */
    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $meta */
    private function personalStatus(Partner|Lender $entity, array $meta): array
    {
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $hasNida = filled($identity['national_id'] ?? null);
        $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);
        $hasUploads = filled($identity['national_id_front'] ?? null) && filled($identity['national_id_back'] ?? null);
        $identityComplete = $hasNida && ($noPhysicalCard || $hasUploads);

        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            $hasContact = filled($entity->contactPersonName()) && filled($entity->phone) && filled($entity->email);
            $complete = $hasContact && $identityComplete;

            return [
                'status' => $complete ? 'complete' : (($hasContact || $hasNida) ? 'in_progress' : 'not_started'),
                'complete' => $complete,
            ];
        }

        $hasContact = filled($entity->name) && filled($entity->phone);
        $complete = $hasContact && $identityComplete;
        $status = $complete ? 'complete' : (($hasContact || $hasNida) ? 'in_progress' : 'not_started');

        return ['status' => $status, 'complete' => $complete];
    }

    private function companyStatus(Partner|Lender $entity): array
    {
        $hasLegal = filled($entity->legal_name ?? null) || filled($entity->name);
        $hasReg = filled($entity->registration_number ?? null) || filled($entity->tin ?? null);
        $complete = $hasLegal && $hasReg;

        return [
            'status' => $complete ? 'complete' : ($hasLegal ? 'in_progress' : 'not_started'),
            'complete' => $complete,
        ];
    }

    /** @param array<string, mixed> $meta */
    private function faceStatus(Partner|Lender $entity, array $meta): array
    {
        if ($entity instanceof Partner && $entity->isCompanyApplicant()) {
            return ['status' => 'complete', 'complete' => true];
        }

        $faces = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);

        $hasFront = filled($faces['front'] ?? null) || ($entity instanceof Partner && filled($entity->affiliate_selfie_path));
        $hasLeft = filled($faces['left'] ?? null);
        $hasRight = filled($faces['right'] ?? null);
        $hasHoldingId = filled($faces['holding_id'] ?? null);

        $complete = $hasFront && $hasLeft && $hasRight && ($noPhysicalCard || $hasHoldingId);
        $status = $complete ? 'complete' : (($hasFront || $hasLeft || $hasRight) ? 'in_progress' : 'not_started');

        return ['status' => $status, 'complete' => $complete];
    }

    /** @param array<string, mixed> $meta */
    private function residenceStatus(array $meta): array
    {
        $residence = is_array($meta['residence'] ?? null) ? $meta['residence'] : [];
        $complete = filled($residence['region'] ?? null) && filled($residence['district'] ?? null);
        $status = $complete ? 'complete' : (filled($residence['street'] ?? null) ? 'in_progress' : 'not_started');

        return ['status' => $status, 'complete' => $complete];
    }

    /** @param array<string, mixed> $meta */
    private function activityStatus(array $meta): array
    {
        $activity = is_array($meta['activity'] ?? null) ? $meta['activity'] : [];
        $complete = filled($activity['type'] ?? null);
        $status = $complete ? 'complete' : (filled($activity['details'] ?? null) ? 'in_progress' : 'not_started');

        return ['status' => $status, 'complete' => $complete];
    }

    /** @param array<string, mixed> $meta */
    private function paymentStatus(array $meta): array
    {
        $payout = is_array($meta['payout_account'] ?? null) ? $meta['payout_account'] : [];
        $complete = ! empty($payout) && filled($payout['type'] ?? null);
        $status = $complete ? 'complete' : 'not_started';

        return ['status' => $status, 'complete' => $complete];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'complete'    => __('borrower.profile.status.complete'),
            'in_progress' => __('borrower.profile.status.in_progress'),
            default       => __('borrower.profile.status.not_started'),
        };
    }

    /* ------------------------------------------------------------------ */
    /* Section updaters                                                    */
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

        if ($focus === 'preferences' && $entity instanceof Lender) {
            $data = $request->validate([
                'risk_preference' => ['nullable', 'in:low,medium,high'],
                'auto_invest'     => ['nullable', 'boolean'],
            ]);

            $entity->update([
                'risk_preference' => $data['risk_preference'] ?? $entity->risk_preference,
                'auto_invest'     => $request->boolean('auto_invest'),
            ]);

            return;
        }

        $data = $request->validate([
            'name'    => ['nullable', 'string', 'max:120'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:120'],
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
            'name'    => $data['name'] ?? null,
            'phone'   => $data['phone'] ?? null,
            'email'   => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
        ], fn ($value) => $value !== null));
    }

    private function saveIdentity(Partner|Lender $entity, Request $request): void
    {
        $data = $request->validate([
            'national_id'           => ['nullable', 'string', 'max:40'],
            'no_physical_nida_card' => ['nullable', 'boolean'],
            'national_id_front'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'national_id_back'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if (filled($data['national_id'] ?? null) && ! \App\Support\NationalIdValidator::isValid($data['national_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'national_id' => \App\Support\NationalIdValidator::message(),
            ]);
        }

        $meta = $entity->metadata ?? [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];

        // National ID is sensitive: allow first entry only, never overwrite once saved.
        if (filled($data['national_id'] ?? null) && blank($identity['national_id'] ?? null)) {
            $identity['national_id'] = \App\Support\NationalIdValidator::format($data['national_id'])
                ?? strtoupper(trim($data['national_id']));
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

    private function saveFace(Partner|Lender $entity, Request $request): void
    {
        $request->validate([
            'face_front'      => ['nullable', 'image', 'max:5120'],
            'face_left'       => ['nullable', 'image', 'max:5120'],
            'face_right'      => ['nullable', 'image', 'max:5120'],
            'face_holding_id' => ['nullable', 'image', 'max:5120'],
        ]);

        $meta = $entity->metadata ?? [];
        $faces = is_array($meta['face_captures'] ?? null) ? $meta['face_captures'] : [];
        $folder = $this->storageFolder($entity);

        foreach ([
            'face_front'      => 'front',
            'face_left'       => 'left',
            'face_right'      => 'right',
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
            'residence_region'   => ['nullable', 'string', 'max:80'],
            'residence_district' => ['nullable', 'string', 'max:80'],
            'residence_ward'     => ['nullable', 'string', 'max:80'],
            'residence_street'   => ['nullable', 'string', 'max:160'],
        ]);

        $meta = $entity->metadata ?? [];
        $residence = array_filter([
            'region'   => $data['residence_region'] ?? null,
            'district' => $data['residence_district'] ?? null,
            'ward'     => $data['residence_ward'] ?? null,
            'street'   => $data['residence_street'] ?? null,
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
            'activity_type'    => ['nullable', 'string', 'max:80'],
            'activity_details' => ['nullable', 'string', 'max:2000'],
        ]);

        $meta = $entity->metadata ?? [];
        $meta['activity'] = array_filter([
            'type'    => $data['activity_type'] ?? null,
            'details' => $data['activity_details'] ?? null,
        ]);

        $entity->update(['metadata' => $meta]);
    }

    private function savePayment(Partner|Lender $entity, Request $request): void
    {
        $data = $request->validate([
            'payout_type'            => ['nullable', 'in:mobile_money,bank'],
            'payout_account_name'    => ['nullable', 'string', 'max:120'],
            'payout_mobile_provider' => ['nullable', 'string', 'max:40'],
            'payout_mobile_number'   => ['nullable', 'string', 'max:30'],
            'payout_bank_name'       => ['nullable', 'string', 'max:120'],
            'payout_account_number'  => ['nullable', 'string', 'max:60'],
        ]);

        $meta = $entity->metadata ?? [];
        $meta['payout_account'] = array_filter([
            'type'             => $data['payout_type'] ?? null,
            'account_name'     => $this->payoutAccountName($entity),
            'mobile_provider'  => $data['payout_mobile_provider'] ?? null,
            'mobile_number'    => $data['payout_mobile_number'] ?? null,
            'bank_name'        => $data['payout_bank_name'] ?? null,
            'account_number'   => $data['payout_account_number'] ?? null,
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
