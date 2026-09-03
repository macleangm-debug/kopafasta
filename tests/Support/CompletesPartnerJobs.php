<?php

namespace Tests\Support;

use App\Models\Vendor;
use App\Services\PartnerMembershipService;
use App\Services\ValuationPartnerService;

trait CompletesPartnerJobs
{
    protected function completePartnerForJobs(Vendor $partner, bool $payMembership = true): Vendor
    {
        $meta = is_array($partner->metadata) ? $partner->metadata : [];
        $partner->update([
            'phone' => $partner->phone ?: '255700000001',
            'email' => $partner->email ?: 'jobs-'.$partner->id.'@test.local',
            'legal_name' => $partner->legal_name ?: trim($partner->name.' Ltd'),
            'registration_number' => $partner->registration_number ?: 'REG-JOB-'.$partner->id,
            'coverage_type' => $partner->coverage_type ?: 'nationwide',
            'metadata' => array_replace_recursive($meta, [
                'contact_person' => ['name' => $meta['contact_person']['name'] ?? 'Job Ready'],
                'identity' => [
                    'national_id' => $meta['identity']['national_id'] ?? '19800101123456789012',
                    'no_physical_nida_card' => true,
                ],
                'residence' => [
                    'region' => $meta['residence']['region'] ?? 'Dar es Salaam',
                    'district' => $meta['residence']['district'] ?? 'Ilala',
                ],
                'payout_account' => ['type' => $meta['payout_account']['type'] ?? 'mobile_money'],
                'face_captures' => [
                    'front' => $meta['face_captures']['front'] ?? 'partners/face-front.jpg',
                    'left' => $meta['face_captures']['left'] ?? 'partners/face-left.jpg',
                    'right' => $meta['face_captures']['right'] ?? 'partners/face-right.jpg',
                ],
            ]),
        ]);

        $partner = $partner->fresh();
        if ($payMembership) {
            app(PartnerMembershipService::class)->activate($partner);
        }

        $terms = app(\App\Services\PartnerTermsService::class);
        if ($terms->appliesTo($partner) && ! $terms->hasSatisfiedTerms($partner)) {
            $terms->accept($partner, \Illuminate\Http\Request::create('/partner/terms', 'POST'));
        }

        return $partner->fresh();
    }

    protected function placeWaitingValuerJobs(Vendor $partner, ?\App\Models\User $actor = null): int
    {
        return app(ValuationPartnerService::class)->assignWaitingJobsCoveredBy($partner->fresh(), $actor);
    }

    /** @return array<string, mixed> */
    protected function completeVehicleAssetAttributes(array $overrides = []): array
    {
        return array_replace_recursive([
            'asset_type' => 'vehicle',
            'label' => 'Toyota Rav4',
            'is_active' => true,
            'registration_number' => 'T123ABC',
            'photo_paths' => [
                'assets/front.jpg',
                'assets/back.jpg',
                'assets/left.jpg',
                'assets/right.jpg',
            ],
            'metadata' => [
                'photo_angles' => [
                    'front' => 'assets/front.jpg',
                    'back' => 'assets/back.jpg',
                    'left' => 'assets/left.jpg',
                    'right' => 'assets/right.jpg',
                ],
                'person_with_asset_path' => 'assets/owner.jpg',
                'details' => ['insurance_expires_at' => now()->addYears(3)->toDateString()],
                'insurance_document_path' => 'assets/ins.pdf',
                'ownership_document_path' => 'assets/title.pdf',
            ],
        ], $overrides);
    }
}
