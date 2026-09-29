<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Application review decisions + structured information requests for Partner 360.
 * Reuses PartnerEnrollmentService for approve/convert — no second decision engine.
 */
class PartnerApplicationDecisionService
{
    public const DOCUMENT_REQUESTS = [
        'national_id' => 'National ID / NIDA (Front + Back)',
        'national_id_front' => 'National ID — Front',
        'national_id_back' => 'National ID — Back',
        'proof_of_address' => 'Proof of address',
        'business_document' => 'Business document',
        'tin_certificate' => 'TIN / tax document',
        'bank_account_evidence' => 'Bank / payment-account evidence',
        'social_profile_evidence' => 'Social / profile evidence',
        'other_document' => 'Other document',
    ];

    public const INFORMATION_REQUESTS = [
        'personal_information' => 'Personal information',
        'address_location' => 'Address / location',
        'occupation_business' => 'Occupation / business',
        'market_promotion' => 'Market / promotion information',
        'contact_information' => 'Contact information',
        'other_information' => 'Other information',
    ];

    /** Map request type → PartnerApplicationDocument doc_type (or special national_id). */
    public const DOCUMENT_TYPE_MAP = [
        'national_id' => 'national_id',
        'national_id_front' => 'national_id_front',
        'national_id_back' => 'national_id_back',
        'proof_of_address' => 'proof_of_address',
        'business_document' => 'business_licence',
        'tin_certificate' => 'tin_certificate',
        'bank_account_evidence' => 'bank_account_evidence',
        'social_profile_evidence' => 'social_profile_evidence',
        'other_document' => 'other',
    ];

    public const REPLACE_REASONS = [
        'not_clear' => 'Image/document not clear',
        'not_readable' => 'Information not readable',
        'cropped' => 'Document cropped/incomplete',
        'wrong_document' => 'Wrong document uploaded',
        'expired' => 'Document expired',
        'side_missing' => 'Front/back missing',
        'mismatch' => 'Information does not match application',
        'updated_required' => 'Updated document required',
        'other' => 'Other',
    ];

    public function __construct(
        private readonly PartnerEnrollmentService $enrollment,
        private readonly PartnerMatchResolutionService $matchResolution,
    ) {}

    /** @return array{documents: array<string,string>, information: array<string,string>, replace_reasons: array<string,string>} */
    public function requestCatalog(): array
    {
        return [
            'documents' => self::DOCUMENT_REQUESTS,
            'information' => self::INFORMATION_REQUESTS,
            'replace_reasons' => self::REPLACE_REASONS,
        ];
    }

