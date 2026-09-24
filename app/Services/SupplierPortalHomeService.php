<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\MarketplaceAsset;
use App\Models\Repayment;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorPayment;
use Illuminate\Support\Collection;

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
            ->whereHas('reservations', fn ($q) => $q->whereIn('status', self::commercialStatuses()))
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
                'reservations as request_count' => fn ($q) => $q->whereIn('status', self::commercialStatuses()),
                'reservations as active_count' => fn ($q) => $q->whereIn('status', self::commercialStatuses()),
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

        return $items;
    }

    /**
     * Approved / deposit-stage deals only. No sourcing, viewing, or handover queue.
     *
     * @return list<string>
     */
    public static function commercialStatuses(): array
    {
        return [
            'reservation_fee_paid',
            'deposit_paid',
            'application_submitted',
            'approved',
            'post_approval_fees_paid',
            'gps_installation',
            'insurance_active',
            'registration_complete',
        ];
    }

    /**
     * @return array{collected: float, remaining: float, deposit_label: string}
     */
    public function dealMoney(AssetReservation $row, Vendor $vendor): array
    {
        $asset = $row->asset;
        $assetValue = (float) ($asset?->asset_value ?? 0);
        $deposit = (float) ($asset?->supplier_deposit ?? 0);
        $financed = max(0.0, $assetValue - $deposit);

        $collected = 0.0;
        $loan = $row->loanApplication?->loan;
        if ($loan) {
            $repaymentIds = Repayment::query()->where('loan_id', $loan->id)->pluck('id');
            if ($repaymentIds->isNotEmpty()) {
                $collected = (float) VendorPayment::query()
                    ->where('partner_id', $vendor->id)
                    ->where('source_type', 'managed_loan_repayment')
                    ->whereIn('source_id', $repaymentIds)
                    ->whereIn('status', ['pending', 'approved', 'paid'])
                    ->sum('amount');
            }
        }

        return [
            'collected' => $collected,
            'remaining' => max(0.0, $financed - $collected),
            'deposit_label' => ($row->deposit_status ?? '') === 'paid'
                ? __('site.supplier_portal.buyer_deposit_paid')
                : __('site.supplier_portal.buyer_waiting_deposit'),
        ];
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
