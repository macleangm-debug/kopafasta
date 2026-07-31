<?php

namespace App\Services;

use App\Models\PartnerApplication;
use App\Models\PartnerApplicationDocument;
use App\Models\PartnerDocument;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PartnerEnrollmentService
{
    /** Canonical categories available on the public partners apply form. */
    public const ENROLLABLE_CATEGORIES = [
        'debt_collector' => 'Collection partner',
        'valuer' => 'Valuer',
        'gps_installer' => 'GPS installer',
        'insurance' => 'Insurance provider',
        'yard' => 'Yard partner',
        'auctioneer' => 'Auctioneer',
        'legal_partner' => 'Legal partner',
        'call_center' => 'Call center',
    ];

    /** Map legacy public-register labels → canonical partner categories. */
    public const CATEGORY_ALIASES = [
        'collection_partner' => 'debt_collector',
        'insurance_provider' => 'insurance',
        'yard_partner' => 'yard',
        'other' => 'supplier',
    ];

    public function normalizeCategory(string $category): string
    {
        $category = strtolower(trim($category));

        return self::CATEGORY_ALIASES[$category] ?? $category;
    }

    /** @return list<string> */
    public function enrollableCategoryKeys(): array
    {
        return array_keys(self::ENROLLABLE_CATEGORIES);
    }

    public function categoryLabel(string $category): string
    {
        $normalized = $this->normalizeCategory($category);

        return self::ENROLLABLE_CATEGORIES[$normalized]
            ?? ($normalized === 'affiliate' ? 'Affiliate' : ucfirst(str_replace('_', ' ', $normalized)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function submitApplication(array $data, array $documents = []): PartnerApplication
    {
        $category = $this->normalizeCategory((string) ($data['partner_category'] ?? $data['type'] ?? ''));
        if ($category === '' || (! isset(self::ENROLLABLE_CATEGORIES[$category]) && $category !== 'affiliate')) {
            throw ValidationException::withMessages([
                'partner_category' => 'Select a valid partner type.',
            ]);
        }

        $applicantCategory = (string) ($data['applicant_category'] ?? 'company');
        $isIndividual = $applicantCategory === 'individual';

        if ($isIndividual) {
            $this->assertRequiredDocuments($documents, $category, 'individual');
        } else {
            $requiresBusinessDocs = $category === 'debt_collector'
                || (
                    isset(self::ENROLLABLE_CATEGORIES[$category])
                    && in_array($applicantCategory, ['company', 'institution'], true)
                )
                || $category === 'affiliate';

            if ($requiresBusinessDocs || isset(self::ENROLLABLE_CATEGORIES[$category])) {
                $this->assertRequiredDocuments($documents, $category, 'company');
            }
        }

        return DB::transaction(function () use ($data, $documents, $category) {
            $application = PartnerApplication::create([
                'type' => $category === 'affiliate' ? 'affiliate' : 'service',
                'partner_category' => $category,
                'applicant_category' => $data['applicant_category'] ?? 'company',
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'business_name' => $data['business_name'] ?? null,
                'legal_name' => $data['legal_name'] ?? ($data['business_name'] ?? null),
                'registration_number' => $data['registration_number'] ?? null,
                'tin' => $data['tin'] ?? null,
                'region' => $data['region'] ?? null,
                'coverage_regions' => array_values(array_filter($data['coverage_regions'] ?? [])),
                'message' => $data['message'] ?? null,
                'status' => 'pending',
            ]);

            $this->storeApplicationDocuments($application, $documents);

            return $application->fresh('documents');
        });
    }

    /**
     * Promote an approved application into a Partner and send activation invite.
     */
    public function convertToPartner(PartnerApplication $application, ?\App\Models\User $actor = null): Vendor
    {
        if ($application->partner_id) {
            throw ValidationException::withMessages([
                'status' => 'This application was already converted to a partner.',
            ]);
        }

        if ($application->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Approve the application before creating a partner account.',
            ]);
        }

        $category = $this->normalizeCategory(
            (string) ($application->partner_category ?: ($application->type === 'affiliate' ? 'affiliate' : 'debt_collector'))
        );

        return DB::transaction(function () use ($application, $category, $actor) {
            $partner = Vendor::create([
                'vendor_number' => app(PartnerCodeService::class)->generate($category),
                'name' => $application->business_name ?: $application->full_name,
                'legal_name' => $application->legal_name,
                'registration_number' => $application->registration_number,
                'tin' => $application->tin,
                'category' => $category,
                'roles' => [$category],
                'phone' => $application->phone,
                'email' => $application->email,
                'address' => $application->region,
                'regions' => $application->coverage_regions ?: array_filter([$application->region]),
                'coverage_type' => filled($application->coverage_regions) || filled($application->region) ? 'regions' : 'nationwide',
                'status' => 'inactive',
            ]);

            foreach ($application->documents as $doc) {
                $dest = 'partners/'.$partner->id.'/compliance/'.basename($doc->file_path);
                if (Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->copy($doc->file_path, $dest);
                }

                PartnerDocument::create([
                    'partner_id' => $partner->id,
                    'label' => $doc->label(),
                    'doc_type' => $doc->doc_type,
                    'file_path' => Storage::disk('public')->exists($dest) ? $dest : $doc->file_path,
                    'mime' => $doc->mime,
                    'size_bytes' => $doc->size_bytes,
                ]);
            }

            if ($partner->isAffiliate()) {
                app(AffiliateService::class)->ensureCode($partner);
                app(AffiliateLifecycleService::class)->initializeNewAffiliate($partner->refresh());
            }

            if (app(PartnerActivationService::class)->requiresActivation($partner)) {
                app(PartnerActivationService::class)->sendActivationInvite($partner, $actor);
            }

            $application->update([
                'partner_id' => $partner->id,
                'reviewed_by' => $actor?->id ?? $application->reviewed_by,
                'reviewed_at' => $application->reviewed_at ?? now(),
            ]);

            return $partner->fresh();
        });
    }

    /**
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function storeApplicationDocuments(PartnerApplication $application, array $documents): void
    {
        foreach ($documents as $docType => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! array_key_exists($docType, PartnerApplicationDocument::DOC_TYPES)) {
                continue;
            }

            $path = $file->store('partner-applications/'.$application->id, 'public');

            PartnerApplicationDocument::create([
                'partner_application_id' => $application->id,
                'doc_type' => $docType,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }

    /**
     * @param  array<string, UploadedFile|null>  $documents
     */
    private function assertRequiredDocuments(array $documents, string $category, string $applicantCategory = 'company'): void
    {
        if ($applicantCategory === 'individual') {
            $required = ['national_id_front', 'national_id_back'];
        } else {
            $required = ['brela', 'tin_certificate', 'national_id_front', 'national_id_back'];
            if ($category === 'debt_collector') {
                $required[] = 'business_licence';
            }
        }

        $missing = [];
        foreach ($required as $type) {
            if (! ($documents[$type] ?? null) instanceof UploadedFile) {
                $missing[] = PartnerApplicationDocument::DOC_TYPES[$type] ?? $type;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'documents' => 'Please upload: '.implode(', ', $missing).'.',
            ]);
        }
    }
}
