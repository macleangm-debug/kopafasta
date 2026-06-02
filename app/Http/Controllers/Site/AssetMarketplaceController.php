<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetReservation;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Services\ApplicationRequirementsService;
use App\Services\AssetMarketplaceFeeService;
use App\Services\AssetReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetMarketplaceController extends Controller
{
    use AuditsActions;

    public function index(Request $request): View
    {
        $category = $request->query('category');
        $filters = [
            'q'         => trim((string) $request->query('q', '')),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'tenure'    => $request->query('tenure'),
        ];
        $assets = $this->loadAssets($category, $filters);

        return view('site.borrower.marketplace.index', [
            'assets'     => $assets,
            'categories' => config('asset_marketplace.categories', []),
            'category'   => $category,
            'filters'    => $filters,
        ]);
    }

    public function show(string $assetId): View
    {
        $asset = $this->findAsset($assetId);
        abort_if(! $asset, 404);

        $reservation = null;
        if ($customer = auth()->user()?->customer) {
            $model = $this->findModel($assetId);
            if ($model) {
                $reservation = app(AssetReservationService::class)->activeForCustomer($customer, $model);
            }
        }

        $applyUrl = route('site.borrower.marketplace.apply', $asset['id']);

        return view('site.borrower.marketplace.show', compact('asset', 'reservation', 'applyUrl'));
    }

    public function startApply(string $assetId): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->findModel($assetId);
        abort_if(! $model, 404);

        app(AssetReservationService::class)->startApplication($customer, $model);

        return redirect()
            ->route('site.borrower.marketplace.reserve', $assetId)
            ->with('status', __('borrower.marketplace.started'));
    }

    public function reserve(Request $request, string $assetId): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->findModel($assetId);
        abort_if(! $model, 404);

        $request->validate([
            'viewing_date' => ['required', 'date', 'after:today'],
            'viewing_time' => ['required', 'string', 'max:20'],
        ]);

        $service = app(AssetReservationService::class);
        $reservation = $service->activeForCustomer($customer, $model)
            ?? $service->createReservation($customer, $model, $request->input('viewing_date'), $request->input('viewing_time'));

        return redirect()
            ->route('site.borrower.marketplace.reserve', $assetId)
            ->with('status', 'Viewing scheduled. Complete the viewing step to continue your asset application.');
    }

    public function reserveFlow(string $assetId, AssetReservationService $reservations, ApplicationRequirementsService $requirements): View|RedirectResponse
    {
        $asset = $this->findAsset($assetId);
        abort_if(! $asset, 404);

        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->findModel($assetId);
        abort_if(! $model, 404);

        $reservation = $reservations->activeForCustomer($customer, $model);
        if (! $reservation) {
            return redirect()->route('site.borrower.marketplace.show', $assetId)
                ->with('warning', 'Apply for this asset and choose a viewing slot first.');
        }

        $steps = $reservations->steps($reservation);
        $applyRequirements = $requirements->checklist($customer);
        $feeBreakdown = app(AssetMarketplaceFeeService::class)->breakdown($customer, $model);

        return view('site.borrower.marketplace.reserve', compact('asset', 'reservation', 'steps', 'applyRequirements', 'feeBreakdown'));
    }

    public function advanceReservation(Request $request, string $assetId, AssetReservationService $reservations): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->findModel($assetId);
        abort_if(! $model, 404);

        $reservation = $reservations->activeForCustomer($customer, $model);
        abort_unless($reservation, 404);

        $action = $request->validate([
            'action' => ['required', 'in:skip_viewing,complete_viewing,confirm_interest,pay_reservation_fee,pay_deposit'],
        ])['action'];

        if (in_array($action, ['pay_reservation_fee', 'pay_deposit'], true)) {
            $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
            if (! $checklist['can_apply']) {
                return back()->with('error', __('borrower.marketplace.requirements_before_payment'));
            }
        }

        $reservations->advance($reservation, $action);

        $message = match ($action) {
            'skip_viewing'          => __('borrower.marketplace.viewing_skipped'),
            'complete_viewing'      => __('borrower.marketplace.viewing_completed'),
            'confirm_interest'      => __('borrower.marketplace.interest_confirmed'),
            'pay_reservation_fee'   => __('borrower.marketplace.application_fee_recorded'),
            'pay_deposit'           => __('borrower.marketplace.deposit_recorded'),
            default                 => __('borrower.marketplace.progress_updated'),
        };

        return back()->with('status', $message);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $data = $request->validate([
            'asset_name'              => ['required', 'string', 'max:150'],
            'budget'                  => ['nullable', 'numeric', 'min:0'],
            'preferred_tenure_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'photo'                   => ['nullable', 'image', 'max:5120'],
        ]);

        $path = $request->hasFile('photo')
            ? $request->file('photo')->store("customer/{$customer->id}/asset-requests", 'public')
            : null;

        AssetRequest::create([
            'customer_id'             => $customer->id,
            'asset_name'              => $data['asset_name'],
            'budget'                  => $data['budget'] ?? null,
            'preferred_tenure_months' => $data['preferred_tenure_months'] ?? null,
            'photo_path'              => $path,
            'status'                  => 'pending',
        ]);

        return redirect()
            ->route('site.borrower.marketplace')
            ->with('status', 'Your asset request has been submitted. We will notify you when a match is available.');
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function loadAssets(?string $category, array $filters = [])
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('marketplace_assets') && MarketplaceAsset::query()->exists()) {
            return MarketplaceAsset::query()
                ->where('is_active', true)
                ->when($category, fn ($q) => $q->where('category', $category))
                ->when(filled($filters['q'] ?? null), fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%'))
                ->when(filled($filters['min_price'] ?? null), fn ($q) => $q->where('asset_value', '>=', (float) $filters['min_price']))
                ->when(filled($filters['max_price'] ?? null), fn ($q) => $q->where('asset_value', '<=', (float) $filters['max_price']))
                ->when(filled($filters['tenure'] ?? null), fn ($q) => $q->where('max_tenure_months', '<=', (int) $filters['tenure']))
                ->orderBy('title')
                ->get()
                ->map(fn (MarketplaceAsset $a) => $this->normalizeAsset($a))
                ->values();
        }

        return collect(config('asset_marketplace.assets', []))
            ->when($category, fn ($c) => $c->where('category', $category))
            ->when(filled($filters['q'] ?? null), fn ($c) => $c->filter(
                fn (array $asset) => str_contains(strtolower($asset['title'] ?? ''), strtolower($filters['q']))
            ))
            ->values();
    }

    private function findModel(string $assetId): ?MarketplaceAsset
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplace_assets')) {
            return null;
        }

        return MarketplaceAsset::query()
            ->where('is_active', true)
            ->where(function ($q) use ($assetId): void {
                $q->where('slug', $assetId);
                if (is_numeric($assetId)) {
                    $q->orWhere('id', (int) $assetId);
                }
            })
            ->first();
    }

    private function findAsset(string $assetId): ?array
    {
        $model = $this->findModel($assetId);
        if ($model) {
            return $this->normalizeAsset($model);
        }

        return collect(config('asset_marketplace.assets', []))->firstWhere('id', $assetId);
    }

    /** @return array<string, mixed> */
    private function normalizeAsset(MarketplaceAsset $asset): array
    {
        $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
        $assetValue = (float) ($asset->asset_value ?: ($deposit * 1.4));
        $remainingLoan = max(0, round($assetValue - $deposit, 2));

        return [
            'id'                   => $asset->slug ?: (string) $asset->id,
            'category'             => $asset->category,
            'title'                => $asset->title,
            'vendor'               => $asset->supplier_name,
            'supplier'             => $asset->supplier_name,
            'description'          => $asset->description,
            'asset_value'          => $assetValue,
            'deposit'              => $deposit,
            'remaining_loan'       => $remainingLoan,
            'supplier_deposit'     => (float) $asset->supplier_deposit,
            'weekly_installment'   => (float) $asset->weekly_installment,
            'max_tenure_months'    => effective_marketplace_asset_max_tenure($asset),
            'photos'               => $asset->photos ?? [],
        ];
    }

}
