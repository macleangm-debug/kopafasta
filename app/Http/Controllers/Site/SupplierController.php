<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetReservation;
use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorPayment;
use App\Services\AssetReservationService;
use App\Services\MarketplaceAssetService;
use App\Services\PartnerProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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
                'assets'       => MarketplaceAsset::where('partner_id', $vendor->id)->count(),
                'reservations' => AssetReservation::whereHas('asset', fn ($q) => $q->where('partner_id', $vendor->id))->whereNotIn('status', ['released', 'cancelled'])->count(),
                'requests'     => Schema::hasColumn('asset_requests', 'partner_id')
                    ? AssetRequest::where('partner_id', $vendor->id)->whereIn('status', ['reviewing', 'matched'])->count()
                    : 0,
                'pending_pay'  => (int) VendorPayment::where('partner_id', $vendor->id)->where('status', 'pending')->sum('amount'),
            ],
        ]);
    }

    public function assets(): View
    {
        $vendor = $this->supplier();
        $assets = MarketplaceAsset::query()->where('partner_id', $vendor->id)->latest()->paginate(20);

        return view('site.supplier.assets.index', compact('vendor', 'assets'));
    }

    public function createAsset(): View
    {
        $vendor = $this->supplier();
        $lending = app(\App\Services\AssetLendingService::class);

        return view('site.supplier.assets.form', [
            'vendor'                      => $vendor,
            'asset'                       => null,
            'categories'                  => config('asset_marketplace.categories', []),
            'defaultDepositMarkupPercent' => $lending->defaultDepositMarkupPercent(),
            'maxAssetPhotos'              => app(MarketplaceAssetService::class)->maxPhotos(),
        ]);
    }

    public function storeAsset(Request $request, MarketplaceAssetService $assets): RedirectResponse
    {
        $vendor = $this->supplier();
        $assets->normalizeRequest($request);
        $validated = $request->validate($assets->validationRules());
        unset($validated['photos'], $validated['remove_photos'], $validated['cover_path']);

        $data = $assets->prepareForSave(array_merge($validated, [
            'vendor_id'     => $vendor->id,
            'supplier_name' => $vendor->name,
            'is_active'     => true,
        ]));

        $record = MarketplaceAsset::create($data);
        $assets->syncPhotos(
            $record,
            $request->file('photos', []),
            $request->input('remove_photos', []),
            $request->input('cover_path')
        );

        return redirect()->route('site.supplier.assets')->with('status', 'Asset uploaded successfully.');
    }

    public function editAsset(MarketplaceAsset $asset): View
    {
        $vendor = $this->supplier();
        abort_unless($asset->vendor_id === $vendor->id, 404);
        $lending = app(\App\Services\AssetLendingService::class);

        return view('site.supplier.assets.form', [
            'vendor'                      => $vendor,
            'asset'                       => $asset,
            'categories'                  => config('asset_marketplace.categories', []),
            'defaultDepositMarkupPercent' => $lending->defaultDepositMarkupPercent(),
            'maxAssetPhotos'              => app(MarketplaceAssetService::class)->maxPhotos(),
        ]);
    }

    public function updateAsset(Request $request, MarketplaceAsset $asset, MarketplaceAssetService $assets): RedirectResponse
    {
        $vendor = $this->supplier();
        abort_unless($asset->vendor_id === $vendor->id, 404);

        $assets->normalizeRequest($request);
        $assets->validateMinimumPhotos($asset, $request->file('photos', []), $request->input('remove_photos', []));
        $validated = $request->validate($assets->validationRules($asset));
        unset($validated['photos'], $validated['remove_photos'], $validated['cover_path']);
        $data = $assets->prepareForSave(array_merge($validated, [
            'is_active' => $request->boolean('is_active', true),
        ]), $asset);

        $asset->update($data);
        $assets->syncPhotos(
            $asset,
            $request->file('photos', []),
            $request->input('remove_photos', []),
            $request->input('cover_path')
        );

        return redirect()->route('site.supplier.assets')->with('status', 'Asset updated.');
    }

    public function requests(): View
    {
        $vendor = $this->supplier();
        // Only admin-released requests (assigned + reviewing/matched) appear here.
        $requests = AssetRequest::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('status', ['reviewing', 'matched'])
            ->latest()
            ->paginate(20);

        return view('site.supplier.requests', compact('vendor', 'requests'));
    }

    public function reservations(): View
    {
        $vendor = $this->supplier();
        $reservations = AssetReservation::query()
            ->with(['asset', 'customer', 'loanApplication'])
            ->whereHas('asset', fn ($q) => $q->where('partner_id', $vendor->id))
            ->latest()
            ->paginate(20);

        return view('site.supplier.reservations', compact('vendor', 'reservations'));
    }

    public function settlements(): View
    {
        $vendor = $this->supplier();
        $payments = VendorPayment::query()
            ->with('partnerSettlement')
            ->where('partner_id', $vendor->id)
            ->latest()
            ->paginate(20);

        return view('site.supplier.settlements', compact('vendor', 'payments'));
    }

    public function applications(): View
    {
        $vendor = $this->supplier();
        $applications = \App\Models\LoanApplication::query()
            ->with(['customer', 'product', 'assetReservation.asset', 'loan'])
            ->whereHas('assetReservation.asset', fn ($q) => $q->where('partner_id', $vendor->id))
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
            ->whereHas('asset', fn ($q) => $q->where('partner_id', $vendor->id))
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
                'status'      => 'matched',
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

    public function profile(Request $request, ?string $section = null): View|RedirectResponse
    {
        $vendor = $this->supplier();

        $section = $section ?: 'hub';

        if (! in_array($section, array_merge(['hub'], PartnerProfileService::SECTIONS), true)) {
            return redirect()->route('site.supplier.profile');
        }

        $common = [
            'partner'         => $vendor,
            'portal'          => 'supplier',
            'profileRoute'    => 'site.supplier.profile',
            'updateRoute'     => 'site.supplier.profile.update',
            'layoutComponent' => 'site.supplier-layout',
            'eyebrow'         => __('site.supplier_portal.title'),
            'accountTabs'     => [
                ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.supplier.profile')],
                ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.supplier.documents')],
                ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.supplier.settings')],
            ],
        ];

        if ($section === 'hub') {
            return view('site.partner-account.hub', $common + [
                'title'    => __('site.partner_account.hub_title'),
                'subtitle' => __('site.partner_account.hub_subtitle'),
            ]);
        }

        return view('site.partner-account.'.$section, $common + [
            'title' => __('site.partner_account.'.$section.'_section'),
        ]);
    }

    public function documents(): View
    {
        $vendor = $this->supplier();
        $documents = VendorDocument::query()
            ->where('partner_id', $vendor->id)
            ->with('task')
            ->latest()
            ->paginate(20);

        return view('site.supplier.documents', compact('vendor', 'documents'));
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $vendor = $this->supplier();
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'file'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store("vendor/{$vendor->id}/documents", 'public');

        VendorDocument::create([
            'vendor_id'  => $vendor->id,
            'label'      => $data['label'],
            'file_path'  => $path,
            'mime'       => $request->file('file')->getMimeType(),
            'size_bytes' => $request->file('file')->getSize(),
        ]);

        return back()->with('status', __('site.partner_account.upload').' ✓');
    }

    public function settings(): View
    {
        $vendor = $this->supplier();

        return view('site.supplier.settings', compact('vendor'));
    }

    public function notifications(): View
    {
        $vendor = $this->supplier();
        $notifications = \App\Models\NotificationLog::query()
            ->when(
                Schema::hasColumn('notification_logs', 'user_id'),
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('recipient', Auth::user()?->email)
                        ->orWhere('recipient', Auth::user()?->phone);
                })
            )
            ->latest()
            ->paginate(20);

        return view('site.supplier.notifications', compact('vendor', 'notifications'));
    }

    public function updateProfile(Request $request, string $section = 'personal'): RedirectResponse
    {
        $vendor = $this->supplier();

        if (! in_array($section, PartnerProfileService::SECTIONS, true)) {
            abort(404);
        }

        app(PartnerProfileService::class)->updateSection($vendor, $section, $request);

        return back()->with('status', __('site.partner_account.save_profile').' ✓');
    }
}
