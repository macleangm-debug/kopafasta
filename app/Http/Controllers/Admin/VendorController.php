<?php

namespace App\Http\Controllers\Admin;

use App\Models\PartnerApplicationDocument;
use App\Models\PartnerDocument;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\PartnerCodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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
            'vendor_number'                  => ['prohibited'],
            'name'                           => ['required', 'string', 'max:150'],
            'legal_name'                     => ['nullable', 'string', 'max:150'],
            'registration_number'            => ['nullable', 'string', 'max:80'],
            'tin'                            => ['nullable', 'string', 'max:40'],
            'category'                       => ['required', 'in:gps_installer,insurance,valuer,towing,yard,auctioneer,supplier,affiliate,call_center,debt_collector,legal_partner'],
            'applicant_category'             => ['nullable', 'in:individual,company'],
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
            'doc_brela'                      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_other'                      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function create()
    {
        if (request()->routeIs('admin.vendors.create')) {
            return redirect()->route('admin.partners.create', request()->query());
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
        $data = $this->normalizeApplicantCategory($data);
        $this->validateAffiliateCode($data, null);
        $this->validateRegions($data);
        $record = Vendor::create($data);
        $this->storeBusinessDocuments($request, $record);

        if ($record->isAffiliate() && blank($record->affiliate_code)) {
            app(AffiliateService::class)->ensureCode($record);
            $record->refresh();
        }

        if ($record->isAffiliate()) {
            app(\App\Services\AffiliateLifecycleService::class)->initializeNewAffiliate($record);
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
        $data = $this->normalizeApplicantCategory($data);
        $this->validateAffiliateCode($data, $vendor);
        $this->validateRegions($data);
        $vendor->update($data);
        $this->storeBusinessDocuments($request, $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', ucfirst($this->singular).' updated.');
    }

    private function storeBusinessDocuments(Request $request, Vendor $partner): void
    {
        $map = [
            'doc_brela' => 'brela',
            'doc_tin_certificate' => 'tin_certificate',
            'doc_business_licence' => 'business_licence',
            'doc_national_id_front' => 'national_id_front',
            'doc_national_id_back' => 'national_id_back',
            'doc_other' => 'other',
        ];

        foreach ($map as $input => $docType) {
            $file = $request->file($input);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('partners/'.$partner->id.'/compliance', 'public');

            PartnerDocument::create([
                'partner_id' => $partner->id,
                'label' => PartnerApplicationDocument::DOC_TYPES[$docType] ?? $docType,
                'doc_type' => $docType,
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
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
    private function normalizeApplicantCategory(array $data): array
    {
        $category = (string) ($data['category'] ?? '');
        $allowsPerson = in_array($category, ['affiliate', 'valuer'], true);
        $applicant = (string) ($data['applicant_category'] ?? 'company');

        if (! $allowsPerson || ! in_array($applicant, ['individual', 'company'], true)) {
            $data['applicant_category'] = 'company';
        } else {
            $data['applicant_category'] = $applicant;
        }

        return $data;
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
        unset(
            $data['vendor_number'],
            $data['doc_brela'],
            $data['doc_tin_certificate'],
            $data['doc_business_licence'],
            $data['doc_national_id_front'],
            $data['doc_national_id_back'],
            $data['doc_other'],
        );

        if (! $existing instanceof Vendor) {
            $data['vendor_number'] = app(PartnerCodeService::class)->generate((string) ($data['category'] ?? 'supplier'));
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
        $affiliateEvaluations = $record->isAffiliate()
            ? $record->affiliateEvaluations()->latest('evaluated_at')->limit(6)->get()
            : collect();
        $recoveryStats = $record->isRecoveryPartner()
            ? app(\App\Services\RecoveryPartnerService::class)->statsForVendor($record)
            : null;

        return view("admin.{$this->viewFolder}.show", array_merge(
            ['record' => $record, 'affiliateStats' => $affiliateStats, 'affiliateEvaluations' => $affiliateEvaluations, 'recoveryStats' => $recoveryStats],
            $this->formData($record),
        ));
    }

    public function updateAffiliateLifecycle(Request $request, Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', app(\App\Services\AffiliateLifecycleService::class)->statuses())],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        app(\App\Services\AffiliateLifecycleService::class)->transition(
            $vendor,
            $data['status'],
            $data['reason'] ?? null,
            $request->user(),
        );

        $this->auditAdmin('vendor.affiliate_lifecycle.updated', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Affiliate lifecycle status updated.');
    }

    public function scanAffiliateFraud(Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        $result = app(\App\Services\AffiliateFraudDetectionService::class)->scanAndPersist($vendor);

        $this->auditAdmin('vendor.affiliate_fraud.scanned', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Fraud scan complete. Risk flag: '.$result['risk_flag'].'.');
    }

    public function updateAffiliateRiskFlag(Request $request, Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        $data = $request->validate([
            'risk_flag' => ['required', 'in:'.implode(',', app(\App\Services\AffiliateFraudDetectionService::class)->flags())],
        ]);

        $vendor->update(['affiliate_risk_flag' => $data['risk_flag']]);

        $this->auditAdmin('vendor.affiliate_risk_flag.updated', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Affiliate risk flag updated.');
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

        app(\App\Services\AffiliateLifecycleService::class)->syncFromKyc($vendor->refresh());

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
