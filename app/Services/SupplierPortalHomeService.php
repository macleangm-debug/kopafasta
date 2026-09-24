<?php

namespace App\Services;

use App\Models\AssetRequest;
use App\Models\AssetReservation;
use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SupplierPortalHomeService
{
    /**
     * Operational home facts from existing listings, reservations, and partner payments.
     *
     * @return array{
     *   stats: array{assets: int, active_financed: int, available: float, pending: float},
     *   attention: list<array{title: string, body: string, url: string, cta: string}>,
     *   recent_payments: Collection<int, VendorPayment>,
     *   asset_activity: Collection<int, MarketplaceAsset>
     * }
     */
    public function dashboard(Vendor $vendor): array
    {
        $profile = app(PartnerProfileService::class);

        $assetsListed = MarketplaceAsset::query()->where('partner_id', $vendor->id)->count();
        $activeFinanced = MarketplaceAsset::query()
            ->where('partner_id', $vendor->id)
            ->whereHas('reservations', fn ($q) => $q->whereNotIn('status', ['released', 'cancelled']))
            ->count();

        $payBase = VendorPayment::query()->where('partner_id', $vendor->id);
        $available = (float) (clone $payBase)->where('status', 'approved')->sum('amount');
        $pending = (float) (clone $payBase)->where('status', 'pending')->sum('amount');

        $recentPayments = VendorPayment::query()
            ->where('partner_id', $vendor->id)
            ->latest()
            ->limit(5)
            ->get();

        $assetActivity = MarketplaceAsset::query()
            ->where('partner_id', $vendor->id)
            ->withCount([
                'reservations as request_count',
                'reservations as active_count' => fn ($q) => $q->whereNotIn('status', ['released', 'cancelled']),
            ])
            ->orderByDesc('request_count')
            ->orderByDesc('active_count')
            ->limit(3)
            ->get();

        return [
            'stats' => [
                'assets' => $assetsListed,
                'active_financed' => $activeFinanced,
                'available' => $available,
                'pending' => $pending,
            ],
            'attention' => $this->attentionItems($vendor, $profile),
            'recent_payments' => $recentPayments,
            'asset_activity' => $assetActivity,
        ];
    }

    /**
     * @return list<array{title: string, body: string, url: string, cta: string}>
     */
    public function attentionItems(Vendor $vendor, PartnerProfileService $profile): array
    {
        $items = [];

        if (! $profile->isComplete($vendor)) {
            $next = collect($profile->sectionsFor($vendor))
                ->first(fn (string $key) => ! ($profile->sectionStatus($vendor, $key)['complete'] ?? false));
            $items[] = [
                'title' => __('site.supplier_portal.attention_profile_title'),
                'body' => __('site.supplier_portal.attention_profile_body', [
                    'percent' => $profile->completionPercent($vendor),
                ]),
                'url' => $next
                    ? route('site.supplier.profile', ['section' => $next])
                    : route('site.supplier.profile'),
                'cta' => __('site.supplier_portal.attention_profile_cta'),
            ];
        }

        if (! ($profile->sectionStatus($vendor, 'payment')['complete'] ?? false)) {
            $items[] = [
                'title' => __('site.supplier_portal.attention_payment_title'),
                'body' => __('site.supplier_portal.attention_payment_body'),
                'url' => route('site.supplier.profile', ['section' => 'payment']),
                'cta' => __('site.supplier_portal.attention_payment_cta'),
            ];
        }

        $requiredTypes = $profile->documentTypesFor($vendor);
        unset($requiredTypes['other']);
        $uploaded = VendorDocument::query()
            ->where('partner_id', $vendor->id)
            ->get();
        $uploadedTypes = $uploaded->pluck('doc_type')->filter()->all();
        $missingRequired = collect(array_keys($requiredTypes))
            ->reject(fn (string $type) => in_array($type, $uploadedTypes, true))
            ->values();
        if ($missingRequired->isNotEmpty() && $uploaded->isEmpty()) {
            $items[] = [
                'title' => __('site.supplier_portal.attention_docs_title'),
                'body' => __('site.supplier_portal.attention_docs_body'),
                'url' => route('site.supplier.documents'),
                'cta' => __('site.supplier_portal.attention_docs_cta'),
            ];
        }

        $handover = AssetReservation::query()
            ->whereHas('asset', fn ($q) => $q->where('partner_id', $vendor->id))
            ->whereIn('status', ['viewing_scheduled', 'post_approval_fees_paid', 'gps_installation'])
            ->count();
        if ($handover > 0) {
            $items[] = [
                'title' => __('site.supplier_portal.attention_handover_title'),
                'body' => __('site.supplier_portal.attention_handover_body', ['count' => $handover]),
                'url' => route('site.supplier.requests'),
                'cta' => __('site.supplier_portal.attention_handover_cta'),
            ];
        }

        if (Schema::hasColumn('asset_requests', 'partner_id')) {
            $openRequests = AssetRequest::query()
                ->where('partner_id', $vendor->id)
                ->whereIn('status', ['reviewing', 'matched'])
                ->count();
            if ($openRequests > 0) {
                $items[] = [
                    'title' => __('site.supplier_portal.attention_requests_title'),
                    'body' => __('site.supplier_portal.attention_requests_body', ['count' => $openRequests]),
                    'url' => route('site.supplier.requests'),
                    'cta' => __('site.supplier_portal.attention_requests_cta'),
                ];
            }
        }

        return $items;
    }

    /**
     * Display label for an existing reservation / request status. No new state engine.
     */
    public function requestStatusLabel(string $status): string
    {
        $key = 'site.supplier_portal.request_status.'.$status;
        $label = __($key);

        return $label === $key
            ? str_replace('_', ' ', ucfirst($status))
            : $label;
    }
}
