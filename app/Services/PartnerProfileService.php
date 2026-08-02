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
    /** @var list<string> */
    public const SECTIONS = ['personal', 'face', 'residence', 'activity', 'payment'];

    public function frontPhotoPath(Partner|Lender $entity): ?string
    {
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
            'personal'  => ['icon' => '👤', 'label' => __('site.partner_account.personal_section'), 'hint' => __('site.partner_account.hint_personal')],
            'face'      => ['icon' => '🤳', 'label' => __('site.partner_account.face_section'), 'hint' => __('site.partner_account.hint_face')],
            'residence' => ['icon' => '🏠', 'label' => __('site.partner_account.residence_section'), 'hint' => __('site.partner_account.hint_residence')],
            'activity'  => ['icon' => '💼', 'label' => __('site.partner_account.activity_section'), 'hint' => __('site.partner_account.hint_activity')],
            'payment'   => ['icon' => '💳', 'label' => __('site.partner_account.payment_section'), 'hint' => __('site.partner_account.hint_payment')],
        ];

        return collect(self::SECTIONS)->map(function (string $key) use ($meta, $entity, $profileRouteName) {
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
            'face'      => $this->faceStatus($entity, $meta),
            'residence' => $this->residenceStatus($meta),
            'activity'  => $this->activityStatus($meta),
            'payment'   => $this->paymentStatus($meta),
            default     => ['status' => 'not_started', 'complete' => false],
        };
    }

    public function completionPercent(Partner|Lender $entity): int
    {
        $complete = collect(self::SECTIONS)
            ->map(fn (string $key) => $this->sectionStatus($entity, $key)['complete'] ? 1 : 0);

        return (int) round(((float) $complete->avg()) * 100);
    }

    public function updateSection(Partner|Lender $entity, string $section, Request $request): void
    {
        match ($section) {
            'personal'  => $this->savePersonal($entity, $request),
            'face'      => $this->saveFace($entity, $request),
            'residence' => $this->saveResidence($entity, $request),
            'activity'  => $this->saveActivity($entity, $request),
            'payment'   => $this->savePayment($entity, $request),
            default     => throw new \InvalidArgumentException("Unknown partner profile section [{$section}]."),
        };
    }

    /* ------------------------------------------------------------------ */
    /* Status calculators                                                  */
    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $meta */
    private function personalStatus(Partner|Lender $entity, array $meta): array
    {
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
        $hasContact = filled($entity->name) && filled($entity->phone);
        $hasNida = filled($identity['national_id'] ?? null);
        $noPhysicalCard = (bool) ($identity['no_physical_nida_card'] ?? false);
        $hasUploads = filled($identity['national_id_front'] ?? null) && filled($identity['national_id_back'] ?? null);
        $identityComplete = $hasNida && ($noPhysicalCard || $hasUploads);
        $complete = $hasContact && $identityComplete;

        $status = $complete ? 'complete' : (($hasContact || $hasNida) ? 'in_progress' : 'not_started');

        return ['status' => $status, 'complete' => $complete];
    }

    /** @param array<string, mixed> $meta */
    private function faceStatus(Partner|Lender $entity, array $meta): array
    {
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
            'national_id'           => ['nullable', 'string', 'max:30'],
            'no_physical_nida_card' => ['nullable', 'boolean'],
            'national_id_front'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'national_id_back'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $meta = $entity->metadata ?? [];
        $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];

        // National ID is sensitive: allow first entry only, never overwrite once saved.
        if (filled($data['national_id'] ?? null) && blank($identity['national_id'] ?? null)) {
            $identity['national_id'] = strtoupper(trim($data['national_id']));
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
            'residence_street'   => ['nullable', 'string', 'max:160'],
        ]);

        $meta = $entity->metadata ?? [];
        $meta['residence'] = array_filter([
            'region'   => $data['residence_region'] ?? null,
            'district' => $data['residence_district'] ?? null,
            'street'   => $data['residence_street'] ?? null,
        ]);

        $entity->update(['metadata' => $meta]);
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
            'account_name'     => $data['payout_account_name'] ?? null,
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
