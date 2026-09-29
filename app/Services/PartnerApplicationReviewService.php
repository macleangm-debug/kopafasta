<?php

namespace App\Services;

use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;

class PartnerApplicationReviewService
{
    /** @var array<string, string> */
    public const REJECTION_REASON_CODES = [
        'incomplete_docs'       => 'Incomplete or missing documents',
        'invalid_id'            => 'Invalid or unclear national ID',
        'business_not_verified' => 'Business registration could not be verified',
        'duplicate_application' => 'Duplicate application already on file',
        'coverage_mismatch'     => 'Coverage area not currently needed',
        'other'                 => 'Other (see notes)',
    ];

    public function __construct(private readonly PartnerEnrollmentService $enrollment) {}

    /** @return array<string, mixed> */
    public function dossier(PartnerApplication $application): array
    {
        $application->loadMissing(['documents', 'partner', 'reviewer']);

        $category = $this->enrollment->normalizeCategory(
            (string) ($application->partner_category ?: ($application->type === 'affiliate' ? 'affiliate' : 'debt_collector'))
        );

        $documents = $application->documents->map(fn (PartnerApplicationDocument $doc) => $this->documentRow($doc));

        $requiredDocTypes = $this->requiredDocTypes($category, (string) $application->applicant_category);
        $presentDocTypes = $application->documents->pluck('doc_type')->all();

        $checklist = collect($requiredDocTypes)
            ->map(fn (string $docType) => [
                'key'     => $docType,
                'label'   => PartnerApplicationDocument::DOC_TYPES[$docType] ?? ucfirst(str_replace('_', ' ', $docType)),
                'present' => in_array($docType, $presentDocTypes, true),
            ])
            ->values()
            ->all();

        $requiredCount = count($checklist);
        $satisfiedCount = collect($checklist)->where('present', true)->count();
        $checklistProgress = $requiredCount > 0
            ? (int) round(($satisfiedCount / $requiredCount) * 100)
            : 100;

        $identity = [
            'national_id_front' => $documents->firstWhere('doc_type', 'national_id_front'),
            'national_id_back'  => $documents->firstWhere('doc_type', 'national_id_back'),
        ];

        $payload = is_array($application->payload) ? $application->payload : [];
        $payloadIdentity = is_array($payload['identity'] ?? null) ? $payload['identity'] : [];
        $declarations = is_array($payload['declarations'] ?? null) ? $payload['declarations'] : [];
        $applicantDecl = is_array($declarations['applicant'] ?? null) ? $declarations['applicant'] : [];

        $activity = $this->activityTimeline($application, $applicantDecl);

        return [
            'applicant' => [
                'full_name'          => $application->full_name,
                'phone'              => $application->phone,
                'email'              => $application->email,
                'applicant_category' => $application->applicant_category,
                'category'           => $category,
                'category_label'     => $this->enrollment->categoryLabel($category),
                'requested_roles'    => $this->enrollment->normalizeRequestedRoles(
                    $category,
                    is_array($application->requested_roles) ? $application->requested_roles : []
                ),
                'region'             => $application->region,
                'coverage_regions'   => $application->coverage_regions ?: [],
                'message'            => $application->message,
                'payload'            => $payload,
                'date_of_birth'      => $payloadIdentity['date_of_birth'] ?? ($applicantDecl['date_of_birth'] ?? null),
                'gender'             => $payloadIdentity['gender'] ?? ($applicantDecl['gender'] ?? null),
                'phone_alt'          => $payloadIdentity['phone_alt'] ?? null,
                'district'           => $payloadIdentity['district'] ?? null,
                'ward'               => $payloadIdentity['ward'] ?? null,
                'street'             => $payloadIdentity['street'] ?? null,
                'reference'          => 'PA-'.$application->id,
                'submitted_at'       => $application->created_at,
            ],
            'application_detail' => [
                'occupation' => $payload['occupation'] ?? null,
                'sales_experience' => $payload['sales_experience'] ?? null,
                'financial_services_experience' => $payload['financial_services_experience'] ?? null,
                'languages' => is_array($payload['languages'] ?? null) ? $payload['languages'] : [],
                'previous_agent' => $payload['previous_agent'] ?? null,
                'previous_agent_details' => $payload['previous_agent_details'] ?? null,
                'why_affiliate' => $payload['why_affiliate'] ?? $application->message,
                'acquisition_methods' => is_array($payload['acquisition_methods'] ?? null) ? $payload['acquisition_methods'] : [],
                'channels' => is_array($payload['channels'] ?? null) ? $payload['channels'] : [],
                'has_social_profile' => $payload['has_social_profile'] ?? null,
                'social_platform' => $payload['social_platform'] ?? null,
                'social_profile_url' => $payload['social_profile_url'] ?? null,
                'monthly_reach' => $payload['monthly_reach'] ?? ($payload['reach'] ?? null),
                'how_heard' => $payload['how_heard'] ?? (data_get($payload, 'acquisition_source.label')),
                'first_10_customers' => $payload['first_10_customers'] ?? null,
                'registered_business' => $payload['registered_business'] ?? null,
            ],
            'declaration' => [
                'version' => $applicantDecl['version'] ?? (data_get($declarations, 'conduct.version')),
                'accepted' => (bool) ($applicantDecl['accepted'] ?? false),
                'accepted_at' => $applicantDecl['accepted_at'] ?? null,
                'full_name' => $applicantDecl['full_name'] ?? $application->full_name,
                'date_of_birth' => $applicantDecl['date_of_birth'] ?? null,
                'gender' => $applicantDecl['gender'] ?? null,
            ],
            'commercial' => [
                'proposed_type' => $category === 'affiliate' ? 'Ordinary Affiliate' : $this->enrollment->categoryLabel($category),
                'territory' => $category === 'affiliate' ? 'Nationwide (online)' : ($application->region ?: '—'),
                'application_fee_required' => $category === 'affiliate'
                    ? app(AffiliateSettingsService::class)->applicationFeeRequired()
                    : false,
                'application_fee_amount' => $category === 'affiliate'
                    ? app(AffiliateSettingsService::class)->applicationFeeAmount()
                    : null,
                'fee_status' => $application->status === 'awaiting_fee' ? 'awaiting_fee' : (
                    in_array($application->status, ['pending', 'needs_info', 'approved', 'rejected'], true) ? 'paid_or_not_required' : $application->status
                ),
            ],
            'activity' => $activity,
            'info_requests' => app(PartnerApplicationDecisionService::class)->allRequests($application),
            'business' => [
                'trading_name'        => $application->business_name,
                'legal_name'          => $application->legal_name,
                'registration_number' => $application->registration_number,
                'tin'                 => $application->tin,
            ],
            'checklist'          => $checklist,
            'checklist_progress' => $checklistProgress,
            'required_docs'      => $requiredCount,
            'satisfied_docs'     => $satisfiedCount,
            'documents'          => $documents->values()->all(),
            'identity'           => $identity,
            'decision' => [
                'status'       => $application->status,
                'reviewer'     => $application->reviewer,
                'reviewed_at'  => $application->reviewed_at,
                'admin_notes'  => $application->admin_notes,
                'partner'      => $application->partner,
                'partner_id'   => $application->partner_id,
            ],
            'rejection_reason_codes' => self::REJECTION_REASON_CODES,
        ];
    }

