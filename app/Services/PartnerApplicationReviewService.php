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

        return [
            'applicant' => [
                'full_name'          => $application->full_name,
                'phone'              => $application->phone,
                'email'              => $application->email,
                'applicant_category' => $application->applicant_category,
                'category'           => $category,
                'category_label'     => $this->enrollment->categoryLabel($category),
                'region'             => $application->region,
                'coverage_regions'   => $application->coverage_regions ?: [],
                'message'            => $application->message,
            ],
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
