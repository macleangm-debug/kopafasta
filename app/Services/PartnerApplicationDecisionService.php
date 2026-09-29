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
        'national_id' => 'National ID / NIDA',
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
        'national_id' => 'national_id', // front+back handled specially
        'proof_of_address' => 'proof_of_address',
        'business_document' => 'business_licence',
        'tin_certificate' => 'tin_certificate',
        'bank_account_evidence' => 'bank_account_evidence',
        'social_profile_evidence' => 'social_profile_evidence',
        'other_document' => 'other',
    ];

    public function __construct(
        private readonly PartnerEnrollmentService $enrollment,
    ) {}

    /** @return array{documents: array<string,string>, information: array<string,string>} */
    public function requestCatalog(): array
    {
        return [
            'documents' => self::DOCUMENT_REQUESTS,
            'information' => self::INFORMATION_REQUESTS,
        ];
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
        $otherLabel = trim((string) ($data['request_other_label'] ?? ''));
        $explanation = trim((string) ($data['request_explanation'] ?? $data['admin_notes'] ?? ''));

        if (! in_array($kind, ['document', 'information'], true)) {
            throw ValidationException::withMessages(['request_kind' => 'Choose Document or Information.']);
        }

        $catalog = $kind === 'document' ? self::DOCUMENT_REQUESTS : self::INFORMATION_REQUESTS;
        if ($type === '' || ! array_key_exists($type, $catalog)) {
            throw ValidationException::withMessages(['request_type' => 'Select what is required.']);
        }

        $isOther = in_array($type, ['other_document', 'other_information'], true);
        if ($isOther && $otherLabel === '') {
            throw ValidationException::withMessages(['request_other_label' => 'Describe the custom request.']);
        }

        $label = $isOther ? $otherLabel : $catalog[$type];
        $payload = is_array($application->payload) ? $application->payload : [];
        $requests = is_array($payload['info_requests'] ?? null) ? $payload['info_requests'] : [];

        $requests[] = [
            'id' => (string) Str::uuid(),
            'kind' => $kind,
            'type' => $type,
            'label' => $label,
            'explanation' => $explanation !== '' ? $explanation : null,
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
            'label' => 'Information requested',
            'detail' => $label.($explanation !== '' ? ' — '.$explanation : ''),
            'actor_id' => Auth::id(),
        ];
        $payload['review_activity'] = $activity;

        // Public-facing note stays compact; full structured list lives in payload.
        $publicNote = $explanation !== '' ? $explanation : null;

        $application->fill([
            'status' => 'needs_info',
            'admin_notes' => $publicNote,
            'payload' => $payload,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        return [
            'application' => $application->fresh(['documents', 'partner', 'reviewer']),
            'partner' => $application->partner,
            'message' => 'Information requested. The applicant will see it on the tracking card.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: PartnerApplication, partner: ?Partner, message: string}
     */
    private function approve(PartnerApplication $application, array $data): array
    {
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
            $existing = $this->findMatchingPartner($application);
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
            ->orderByDesc('id')
            ->first();
    }

    public function linkExistingPartner(PartnerApplication $application, Partner $partner): Partner
    {
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

        $application->update([
            'partner_id' => $partner->id,
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
                $documentIds[] = $this->storeDocument($application, 'national_id_front', $front)->id;
                $documentIds[] = $this->storeDocument($application, 'national_id_back', $back)->id;
            } else {
                $docType = self::DOCUMENT_TYPE_MAP[$type] ?? 'other';
                $file = $input['document'] ?? null;
                if (is_array($file)) {
                    $file = collect($file)->first(fn ($f) => $f instanceof UploadedFile);
                }
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['document' => 'Upload the requested document.']);
                }
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
            'label' => 'Information supplied',
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