    /** @return array<int, array{at:?\Illuminate\Support\Carbon|string, label:string, detail:?string}> */
    private function activityTimeline(PartnerApplication $application, array $applicantDecl): array
    {
        $events = [];
        if ($application->created_at) {
            $events[] = [
                'at' => $application->created_at,
                'label' => 'Application submitted',
                'detail' => null,
            ];
        }
        if (! empty($applicantDecl['accepted_at'])) {
            $events[] = [
                'at' => $applicantDecl['accepted_at'],
                'label' => 'Declaration accepted',
                'detail' => $applicantDecl['version'] ?? null,
            ];
        }
        if ($application->status === 'needs_info' && $application->reviewed_at) {
            $events[] = [
                'at' => $application->reviewed_at,
                'label' => 'Information requested',
                'detail' => $application->admin_notes,
            ];
        }
        if ($application->status === 'rejected' && $application->reviewed_at) {
            $events[] = [
                'at' => $application->reviewed_at,
                'label' => 'Application declined',
                'detail' => $application->admin_notes,
            ];
        }
        if ($application->status === 'approved' && $application->reviewed_at) {
            $events[] = [
                'at' => $application->reviewed_at,
                'label' => 'Application approved',
                'detail' => $application->reviewer?->name
                    ? 'Reviewed by '.$application->reviewer->name
                    : null,
            ];
        }

        $payload = is_array($application->payload) ? $application->payload : [];
        foreach (is_array($payload['review_activity'] ?? null) ? $payload['review_activity'] : [] as $row) {
            if (! is_array($row) || blank($row['label'] ?? null)) {
                continue;
            }
            $events[] = [
                'at' => $row['at'] ?? null,
                'label' => (string) $row['label'],
                'detail' => $row['detail'] ?? null,
            ];
        }
        foreach (is_array($payload['info_requests'] ?? null) ? $payload['info_requests'] : [] as $req) {
            if (! is_array($req)) {
                continue;
            }
            if (($req['status'] ?? '') === 'submitted' && ! empty($req['submitted_at'])) {
                $events[] = [
                    'at' => $req['submitted_at'],
                    'label' => 'Information supplied',
                    'detail' => $req['label'] ?? null,
                ];
            }
        }

        if ($application->partner_id && $application->partner) {
            $events[] = [
                'at' => $application->partner->created_at ?? $application->reviewed_at,
                'label' => 'Partner account created',
                'detail' => $application->partner->vendor_number
                    ?: $application->partner->partner_number,
            ];
            if ($application->partner->activated_at) {
                $events[] = [
                    'at' => $application->partner->activated_at,
                    'label' => 'Partner activated',
                    'detail' => null,
                ];
            }
        }

        // De-dupe by label+at and sort chronologically.
        $events = collect($events)
            ->unique(fn ($e) => ($e['label'] ?? '').'|'.(string) ($e['at'] ?? ''))
            ->sortBy(function ($e) {
                try {
                    return \Illuminate\Support\Carbon::parse($e['at'] ?? now())->timestamp;
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->values()
            ->all();

        return $events;
    }

    /** @return array<int, string> */
    private function requiredDocTypes(string $category, string $applicantCategory): array
    {
        if ($applicantCategory === 'individual') {
            return ['national_id_front', 'national_id_back'];
        }

        $required = ['brela', 'tin_certificate', 'national_id_front', 'national_id_back'];

        if ($category === 'debt_collector') {
            $required[] = 'business_licence';
        }

        return $required;
    }

    /** @return array<string, mixed> */
    private function documentRow(PartnerApplicationDocument $doc): array
    {
        $mime = (string) ($doc->mime ?? '');
        $isImage = str_starts_with($mime, 'image/')
            || preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) ($doc->original_name ?? $doc->file_path ?? '')) === 1;

        return [
            'id'            => $doc->id,
            'doc_type'      => $doc->doc_type,
            'label'         => $doc->label(),
            'url'           => $doc->url(),
            'mime'          => $doc->mime,
            'original_name' => $doc->original_name,
            'is_image'      => $isImage,
            'created_at'    => $doc->created_at,
        ];
    }
}