    /**
     * Existing application documents available for update/replacement requests.
     *
     * @return list<array{value: string, label: string, doc_type: string, present: bool}>
     */
    public function existingDocumentOptions(PartnerApplication $application): array
    {
        $application->loadMissing('documents');
        $present = $application->documents->pluck('doc_type')->unique()->all();
        $options = [];

        if (in_array('national_id_front', $present, true)) {
            $options[] = [
                'value' => 'national_id_front',
                'label' => self::DOCUMENT_REQUESTS['national_id_front'],
                'doc_type' => 'national_id_front',
                'present' => true,
            ];
        }
        if (in_array('national_id_back', $present, true)) {
            $options[] = [
                'value' => 'national_id_back',
                'label' => self::DOCUMENT_REQUESTS['national_id_back'],
                'doc_type' => 'national_id_back',
                'present' => true,
            ];
        }
        if (in_array('national_id_front', $present, true) && in_array('national_id_back', $present, true)) {
            $options[] = [
                'value' => 'national_id',
                'label' => self::DOCUMENT_REQUESTS['national_id'],
                'doc_type' => 'national_id',
                'present' => true,
            ];
        }

        foreach (self::DOCUMENT_TYPE_MAP as $requestType => $docType) {
            if (in_array($requestType, ['national_id', 'national_id_front', 'national_id_back'], true)) {
                continue;
            }
            if (in_array($docType, $present, true)) {
                $options[] = [
                    'value' => $requestType,
                    'label' => self::DOCUMENT_REQUESTS[$requestType] ?? $docType,
                    'doc_type' => $docType,
                    'present' => true,
                ];
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: PartnerApplication, partner: ?Partner, message: string}
     */
    public function execute(PartnerApplication $application, array $data): array
    {
        $status = (string) ($data['status'] ?? '');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'needs_info'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid decision status.']);
        }

        return match ($status) {
            'needs_info' => $this->requestInformation($application, $data),
            'approved' => $this->approve($application, $data),
            'rejected' => $this->decline($application, $data),
            default => $this->savePending($application, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: PartnerApplication, partner: ?Partner, message: string}
     */
    private function requestInformation(PartnerApplication $application, array $data): array
    {
        $kind = (string) ($data['request_kind'] ?? '');
        $type = (string) ($data['request_type'] ?? '');
        $mode = (string) ($data['request_mode'] ?? 'new');
        $otherLabel = trim((string) ($data['request_other_label'] ?? ''));
        $explanation = trim((string) ($data['request_explanation'] ?? $data['admin_notes'] ?? ''));
        $replaceReason = (string) ($data['replace_reason'] ?? '');
        $replaceReasonOther = trim((string) ($data['replace_reason_other'] ?? ''));

        if (! in_array($kind, ['document', 'information'], true)) {
            throw ValidationException::withMessages(['request_kind' => 'Choose Document or Information.']);
        }
        if (! in_array($mode, ['new', 'replace'], true)) {
            $mode = 'new';
        }

        $catalog = $kind === 'document' ? self::DOCUMENT_REQUESTS : self::INFORMATION_REQUESTS;
        if ($type === '' || ! array_key_exists($type, $catalog)) {
            throw ValidationException::withMessages(['request_type' => 'Select what is required.']);
        }

        $isOther = in_array($type, ['other_document', 'other_information'], true);
        if ($isOther && $otherLabel === '' && $mode === 'new') {
            throw ValidationException::withMessages(['request_other_label' => 'Describe the custom request.']);
        }

        $replaceReasonLabel = null;
        if ($kind === 'document' && $mode === 'replace') {
            if ($replaceReason === '' || ! array_key_exists($replaceReason, self::REPLACE_REASONS)) {
                throw ValidationException::withMessages(['replace_reason' => 'Select why the document must be updated.']);
            }
            if ($replaceReason === 'other' && $replaceReasonOther === '') {
                throw ValidationException::withMessages(['replace_reason_other' => 'Describe the custom reason.']);
            }
            $replaceReasonLabel = $replaceReason === 'other'
                ? $replaceReasonOther
                : self::REPLACE_REASONS[$replaceReason];
        }

        $label = $isOther && $otherLabel !== '' ? $otherLabel : $catalog[$type];
        $payload = is_array($application->payload) ? $application->payload : [];
        $requests = is_array($payload['info_requests'] ?? null) ? $payload['info_requests'] : [];

        $publicExplanation = $explanation;
        if ($replaceReasonLabel) {
            $publicExplanation = $replaceReasonLabel.($explanation !== '' ? '. '.$explanation : '');
        }

        $requests[] = [
            'id' => (string) Str::uuid(),
            'kind' => $kind,
            'type' => $type,
            'mode' => $mode,
            'label' => $label,
            'explanation' => $publicExplanation !== '' ? $publicExplanation : null,
            'replace_reason' => $replaceReason !== '' ? $replaceReason : null,
            'replace_reason_label' => $replaceReasonLabel,
            'status' => 'requested',
            'requested_at' => now()->toIso8601String(),
            'requested_by' => Auth::id(),
            'submitted_at' => null,
            'response_text' => null,
            'document_ids' => [],
        ];

        $payload['info_requests'] = $requests;
        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => $mode === 'replace' ? 'Document requested for update' : 'Information requested',
            'detail' => $label
                .($replaceReasonLabel ? ' — '.$replaceReasonLabel : '')
                .($explanation !== '' ? ' — '.$explanation : ''),
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        $application->fill([
            'status' => 'needs_info',
            'admin_notes' => $publicExplanation !== '' ? $publicExplanation : null,
            'payload' => $payload,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        return [
            'application' => $application->fresh(['documents', 'partner', 'reviewer']),
            'partner' => $application->partner,
            'message' => $mode === 'replace'
                ? 'Document update requested. The applicant will see it on the tracking card.'
                : 'Information requested. The applicant will see it on the tracking card.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: PartnerApplication, partner: ?Partner, message: string}
     */
    private function approve(PartnerApplication $application, array $data): array
    {
        $unresolved = $this->matchResolution->unresolvedMatches($application);
        if ($unresolved !== []) {
            $names = collect($unresolved)->map(fn ($m) => $m['existing']['name'] ?? 'Partner')->implode(', ');
            throw ValidationException::withMessages([
                'status' => 'Resolve possible Partner matches before Approve (Review match → '.$names.').',
            ]);
        }

        // Keep-separate with shared login email/phone cannot activate a second identity.
        foreach ($this->matchResolution->matchesFor($application) as $match) {
            if (($match['resolution'] ?? null) !== 'keep_separate') {
                continue;
            }
            $emailBlocked = ! empty($match['uniqueness']['email_shared_with_existing_login']);
            $phoneBlocked = ! empty($match['uniqueness']['phone_shared_with_existing_login']);
            if ($emailBlocked || $phoneBlocked) {
                throw ValidationException::withMessages([
                    'status' => $match['uniqueness']['message']
                        ?? 'Correct the duplicated contact details before Approve — Keep separate was recorded, but login identifiers must stay unique.',
                ]);
            }
        }

        $linkedId = $this->matchResolution->linkedPartnerId($application);
        $existingForLink = $linkedId
            ? Partner::query()->find($linkedId)
            : $this->findMatchingPartner($application);

        // New Partner identity must not absorb a Borrower/Member login.
        if (! $application->partner_id && ! $existingForLink) {
            if ($borrowerBlock = $this->matchResolution->borrowerContactBlocker($application)) {
                throw ValidationException::withMessages(['status' => $borrowerBlock]);
            }
        }

        // Existing Partner link target must be Partner-only (never a Borrower User).
        if ($existingForLink && ($borrowerOnPartner = $this->matchResolution->borrowerIdentityOnPartner($existingForLink))) {
            throw ValidationException::withMessages(['status' => $borrowerOnPartner]);
        }

        $payload = is_array($application->payload) ? $application->payload : [];
        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => 'Application approved',
            'detail' => trim((string) ($data['admin_notes'] ?? '')) ?: null,
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        $application->fill([
            'status' => 'approved',
            'admin_notes' => $data['admin_notes'] ?? $application->admin_notes,
            'payload' => $payload,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        $partner = null;
        if (! $application->partner_id) {
            $existing = $existingForLink;

            if ($existing) {
                $partner = $this->linkExistingPartner($application->fresh('documents'), $existing);
                $message = 'Application approved and linked to existing partner '.$partner->vendor_number.'.';
            } else {
                $partner = $this->enrollment->convertToPartner($application->fresh('documents'), Auth::user());
                $message = 'Partner approved. Partner code '.$partner->vendor_number.' is ready — they can activate via Track status / Activate account.';
            }
        } else {
            $partner = $application->partner;
            $message = 'Partner application already linked to '.$partner?->vendor_number.'.';
        }

        return [
            'application' => $application->fresh(['documents', 'partner', 'reviewer']),
            'partner' => $partner,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: PartnerApplication, partner: ?Partner, message: string}
     */
    private function decline(PartnerApplication $application, array $data): array
    {
        $reason = (string) ($data['rejection_reason'] ?? '');
        $description = trim((string) ($data['admin_notes'] ?? ''));
        if ($reason === '' || ! array_key_exists($reason, PartnerApplicationReviewService::REJECTION_REASON_CODES)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Select a decline reason.']);
        }
        if ($description === '') {
            throw ValidationException::withMessages(['admin_notes' => 'Add a short description for the applicant.']);
        }

        $reasonLabel = PartnerApplicationReviewService::REJECTION_REASON_CODES[$reason];
        // Public note = description only (no internal reason code leakage beyond approved public labels).
        $publicNote = $description;

        $payload = is_array($application->payload) ? $application->payload : [];
        $payload['decline'] = [
            'reason_code' => $reason,
            'reason_label' => $reasonLabel,
            'description' => $description,
            'declined_at' => now()->toIso8601String(),
            'declined_by' => Auth::id(),
        ];
        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => 'Application declined',
            'detail' => $reasonLabel.': '.$description,
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        $application->fill([
            'status' => 'rejected',
            'admin_notes' => $publicNote,
            'payload' => $payload,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        return [
            'application' => $application->fresh(['documents', 'partner', 'reviewer']),
            'partner' => null,
            'message' => 'Application declined. The applicant will see the decision on tracking.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: PartnerApplication, partner: ?Partner, message: string}
     */
    private function savePending(PartnerApplication $application, array $data): array
    {
        $application->fill([
            'status' => 'pending',
            'admin_notes' => $data['admin_notes'] ?? $application->admin_notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        return [
            'application' => $application->fresh(['documents', 'partner', 'reviewer']),
            'partner' => $application->partner,
            'message' => 'Application kept under review.',
        ];
    }

    public function findMatchingPartner(PartnerApplication $application): ?Partner
    {
        if (! filled($application->phone) && ! filled($application->email) && ! filled($application->tin)) {
            return null;
        }

        $dismissed = $this->matchResolution->keepSeparatePartnerIds($application);

        return Partner::query()
            ->where(function ($q) use ($application) {
                if (filled($application->phone)) {
                    $q->orWhere('phone', $application->phone);
                }
                if (filled($application->email)) {
                    $q->orWhere('email', $application->email);
                }
                if (filled($application->tin)) {
                    $q->orWhere('tin', $application->tin);
                }
            })
            ->when($dismissed !== [], fn ($q) => $q->whereNotIn('id', $dismissed))
            ->orderByDesc('id')
            ->first();
    }

    public function linkExistingPartner(PartnerApplication $application, Partner $partner): Partner
    {
        if ($borrowerOnPartner = $this->matchResolution->borrowerIdentityOnPartner($partner)) {
            throw ValidationException::withMessages(['status' => $borrowerOnPartner]);
        }

        foreach ($application->documents as $doc) {
            $dest = 'partners/'.$partner->id.'/compliance/'.basename((string) $doc->file_path);
            if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)
                && ! Storage::disk('public')->exists($dest)) {
                Storage::disk('public')->copy($doc->file_path, $dest);
            }

            $exists = $partner->documents()
                ->where('doc_type', $doc->doc_type)
                ->exists();
            if (! $exists) {
                \App\Models\PartnerDocument::create([
                    'partner_id' => $partner->id,
                    'label' => $doc->label(),
                    'doc_type' => $doc->doc_type,
                    'file_path' => Storage::disk('public')->exists($dest) ? $dest : $doc->file_path,
                    'mime' => $doc->mime,
                    'size_bytes' => $doc->size_bytes,
                ]);
            }
        }

        // One Partner identity may hold multiple roles — add the application category if missing.
        $category = $this->enrollment->normalizeCategory(
            (string) ($application->partner_category ?: ($application->type === 'affiliate' ? 'affiliate' : 'debt_collector'))
        );
        $roles = $partner->partnerRoles();
        $addedRole = ! in_array($category, $roles, true);
        if ($addedRole) {
            $roles[] = $category;
            $partner->update(['roles' => array_values($roles)]);
        }

        $payload = is_array($application->payload) ? $application->payload : [];
        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => 'Identity linked to existing Partner',
            'detail' => $partner->name.' ('.($partner->vendor_number ?: $partner->partner_number ?: 'P-'.$partner->id).')'
                .($addedRole ? ' · added '.$this->enrollment->categoryLabel($category).' workspace' : ' · role already present')
                .' · no new user · profile fields not overwritten',
            'actor_id' => Auth::id(),
        ];
        $payload['identity_link'] = [
            'partner_id' => $partner->id,
            'linked_at' => now()->toIso8601String(),
            'linked_by' => Auth::id(),
            'added_role' => $addedRole ? $category : null,
        ];
        $payload['review_activity'] = $activity;

        $application->update([
            'partner_id' => $partner->id,
            'payload' => $payload,
            'reviewed_by' => Auth::id() ?? $application->reviewed_by,
            'reviewed_at' => $application->reviewed_at ?? now(),
        ]);

        return $partner->fresh();
    }

    /**
     * Applicant fulfills one outstanding info request from the tracking card.
     *
     * @param  array<string, mixed>  $input
     */
    public function fulfillRequest(PartnerApplication $application, string $requestId, array $input): PartnerApplication
    {
        $payload = is_array($application->payload) ? $application->payload : [];
        $requests = is_array($payload['info_requests'] ?? null) ? $payload['info_requests'] : [];
        $index = collect($requests)->search(fn ($row) => ($row['id'] ?? '') === $requestId);
        if ($index === false) {
            throw ValidationException::withMessages(['request' => 'That request was not found.']);
        }

        $row = $requests[$index];
        if (($row['status'] ?? '') === 'submitted') {
            throw ValidationException::withMessages(['request' => 'This request was already submitted.']);
        }

        $kind = (string) ($row['kind'] ?? '');
        $mode = (string) ($row['mode'] ?? 'new');
        $documentIds = [];

        if ($kind === 'document') {
            $type = (string) ($row['type'] ?? '');
            if ($type === 'national_id') {
                $front = $input['doc_national_id_front'] ?? null;
                $back = $input['doc_national_id_back'] ?? null;
                if (! $front instanceof UploadedFile || ! $back instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'doc_national_id_front' => 'Capture or upload NIDA front and back.',
                    ]);
                }
                $this->archiveDocumentsOfTypes($application, $payload, ['national_id_front', 'national_id_back'], $row);
                $documentIds[] = $this->storeDocument($application, 'national_id_front', $front)->id;
                $documentIds[] = $this->storeDocument($application, 'national_id_back', $back)->id;
            } elseif (in_array($type, ['national_id_front', 'national_id_back'], true)) {
                $field = $type === 'national_id_front' ? 'doc_national_id_front' : 'doc_national_id_back';
                $file = $input[$field] ?? $input['document'] ?? null;
                if (is_array($file)) {
                    $file = collect($file)->first(fn ($f) => $f instanceof UploadedFile);
                }
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        $field => 'Capture or upload the requested National ID side.',
                    ]);
                }
                $this->archiveDocumentsOfTypes($application, $payload, [$type], $row);
                $documentIds[] = $this->storeDocument($application, $type, $file)->id;
            } else {
                $docType = self::DOCUMENT_TYPE_MAP[$type] ?? 'other';
                $file = $input['document'] ?? null;
                if (is_array($file)) {
                    $file = collect($file)->first(fn ($f) => $f instanceof UploadedFile);
                }
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['document' => 'Upload the requested document.']);
                }
                $this->archiveDocumentsOfTypes($application, $payload, [$docType], $row);
                $documentIds[] = $this->storeDocument($application, $docType, $file)->id;
            }
            $row['response_text'] = null;
        } else {
            $text = trim((string) ($input['response_text'] ?? ''));
            if ($text === '') {
                throw ValidationException::withMessages(['response_text' => 'Enter the requested information.']);
            }
            $row['response_text'] = $text;
        }

        $row['status'] = 'submitted';
        $row['submitted_at'] = now()->toIso8601String();
        $row['document_ids'] = $documentIds;
        $requests[$index] = $row;
        $payload['info_requests'] = array_values($requests);

        $activity = is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [];
        $activity[] = [
            'at' => now()->toIso8601String(),
            'label' => $mode === 'replace' ? 'Replacement submitted' : 'Information supplied',
            'detail' => (string) ($row['label'] ?? 'Request'),
            'actor_id' => null,
        ];
        $payload['review_activity'] = $activity;

        $outstanding = collect($requests)->contains(fn ($r) => ($r['status'] ?? '') === 'requested');
        $application->fill([
            'status' => $outstanding ? 'needs_info' : 'pending',
            'payload' => $payload,
            'admin_notes' => $outstanding ? $application->admin_notes : null,
        ])->save();

        return $application->fresh('documents');
    }

    private function storeDocument(PartnerApplication $application, string $docType, UploadedFile $file): PartnerApplicationDocument
    {
        // Allow extended types used by info requests.
        $allowed = array_merge(array_keys(PartnerApplicationDocument::DOC_TYPES), [
            'proof_of_address',
            'bank_account_evidence',
            'social_profile_evidence',
        ]);
        if (! in_array($docType, $allowed, true)) {
            $docType = 'other';
        }

        $path = $file->store('partner-applications/'.$application->id, 'public');

        return PartnerApplicationDocument::create([
            'partner_application_id' => $application->id,
            'doc_type' => $docType,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * Keep prior versions in payload history; remove them from the current document set
     * so Identity & Documents shows only one current file per type.
     *
     * @param  list<string>  $docTypes
     * @param  array<string, mixed>  $requestRow
     * @param  array<string, mixed>  $payload
     */
    private function archiveDocumentsOfTypes(
        PartnerApplication $application,
        array &$payload,
        array $docTypes,
        array $requestRow
    ): void {
        $history = is_array($payload['document_history'] ?? null) ? $payload['document_history'] : [];
        $application->loadMissing('documents');

        foreach ($application->documents->whereIn('doc_type', $docTypes) as $doc) {
            $history[] = [
                'id' => $doc->id,
                'doc_type' => $doc->doc_type,
                'file_path' => $doc->file_path,
                'original_name' => $doc->original_name,
                'mime' => $doc->mime,
                'size_bytes' => $doc->size_bytes,
                'superseded_at' => now()->toIso8601String(),
                'superseded_by_request_id' => $requestRow['id'] ?? null,
                'replace_reason' => $requestRow['replace_reason_label'] ?? ($requestRow['replace_reason'] ?? null),
            ];
            $doc->delete();
        }

        $payload['document_history'] = $history;
    }

    /** @return list<array<string, mixed>> */
    public function outstandingRequests(PartnerApplication $application): array
    {
        $payload = is_array($application->payload) ? $application->payload : [];
        $requests = is_array($payload['info_requests'] ?? null) ? $payload['info_requests'] : [];

        return array_values(array_filter($requests, fn ($r) => ($r['status'] ?? '') === 'requested'));
    }

    /** @return list<array<string, mixed>> */
    public function allRequests(PartnerApplication $application): array
    {
        $payload = is_array($application->payload) ? $application->payload : [];

        return is_array($payload['info_requests'] ?? null) ? array_values($payload['info_requests']) : [];
    }
}
