<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetReservation;
use App\Models\MarketplaceAsset;
use App\Models\NotificationLog;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorPayment;
use App\Services\AssetLendingService;
use App\Services\AssetReservationService;
use App\Services\MarketplaceAssetService;
use App\Services\PartnerPortalRedirectService;
use App\Services\PartnerProfileService;
use App\Services\SupplierPortalHomeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupplierController extends Controller
{
    protected function supplier(): Vendor
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();
        abort_unless($vendor, 403, 'Supplier portal access requires an active supplier account.');

        if (! $vendor->isSupplier()) {
            throw new HttpResponseException(
                redirect()
                    ->to(app(PartnerPortalRedirectService::class)->homeUrl(Auth::user()))
                    ->with('warning', __('site.partner_portal.redirect_from_supplier'))
            );
        }

        return $vendor;
    }

    /**
     * @return array<string, mixed>
     */
    private function assetFormData(Vendor $vendor, ?MarketplaceAsset $asset, AssetLendingService $lending): array
    {
        $price = (float) old('asset_value', $asset?->asset_value ?? 0);

        return [
            'vendor' => $vendor,
            'asset' => $asset,
            'categories' => config('asset_marketplace.categories', []),
            'quote' => $lending->pricingQuoteFromAssetPrice($price),
            'depositTiers' => $lending->depositTiers(),
            'markupPercent' => $lending->defaultDepositMarkupPercent(),
            'markupBase' => $lending->markupBase(),
            'productMaxTenureMonths' => $lending->productMaxTenureMonths(),
            'maxAssetPhotos' => app(MarketplaceAssetService::class)->maxPhotos(),
            'submitToken' => (string) Str::uuid(),
        ];
    }

    private function isDuplicateAssetSubmit(Request $request): bool
    {
        $token = trim((string) $request->input('_submit_token'));
        if ($token === '') {
            return false;
        }

        return ! Cache::add('supplier-asset-submit:'.Auth::id().':'.$token, 1, now()->addMinutes(15));
    }

    public function dashboard(SupplierPortalHomeService $home): View
    {
        $vendor = $this->supplier();
        $homeData = $home->dashboard($vendor);

        return view('site.supplier.dashboard', [
            'vendor' => $vendor,
            'stats' => $homeData['stats'],
            'attention' => $homeData['attention'],
            'recentPayments' => $homeData['recent_payments'],
            'assetActivity' => $homeData['asset_activity'],
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
        $lending = app(AssetLendingService::class);

        return view('site.supplier.assets.form', $this->assetFormData($vendor, null, $lending));
    }

    public function storeAsset(Request $request, MarketplaceAssetService $assets): RedirectResponse
    {
        $vendor = $this->supplier();
        if ($this->isDuplicateAssetSubmit($request)) {
            return redirect()->route('site.supplier.assets');
        }
        $assets->normalizeRequest($request);
        $validated = $request->validate($assets->validationRules(), $assets->validationMessages());
        unset($validated['photos'], $validated['remove_photos'], $validated['cover_path']);

        $data = $assets->prepareForSave(array_merge($validated, [
            'vendor_id' => $vendor->id,
            'supplier_name' => $vendor->name,
            'is_active' => $request->boolean('is_active', true),
        ]));

        $record = MarketplaceAsset::create($data);
        $assets->syncPhotos(
            $record,
            $request->file('photos', []),
            $request->input('remove_photos', []),
            $request->input('cover_path')
        );

        return redirect()->route('site.supplier.assets');
    }

    public function editAsset(MarketplaceAsset $asset): View
    {
        $vendor = $this->supplier();
        abort_unless($asset->vendor_id === $vendor->id, 404);
        $lending = app(AssetLendingService::class);

        return view('site.supplier.assets.form', $this->assetFormData($vendor, $asset, $lending));
    }

    public function updateAsset(Request $request, MarketplaceAsset $asset, MarketplaceAssetService $assets): RedirectResponse
    {
        $vendor = $this->supplier();
        abort_unless($asset->vendor_id === $vendor->id, 404);
        if ($this->isDuplicateAssetSubmit($request)) {
            return redirect()->route('site.supplier.assets');
        }

        $assets->normalizeRequest($request);
        $assets->validateMinimumPhotos($asset, $request->file('photos', []), $request->input('remove_photos', []));
        $validated = $request->validate($assets->validationRules($asset), $assets->validationMessages());
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

        return redirect()->route('site.supplier.assets');
    }

    public function requests(SupplierPortalHomeService $home): View
    {
        $vendor = $this->supplier();
        $reservations = AssetReservation::query()
            ->with(['asset', 'customer', 'loanApplication.loan'])
            ->whereHas('asset', fn ($q) => $q->where('partner_id', $vendor->id))
            ->whereIn('status', SupplierPortalHomeService::commercialStatuses())
            ->latest()
            ->paginate(20);

        $deals = $reservations->getCollection()->map(function (AssetReservation $row) use ($home, $vendor) {
            $money = $home->dealMoney($row, $vendor);

            return [
                'row' => $row,
                'buyer' => $row->customer?->legalDisplayName() ?: '—',
                'asset' => $row->asset?->title ?: '—',
                'collected' => $money['collected'],
                'remaining' => $money['remaining'],
                'deposit_label' => $money['deposit_label'],
            ];
        });

        return view('site.supplier.requests', compact('vendor', 'reservations', 'deals'));
    }

    public function reservations(): RedirectResponse
    {
        $this->supplier();

        return redirect()->route('site.supplier.requests');
    }

    public function settlements(): View
    {
        $vendor = $this->supplier();
        $payments = VendorPayment::query()
            ->with('partnerSettlement')
            ->where('partner_id', $vendor->id)
            ->latest()
            ->paginate(20);
        $payBase = VendorPayment::query()->where('partner_id', $vendor->id);

        return view('site.supplier.settlements', [
            'vendor' => $vendor,
            'payments' => $payments,
            'money' => [
                'available' => (float) (clone $payBase)->where('status', 'approved')->sum('amount'),
                'pending' => (float) (clone $payBase)->where('status', 'pending')->sum('amount'),
                'paid' => (float) (clone $payBase)->where('status', 'paid')->sum('amount'),
            ],
        ]);
    }

    public function applications(): RedirectResponse
    {
        $this->supplier();

        return redirect()->route('site.supplier.settlements');
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
            'action' => ['required', 'in:accept,decline'],
            'vendor_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] === 'accept') {
            $assetRequest->update([
                'status' => 'matched',
                'admin_notes' => trim(($assetRequest->admin_notes ?? '')."\nSupplier accepted: ".($data['vendor_notes'] ?? '')),
            ]);

            return back()->with('status', 'Request accepted. Our team will follow up with the borrower.');
        }

        $assetRequest->update([
            'status' => 'closed',
            'admin_notes' => trim(($assetRequest->admin_notes ?? '')."\nSupplier declined: ".($data['vendor_notes'] ?? '')),
        ]);

        return back()->with('status', 'Request declined.');
    }

    public function profile(Request $request, ?string $section = null): View|RedirectResponse
    {
        $vendor = $this->supplier();
        app(PartnerProfileService::class)->hydrateCanonicalIdentity($vendor);

        $section = $section ?: 'hub';
        $allowed = array_merge(['hub', 'card', 'documents', 'settings'], PartnerProfileService::SECTIONS);

        if (! in_array($section, $allowed, true)) {
            return redirect()->route('site.supplier.profile');
        }

        $common = [
            'partner' => $vendor,
            'portal' => 'supplier',
            'profileRoute' => 'site.supplier.profile',
            'updateRoute' => 'site.supplier.profile.update',
            'layoutComponent' => 'site.supplier-layout',
            'eyebrow' => __('site.supplier_portal.title'),
            'accountTabs' => [],
        ];

        if ($section === 'hub') {
            return view('site.partner-account.hub', $common + [
                'title' => __('site.supplier_portal.profile_title'),
                'subtitle' => __('site.supplier_portal.profile_subtitle'),
            ]);
        }

        if ($section === 'card') {
            return view('site.supplier.card', $common + [
                'title' => __('site.supplier_portal.card_title'),
            ]);
        }

        if ($section === 'documents') {
            return $this->documents();
        }

        if ($section === 'settings') {
            return $this->settings();
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
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store("vendor/{$vendor->id}/documents", 'public');

        VendorDocument::create([
            'vendor_id' => $vendor->id,
            'label' => $data['label'],
            'file_path' => $path,
            'mime' => $request->file('file')->getMimeType(),
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
        $notifications = NotificationLog::query()
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

        if ($request->expectsJson() || $request->ajax() || $request->header('X-KF-Autosave')) {
            return response()->json([
                'ok' => true,
                'saved' => true,
                'section' => $section,
                'message' => __('site.partner_account.save_profile'),
            ]);
        }

        return back()->with('status', __('site.partner_account.save_profile').' ✓');
    }
}
