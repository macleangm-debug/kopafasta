<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetReservation;
use App\Models\MarketplaceAsset;
use App\Services\ApplicationRequirementsService;
use App\Services\AssetMarketplaceFeeService;
use App\Services\AssetReservationPaymentService;
use App\Services\AssetReservationService;
use App\Services\CustomerPaymentService;
use App\Services\PaymentAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetMarketplaceController extends Controller
{
    use AuditsActions;

    /** @return list<array<string, mixed>> */
    public function homepageFeatured(int $limit = 6): array
    {
        return $this->loadAssets(null, ['sort' => 'title'])->take($limit)->values()->all();
    }

    public function index(Request $request): View
    {
        return $this->renderIndex($request, 'site.borrower.marketplace.index', true);
    }

    public function publicIndex(Request $request): View
    {
        return $this->renderIndex($request, 'site.public.marketplace.index', false);
    }

    public function publicShow(string $assetId): View
    {
        $asset = $this->findAsset($assetId);
        abort_if(! $asset, 404);

        $loginUrl = route('site.login', ['redirect' => route('site.borrower.marketplace.show', $assetId)]);
        $relatedAssets = $this->relatedAssets($asset);

        return view('site.public.marketplace.show', compact('asset', 'loginUrl', 'relatedAssets'));
    }

    private function renderIndex(Request $request, string $view, bool $authenticated): View
    {
        $category = $request->query('category');
        $filters = [
            'q'         => trim((string) $request->query('q', '')),
            'brand'     => trim((string) $request->query('brand', '')),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'tenure'    => $request->query('tenure'),
            'sort'      => $request->query('sort', 'title'),
        ];
        $assets = $this->loadAssets($category, $filters);

        return view($view, [
            'assets'     => $assets,
            'categories' => config('asset_marketplace.categories', []),
            'category'   => $category,
            'filters'    => $filters,
            'authenticated' => $authenticated,
        ]);
    }

    public function show(string $assetId): View
    {
        $customer = auth()->user()?->customer;
        $asset = $this->findAsset($assetId, $customer);
        abort_if(! $asset, 404);

        $reservation = null;
        if ($customer) {
            $model = $this->resolveModel($assetId);
            if ($model) {
                $reservation = app(AssetReservationService::class)->activeForCustomer($customer, $model);
            }
        }

        $applyUrl = route('site.borrower.marketplace.apply', $asset['id']);
        $relatedAssets = $this->relatedAssets($asset);

        return view('site.borrower.marketplace.show', compact('asset', 'reservation', 'applyUrl', 'relatedAssets'));
    }

    public function startApply(string $assetId): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->resolveModel($assetId);
        abort_if(! $model, 404);

        try {
            app(AssetReservationService::class)->startApplication($customer, $model);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('site.borrower.marketplace.show', $assetId)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('site.borrower.marketplace.reserve', $assetId)
            ->with('status', __('borrower.marketplace.started'));
    }

    public function reserve(Request $request, string $assetId): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->resolveModel($assetId);
        abort_if(! $model, 404);

        $request->validate([
            'viewing_date' => ['required', 'date', 'after:today'],
            'viewing_time' => ['required', 'string', 'max:20'],
        ]);

        $service = app(AssetReservationService::class);
        $reservation = $service->activeForCustomer($customer, $model);
        abort_unless($reservation && $service->canScheduleViewing($reservation), 422);

        $service->scheduleViewing($reservation, $request->input('viewing_date'), $request->input('viewing_time'));

        return redirect()
            ->route('site.borrower.marketplace.reserve', $assetId)
            ->with('status', __('borrower.marketplace.viewing_scheduled_status'));
    }

    public function reserveFlow(string $assetId, AssetReservationService $reservations, ApplicationRequirementsService $requirements, PaymentAccountService $accounts): View|RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $asset = $this->findAsset($assetId, $customer);
        abort_if(! $asset, 404);

        $model = $this->resolveModel($assetId);
        abort_if(! $model, 404);

        $reservation = $reservations->activeForCustomer($customer, $model);
        if (! $reservation) {
            return redirect()->route('site.borrower.marketplace.show', $assetId)
                ->with('warning', __('borrower.marketplace.start_application_first'));
        }

        $reservation->load(['loanApplication.loan', 'loanApplication.postApprovalFees']);

        $steps = $reservations->steps($reservation);
        $applyRequirements = $requirements->checklist($customer);
        $feeBreakdown = app(AssetMarketplaceFeeService::class)->breakdown($customer, $model);
        $paymentGatewayDummy = payment_gateway_is_dummy();
        $paymentService = app(AssetReservationPaymentService::class);
        $reservationRef = $paymentService->paymentReference($reservation, AssetReservationPaymentService::STEP_RESERVATION_FEE);
        $depositRef = $paymentService->paymentReference($reservation, AssetReservationPaymentService::STEP_DEPOSIT);
        $bankAccounts = $accounts->bankAccountsForDisplay('asset_reservation_fee', $reservationRef);
        $mobileResolved = $accounts->resolve('asset_reservation_fee', 'mobile_money');
        $mobileDetails = $accounts->mobileMoneyDetails($mobileResolved['mobile_money_account'], $reservationRef);
        $depositBankAccounts = $accounts->bankAccountsForDisplay('asset_deposit', $depositRef);
        $depositMobileResolved = $accounts->resolve('asset_deposit', 'mobile_money');
        $depositMobileDetails = $accounts->mobileMoneyDetails($depositMobileResolved['mobile_money_account'], $depositRef);
        $paymentService = app(AssetReservationPaymentService::class);
        $reservationFeeQuote = $paymentService->quote($customer, $reservation, AssetReservationPaymentService::STEP_RESERVATION_FEE);
        $depositQuote = $paymentService->quote($customer, $reservation, AssetReservationPaymentService::STEP_DEPOSIT);
        $referralWallet = app(\App\Services\ReferralService::class)->wallet($customer);

        return view('site.borrower.marketplace.reserve', compact(
            'asset',
            'reservation',
            'steps',
            'applyRequirements',
            'feeBreakdown',
            'paymentGatewayDummy',
            'reservationRef',
            'bankAccounts',
            'mobileDetails',
            'depositBankAccounts',
            'depositMobileDetails',
            'depositRef',
            'reservationFeeQuote',
            'depositQuote',
            'referralWallet',
        ));
    }

    public function payReservation(Request $request, string $assetId, AssetReservationService $reservations, AssetReservationPaymentService $payments): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->resolveModel($assetId);
        abort_if(! $model, 404);

        $reservation = $reservations->activeForCustomer($customer, $model);
        abort_unless($reservation, 404);

        $data = $request->validate([
            'step'           => ['required', 'in:reservation_fee,deposit'],
            'payment_method' => ['required', 'in:bank_transfer,mobile_money'],
            'mobile_number'  => [payment_gateway_is_dummy() ? 'nullable' : 'required_if:payment_method,mobile_money', 'nullable', 'string', 'max:20'],
            'payment_date'   => ['nullable', 'date'],
            'proof'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'use_wallet'     => ['nullable', 'boolean'],
            'promo_code'     => ['nullable', 'string', 'max:40'],
        ]);

        if ($data['payment_method'] === 'mobile_money' && ! empty($data['mobile_number'])) {
            if (! CustomerPaymentService::validateMobileNumber($data['mobile_number'])) {
                return back()->withInput()->withErrors([
                    'mobile_number' => 'Enter your number with country code, without a leading zero (e.g. 255712345678).',
                ]);
            }
        }

        try {
            $payment = $payments->submit($customer, $reservation, $data['step'], [
                'payment_method' => $data['payment_method'],
                'mobile_number'  => $data['mobile_number'] ?? null,
                'payment_date'   => $data['payment_date'] ?? null,
                'proof'          => $request->file('proof'),
                'reference'      => $payments->paymentReference($reservation, $data['step']),
                'use_wallet'     => $request->boolean('use_wallet'),
                'promo_code'     => $data['promo_code'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('borrower.marketplace.payment_failed'));
        }

        $this->auditBorrower('marketplace.reservation_payment', $reservation, [
            'step'      => $data['step'],
            'reference' => $payment->reference,
            'amount'    => $payment->amount,
        ]);

        $message = $data['payment_method'] === 'bank_transfer' && ! $payment->isVerified()
            ? __('borrower.marketplace.payment_bank_pending', ['ref' => $payment->reference])
            : ($data['step'] === 'deposit'
                ? __('borrower.marketplace.deposit_recorded')
                : __('borrower.marketplace.application_fee_recorded'));

        if ($payment->isVerified()) {
            return back()->with('status', $message);
        }

        return redirect()->route('site.borrower.payments.show', $payment)->with('status', $message);
    }

    public function advanceReservation(Request $request, string $assetId, AssetReservationService $reservations): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        $model = $this->resolveModel($assetId);
        abort_if(! $model, 404);

        $reservation = $reservations->activeForCustomer($customer, $model);
        abort_unless($reservation, 404);

        $action = $request->validate([
            'action' => ['required', 'in:skip_viewing,complete_viewing,confirm_interest'],
        ])['action'];

        $feeAlreadyPaid = $reservation->reservation_fee_status === 'paid';
        $reservations->advance($reservation, $action);

        $message = match ($action) {
            'skip_viewing'     => $feeAlreadyPaid
                ? __('borrower.marketplace.viewing_skipped_after_fee')
                : __('borrower.marketplace.viewing_skipped'),
            'complete_viewing' => __('borrower.marketplace.viewing_completed'),
            'confirm_interest' => __('borrower.marketplace.interest_confirmed'),
            default            => __('borrower.marketplace.progress_updated'),
        };

        return back()->with('status', $message);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $customer = auth()->user()?->customer;
        abort_unless($customer, 403);

        return $this->persistAssetRequest($request, $customer);
    }

    public function storePublicRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_name'              => ['required', 'string', 'max:150'],
            'description'             => ['nullable', 'string', 'max:2000'],
            'budget'                  => ['nullable', 'numeric', 'min:0'],
            'preferred_tenure_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $request->session()->put('pending_asset_request', $data);
        $request->session()->put('login_redirect', route('site.borrower.marketplace', ['request' => 1]));

        return redirect()
            ->route('site.register.borrower')
            ->with('status', __('borrower.marketplace.request_signup_hint'));
    }

    private function persistAssetRequest(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'asset_name'              => ['required', 'string', 'max:150'],
            'description'             => ['nullable', 'string', 'max:2000'],
            'budget'                  => ['nullable', 'numeric', 'min:0'],
            'preferred_tenure_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'photo'                   => ['nullable', 'image', 'max:5120'],
            'photos'                  => ['nullable', 'array'],
            'photos.*'                => ['image', 'max:5120'],
        ]);

        $path = $request->hasFile('photo')
            ? $request->file('photo')->store("customer/{$customer->id}/asset-requests", 'public')
            : null;

        $additional = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $additional[] = $file->store("customer/{$customer->id}/asset-requests", 'public');
            }
        }

        AssetRequest::create([
            'customer_id'             => $customer->id,
            'asset_name'              => $data['asset_name'],
            'description'             => $data['description'] ?? null,
            'budget'                  => $data['budget'] ?? null,
            'preferred_tenure_months' => $data['preferred_tenure_months'] ?? null,
            'photo_path'              => $path,
            'additional_photos'       => $additional ?: null,
            'status'                  => 'sourcing',
        ]);

        $request->session()->forget('pending_asset_request');

        return redirect()
            ->route('site.borrower.marketplace')
            ->with('status', 'Asset sourcing request submitted. Our team will work with suppliers to find a match.');
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function relatedAssets(array $asset, int $limit = 4): \Illuminate\Support\Collection
    {
        $currentId = (string) ($asset['id'] ?? '');
        $category = $asset['category'] ?? null;

        $sameCategory = $this->loadAssets($category, [])
            ->filter(fn (array $row) => (string) ($row['id'] ?? '') !== $currentId)
            ->values();

        if ($sameCategory->count() >= 2) {
            return $sameCategory->take($limit);
        }

        $others = $this->loadAssets(null, [])
            ->filter(fn (array $row) => (string) ($row['id'] ?? '') !== $currentId)
            ->reject(fn (array $row) => $sameCategory->contains(fn (array $a) => ($a['id'] ?? null) === ($row['id'] ?? null)));

        return $sameCategory->merge($others)->unique('id')->take($limit)->values();
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function loadAssets(?string $category, array $filters = [])
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('marketplace_assets') && MarketplaceAsset::query()->exists()) {
            $query = MarketplaceAsset::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('availability_status')->orWhere('availability_status', 'available'))
                ->when($category, fn ($q) => $q->where('category', $category))
                ->when(filled($filters['q'] ?? null), function ($q) use ($filters): void {
                    $term = '%'.$filters['q'].'%';
                    $q->where(function ($inner) use ($term): void {
                        $inner->where('title', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('supplier_name', 'like', $term);
                    });
                })
                ->when(filled($filters['brand'] ?? null), function ($q) use ($filters): void {
                    $term = '%'.$filters['brand'].'%';
                    $q->where(function ($inner) use ($term): void {
                        $inner->where('title', 'like', $term)
                            ->orWhere('description', 'like', $term);
                    });
                })
                ->when(filled($filters['min_price'] ?? null), fn ($q) => $q->where('asset_value', '>=', \App\Support\MoneyFormat::toNumber($filters['min_price'])))
                ->when(filled($filters['max_price'] ?? null), fn ($q) => $q->where('asset_value', '<=', \App\Support\MoneyFormat::toNumber($filters['max_price'])))
                ->when(filled($filters['tenure'] ?? null), fn ($q) => $q->where('max_tenure_months', '<=', (int) $filters['tenure']));

            $sort = $filters['sort'] ?? 'title';
            match ($sort) {
                'price_asc'  => $query->orderBy('asset_value'),
                'price_desc' => $query->orderByDesc('asset_value'),
                'deposit_asc'=> $query->orderBy('customer_deposit'),
                default      => $query->orderBy('title'),
            };

            return $query->with('vendor')->get()
                ->map(fn (MarketplaceAsset $a) => $this->normalizeAsset($a))
                ->values();
        }

        return collect(config('asset_marketplace.assets', []))
            ->when($category, fn ($c) => $c->where('category', $category))
            ->when(filled($filters['q'] ?? null), fn ($c) => $c->filter(
                fn (array $asset) => str_contains(strtolower($asset['title'] ?? ''), strtolower($filters['q']))
                    || str_contains(strtolower($asset['description'] ?? ''), strtolower($filters['q']))
            ))
            ->when(filled($filters['brand'] ?? null), fn ($c) => $c->filter(
                fn (array $asset) => str_contains(strtolower($asset['title'] ?? ''), strtolower($filters['brand']))
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
            ->where(fn ($q) => $q->whereNull('availability_status')->orWhere('availability_status', 'available'))
            ->where(function ($q) use ($assetId): void {
                $q->where('slug', $assetId);
                if (is_numeric($assetId)) {
                    $q->orWhere('id', (int) $assetId);
                }
            })
            ->first();
    }

    private function resolveModel(string $assetId): ?MarketplaceAsset
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('marketplace_assets')) {
            return null;
        }

        $model = app(\App\Services\MarketplaceAssetService::class)->resolveOrMaterialize($assetId);
        if ($model) {
            return $model;
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

    private function findAsset(string $assetId, ?\App\Models\Customer $customer = null): ?array
    {
        $model = null;

        if ($customer) {
            $model = $this->resolveModel($assetId);
            if ($model && ! $model->isAvailable()) {
                $hasReservation = app(AssetReservationService::class)->activeForCustomer($customer, $model);
                if (! $hasReservation) {
                    $model = null;
                }
            }
        } else {
            $model = $this->findModel($assetId);
        }

        if ($model) {
            return $this->normalizeAsset($model);
        }

        return collect(config('asset_marketplace.assets', []))->firstWhere('id', $assetId);
    }

    /** @return array<string, mixed> */
    private function normalizeAsset(MarketplaceAsset $asset): array
    {
        $lending = app(\App\Services\AssetLendingService::class);
        $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
        $assetValue = (float) ($asset->asset_value ?: ($deposit * 1.4));
        $remainingLoan = max(0, round($assetValue - $deposit, 2));
        $supplierDeposit = (float) $asset->supplier_deposit;
        $vendor = $asset->relationLoaded('vendor') ? $asset->vendor : $asset->vendor()->first();

        return [
            'id'                     => $asset->slug ?: (string) $asset->id,
            'category'               => $asset->category,
            'title'                  => $asset->title,
            'vendor'                 => $vendor?->name ?: $asset->supplier_name,
            'supplier'               => $vendor?->name ?: $asset->supplier_name,
            'supplier_region'        => $vendor?->coverageLabel(),
            'description'            => $asset->description,
            'asset_value'            => $assetValue,
            'deposit'                => $deposit,
            'remaining_loan'         => $remainingLoan,
            'supplier_deposit'       => $supplierDeposit,
            'deposit_markup_percent' => (float) ($asset->deposit_markup_percent ?? 0),
            'deposit_markup_amount'  => $lending->depositMarkupAmount($asset),
            'weekly_installment'     => (float) $asset->weekly_installment,
            'max_tenure_months'      => effective_marketplace_asset_max_tenure($asset),
            'waiting_period_days'    => $asset->waiting_period_days,
            'photos'                 => $asset->photos ?? [],
        ];
    }

}
