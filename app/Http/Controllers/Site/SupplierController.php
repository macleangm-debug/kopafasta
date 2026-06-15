<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetReservation;
use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\AssetReservationService;
use App\Services\MarketplaceAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupplierController extends Controller
{
    protected function supplier(): Vendor
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();
        abort_unless($vendor && $vendor->isSupplier(), 403, 'Supplier portal access requires an active supplier account.');

        return $vendor;
    }

    public function dashboard(): View
    {
        $vendor = $this->supplier();

        return view('site.supplier.dashboard', [
            'vendor' => $vendor,
            'stats'  => [
                'assets'       => MarketplaceAsset::where('vendor_id', $vendor->id)->count(),
                'reservations' => AssetReservation::whereHas('asset', fn ($q) => $q->where('vendor_id', $vendor->id))->whereNotIn('status', ['released', 'cancelled'])->count(),
                'requests'     => AssetRequest::where('vendor_id', $vendor->id)->where('status', '!=', 'closed')->count(),
                'pending_pay'  => (int) VendorPayment::where('vendor_id', $vendor->id)->where('status', 'pending')->sum('amount'),
            ],
        ]);
    }

    public function assets(): View
    {
        $vendor = $this->supplier();
        $assets = MarketplaceAsset::query()->where('vendor_id', $vendor->id)->latest()->paginate(20);

        return view('site.supplier.assets.index', compact('vendor', 'assets'));
    }

    public function createAsset(): View
    {
        $vendor = $this->supplier();

        return view('site.supplier.assets.form', [
            'vendor' => $vendor,
            'asset'  => null,
            'categories' => config('asset_marketplace.categories', []),
        ]);
    }

    public function storeAsset(Request $request, MarketplaceAssetService $assets): RedirectResponse
    {
        $vendor = $this->supplier();
        $data = $request->validate([
            'category'               => ['required', 'string', 'max:40'],
            'title'                  => ['required', 'string', 'max:150'],
            'description'            => ['nullable', 'string'],
            'asset_value'            => ['required', 'numeric', 'min:0'],
            'supplier_deposit'       => ['required', 'numeric', 'min:0'],
            'deposit_markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'weekly_installment'     => ['required', 'numeric', 'min:0'],
            'max_tenure_months'      => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $data['vendor_id'] = $vendor->id;
        $data['supplier_name'] = $vendor->name;
        $data['is_active'] = true;
        $data = $assets->prepareForSave($data);
        MarketplaceAsset::create($data);

        return redirect()->route('site.supplier.assets')->with('status', 'Asset uploaded successfully.');
    }

    public function editAsset(MarketplaceAsset $asset): View
    {
        $vendor = $this->supplier();
        abort_unless($asset->vendor_id === $vendor->id, 404);

        return view('site.supplier.assets.form', [
            'vendor' => $vendor,
            'asset'  => $asset,
            'categories' => config('asset_marketplace.categories', []),
        ]);
    }

    public function updateAsset(Request $request, MarketplaceAsset $asset, MarketplaceAssetService $assets): RedirectResponse
    {
        $vendor = $this->supplier();
        abort_unless($asset->vendor_id === $vendor->id, 404);

        $data = $request->validate([
            'category'               => ['required', 'string', 'max:40'],
            'title'                  => ['required', 'string', 'max:150'],
            'description'            => ['nullable', 'string'],
            'asset_value'            => ['required', 'numeric', 'min:0'],
            'supplier_deposit'       => ['required', 'numeric', 'min:0'],
            'deposit_markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'weekly_installment'     => ['required', 'numeric', 'min:0'],
            'max_tenure_months'      => ['required', 'integer', 'min:1', 'max:120'],
            'is_active'              => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data = $assets->prepareForSave($data, $asset);
        $asset->update($data);

        return redirect()->route('site.supplier.assets')->with('status', 'Asset updated.');
    }

    public function requests(): View
    {
        $vendor = $this->supplier();
        $requests = AssetRequest::query()->where('vendor_id', $vendor->id)->latest()->paginate(20);

        return view('site.supplier.requests', compact('vendor', 'requests'));
    }

    public function reservations(): View
    {
        $vendor = $this->supplier();
        $reservations = AssetReservation::query()
            ->with(['asset', 'customer', 'loanApplication'])
            ->whereHas('asset', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->latest()
            ->paginate(20);

        return view('site.supplier.reservations', compact('vendor', 'reservations'));
    }

    public function settlements(): View
    {
        $vendor = $this->supplier();
        $payments = VendorPayment::query()
            ->with('partnerSettlement')
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(20);

        return view('site.supplier.settlements', compact('vendor', 'payments'));
    }

    public function applications(): View
    {
        $vendor = $this->supplier();
        $applications = \App\Models\LoanApplication::query()
            ->with(['customer', 'product', 'assetReservation.asset', 'loan'])
            ->whereHas('assetReservation.asset', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->whereNotIn('status', ['withdrawn'])
            ->latest()
            ->paginate(20);

        return view('site.supplier.applications', compact('vendor', 'applications'));
    }

    public function delivered(): View
    {
        $vendor = $this->supplier();
        $reservations = AssetReservation::query()
            ->with(['asset', 'customer', 'loanApplication.loan'])
            ->whereHas('asset', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->whereIn('status', ['released'])
            ->latest('released_at')
            ->paginate(20);

        return view('site.supplier.delivered', compact('vendor', 'reservations'));
    }

    public function updateReservation(Request $request, AssetReservation $reservation): RedirectResponse
    {
        $vendor = $this->supplier();
        abort_unless($reservation->asset?->vendor_id === $vendor->id, 404);

        $action = $request->validate([
            'action' => ['required', 'in:confirm_viewing,complete_viewing,gps_installation,insurance_active'],
        ])['action'];

        if ($action === 'confirm_viewing' && $reservation->status === 'viewing_scheduled') {
            return back()->with('status', 'Viewing appointment acknowledged.');
        }

        if ($action === 'complete_viewing' && in_array($reservation->status, ['viewing_scheduled', 'viewing_completed'], true)) {
            app(AssetReservationService::class)->markViewingCompleted($reservation);

            return back()->with('status', 'Viewing marked complete by supplier.');
        }

        if (in_array($action, ['gps_installation', 'insurance_active'], true)) {
            app(AssetReservationService::class)->advance($reservation, $action);

            return back()->with('status', 'Reservation milestone updated.');
        }

        return back()->with('error', 'This reservation cannot be updated at its current stage.');
    }

    public function updateRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $vendor = $this->supplier();
        abort_unless($assetRequest->vendor_id === $vendor->id, 404);

        $data = $request->validate([
            'action'       => ['required', 'in:accept,decline'],
            'vendor_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] === 'accept') {
            $assetRequest->update([
                'status'      => 'reviewing',
                'admin_notes' => trim(($assetRequest->admin_notes ?? '')."\nSupplier accepted: ".($data['vendor_notes'] ?? '')),
            ]);

            return back()->with('status', 'Request accepted. Our team will follow up with the borrower.');
        }

        $assetRequest->update([
            'status'      => 'closed',
            'admin_notes' => trim(($assetRequest->admin_notes ?? '')."\nSupplier declined: ".($data['vendor_notes'] ?? '')),
        ]);

        return back()->with('status', 'Request declined.');
    }
}
