<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use App\Services\AffiliateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorController extends ResourceController
{
    protected string $model = Vendor::class;
    protected string $routePrefix = 'admin.partners';
    protected string $viewFolder = 'partners';
    protected string $singular = 'partner';

    /** @var list<string> */
    private const REGION_REQUIRED_CATEGORIES = [
        'gps_installer',
        'insurance',
        'valuer',
        'supplier',
        'debt_collector',
        'towing',
        'yard',
        'auctioneer',
    ];

    protected function rules(?Model $model = null): array
    {
        return [
            'vendor_number'                  => ['nullable', 'string', 'max:50'],
            'name'                           => ['required', 'string', 'max:150'],
            'category'                       => ['required', 'in:gps_installer,insurance,valuer,towing,yard,auctioneer,supplier,affiliate,call_center,debt_collector,legal_partner'],
            'roles'                          => ['nullable', 'array'],
            'roles.*'                        => ['string', 'in:gps_installer,insurance,valuer,towing,yard,auctioneer,supplier,affiliate,capital,call_center,debt_collector,legal_partner'],
            'phone'                          => ['nullable', 'string', 'max:30'],
            'email'                          => ['nullable', 'email', 'max:150'],
            'address'                        => ['nullable', 'string', 'max:500'],
            'status'                         => ['required', 'in:active,inactive,suspended'],
            'partner_cost'                   => ['nullable', 'numeric', 'min:0'],
            'markup_percent'                 => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_markup_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'supplier_type'                  => ['nullable', 'in:managed_loan,upfront_settlement'],
            'affiliate_code'                 => ['nullable', 'string', 'max:32'],
            'recovery_fee_type'              => ['nullable', 'in:percentage,fixed'],
            'recovery_fixed_amount'          => ['nullable', 'numeric', 'min:0'],
            'registration_discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'application_discount_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'affiliate_commission_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recovery_commission_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recovery_markup_percent'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'regions'                        => ['nullable', 'array'],
            'regions.*'                      => ['string', 'max:100'],
            'coverage_type'                  => ['nullable', 'in:regions,nationwide'],
        ];
    }

    public function create()
    {
        if (request()->routeIs('admin.vendors.create')) {
            return redirect()->route('admin.partners.create', request()->query());
        }

        if (! request()->query('category')) {
            return view('admin.partners.choose-type', $this->formData());
        }

        return view("admin.{$this->viewFolder}.create", $this->formData());
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'statuses'   => ['active' => 'Active', 'inactive' => 'Inactive (Pending)', 'suspended' => 'Suspended'],
            'categories' => [
                'gps_installer' => 'GPS Installer',
                'insurance'     => 'Insurance Provider',
                'valuer'        => 'Valuer',
                'towing'        => 'Towing',
                'yard'          => 'Yard',
                'auctioneer'    => 'Auctioneer',
                'supplier'      => 'Asset Supplier',
                'affiliate'     => 'Affiliate Partner',
                'call_center'   => 'Call Center',
                'debt_collector'=> 'Debt Collector',
                'legal_partner' => 'Legal Partner',
            ],
            'roleOptions' => app(\App\Services\PartnerService::class)->roleOptions(),
            'defaultCategory' => request()->query('category'),
            'regionOptions' => partner_region_options(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $this->validateAffiliateCode($data, null);
        $this->validateRegions($data);
        $record = Vendor::create($data);

        if ($record->isAffiliate() && blank($record->affiliate_code)) {
            app(AffiliateService::class)->ensureCode($record);
            $record->refresh();
        }

        if (app(\App\Services\PartnerActivationService::class)->requiresActivation($record)) {
            app(\App\Services\PartnerActivationService::class)->sendActivationInvite($record);
        }

        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $data = $this->transform($request->validate($this->rules($vendor)), $vendor);
        $this->validateAffiliateCode($data, $vendor);
        $this->validateRegions($data);
        $vendor->update($data);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', ucfirst($this->singular).' updated.');
    }

    /** @param array<string, mixed> $data */
    private function validateRegions(array $data): void
    {
        $category = (string) ($data['category'] ?? '');
        $roles = array_values(array_filter($data['roles'] ?? [$category]));
        $requires = in_array($category, self::REGION_REQUIRED_CATEGORIES, true)
            || collect($roles)->intersect(self::REGION_REQUIRED_CATEGORIES)->isNotEmpty();

        if (! $requires) {
            return;
        }

        if (($data['coverage_type'] ?? 'regions') === 'nationwide') {
            return;
        }

        if (array_filter($data['regions'] ?? []) === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'regions' => 'Select at least one operating region for this partner type, or mark coverage as nationwide.',
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateAffiliateCode(array $data, ?Vendor $existing): void
    {
        if (($data['category'] ?? '') !== 'affiliate' && ! in_array('affiliate', $data['roles'] ?? [], true)) {
            return;
        }

        $code = strtoupper(trim((string) ($data['affiliate_code'] ?? '')));
        if ($code === '') {
            return;
        }

        if (! app(AffiliateService::class)->codeIsUnique($code, $existing?->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'affiliate_code' => 'This affiliate code is already in use.',
            ]);
        }
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['vendor_number'])) {
            $data['vendor_number'] = 'PTR-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        }

        if (($data['category'] ?? '') === 'affiliate' && empty($data['affiliate_code']) && $existing instanceof Vendor) {
            $data['affiliate_code'] = app(AffiliateService::class)->ensureCode($existing);
        }

        $roles = array_values(array_filter($data['roles'] ?? []));
        if ($roles !== []) {
            $data['roles'] = $roles;
            $data['category'] = $roles[0];
        } elseif (filled($data['category'] ?? null)) {
            $data['roles'] = [$data['category']];
        }

        if (array_key_exists('regions', $data)) {
            $data['regions'] = array_values(array_filter($data['regions'] ?? []));
        }

        $coverage = (string) ($data['coverage_type'] ?? 'regions');
        $data['coverage_type'] = in_array($coverage, ['regions', 'nationwide'], true) ? $coverage : 'regions';
        if ($data['coverage_type'] === 'nationwide') {
            $data['regions'] = [];
        }

        return $data;
    }

    public function show($id)
    {
        if (request()->routeIs('admin.vendors.show')) {
            return redirect()->route('admin.partners.show', $id);
        }

        $record = Vendor::findOrFail($id);
        $affiliateStats = $record->isAffiliate()
            ? app(AffiliateService::class)->stats($record)
            : null;
        $recoveryStats = $record->isRecoveryPartner()
            ? app(\App\Services\RecoveryPartnerService::class)->statsForVendor($record)
            : null;

        return view("admin.{$this->viewFolder}.show", array_merge(
            ['record' => $record, 'affiliateStats' => $affiliateStats, 'recoveryStats' => $recoveryStats],
            $this->formData($record),
        ));
    }

    public function edit($id)
    {
        if (request()->routeIs('admin.vendors.edit')) {
            return redirect()->route('admin.partners.edit', $id);
        }

        $record = Vendor::findOrFail($id);

        return view("admin.{$this->viewFolder}.edit", array_merge(
            ['record' => $record],
            $this->formData($record),
        ));
    }

    public function approveAffiliateKyc(Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        $vendor->update([
            'affiliate_kyc_status' => 'verified',
        ]);

        $this->auditAdmin('vendor.affiliate_kyc.approved', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Affiliate KYC approved. Public verification is now active.');
    }

    public function rejectAffiliateKyc(Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        $vendor->update([
            'affiliate_kyc_status' => 'rejected',
        ]);

        $this->auditAdmin('vendor.affiliate_kyc.rejected', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Affiliate KYC rejected. Partner can re-upload documents.');
    }

    public function destroy($id)
    {
        $record = Vendor::findOrFail($id);
        $this->auditAdminDeleted($record);
        $record->delete();

        return redirect()
            ->route('admin.partners.all')
            ->with('status', ucfirst($this->singular).' deleted.');
    }
}
