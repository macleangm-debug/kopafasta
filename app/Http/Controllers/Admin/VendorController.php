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
            'contact_person_name'            => ['nullable', 'string', 'max:150'],
            'national_id'                    => ['nullable', 'string', 'max:30'],
            'address_region'                 => ['nullable', 'string', 'max:80'],
            'address_district'               => ['nullable', 'string', 'max:80'],
            'address_ward'                   => ['nullable', 'string', 'max:80'],
            'address_street'                 => ['nullable', 'string', 'max:160'],
            'payout_type'                    => ['nullable', 'in:mobile_money,bank'],
            'payout_account_name'            => ['nullable', 'string', 'max:120'],
            'payout_mobile_provider'         => ['nullable', 'string', 'max:40'],
            'payout_mobile_number'           => ['nullable', 'string', 'max:30'],
            'payout_bank_name'               => ['nullable', 'string', 'max:120'],
            'payout_account_number'          => ['nullable', 'string', 'max:60'],
            'status'                         => ['required', 'in:active,inactive,suspended'],
            'partner_cost'                   => ['nullable', 'numeric', 'min:0'],
            'service_rate_percent'           => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'doc_brela_pages'                => ['nullable', 'array', 'max:12'],
            'doc_brela_pages.*'              => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate_pages'      => ['nullable', 'array', 'max:12'],
            'doc_tin_certificate_pages.*'    => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence_pages'     => ['nullable', 'array', 'max:12'],
            'doc_business_licence_pages.*'   => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front_pages'    => ['nullable', 'array', 'max:12'],
            'doc_national_id_front_pages.*'  => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back_pages'     => ['nullable', 'array', 'max:12'],
            'doc_national_id_back_pages.*'   => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_other'                      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_other_pages'                => ['nullable', 'array', 'max:12'],
            'doc_other_pages.*'              => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function create()
    {
        if (request()->routeIs('admin.vendors.create')) {
            return redirect()->route('admin.partners.create', request()->query());
        }

        abort_unless(request()->user()?->can('create', Vendor::class), 403, 'Only the Partners Management team or an admin can add partners.');

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
            'defaultRegion' => request()->query('region'),
            'regionOptions' => partner_region_options(),
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', Vendor::class);

        $validated = $request->validate(array_merge($this->rules(), [
            'activation_mode' => ['nullable', 'in:invite,activate_now,draft'],
            'notify_partner' => ['nullable', 'boolean'],
            'activation_pin' => ['nullable', 'digits:4'],
        ]));

        $activationMode = (string) ($validated['activation_mode'] ?? 'invite');
        $notifyPartner = $request->boolean('notify_partner');
        $activationPin = $validated['activation_pin'] ?? null;
        unset($validated['activation_mode'], $validated['notify_partner'], $validated['activation_pin']);

        if ($activationMode === 'activate_now') {
            if (blank($activationPin)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'activation_pin' => 'A 4-digit PIN is required to activate the account now.',
                ]);
            }
            if (blank($validated['phone'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'phone' => 'Phone is required to activate the partner portal account.',
                ]);
            }
        }

        if ($activationMode === 'activate_now') {
            $validated['status'] = 'inactive';
        } elseif ($activationMode === 'draft' || $activationMode === 'invite') {
            $validated['status'] = 'inactive';
        }

        $data = $this->transform($validated);
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

        $activation = app(\App\Services\PartnerActivationService::class);
        $statusMessage = ucfirst($this->singular).' created.';

        if ($activationMode === 'activate_now' && $activation->requiresActivation($record)) {
            $token = $activation->prepareActivation($record);
            $activation->activate($record->fresh(), $token, ['pin' => $activationPin]);
            $statusMessage = ucfirst($this->singular).' created and portal activated.';
        } elseif ($activationMode === 'invite' && $activation->requiresActivation($record)) {
            $activation->sendActivationInvite($record, $request->user('admin'), notify: $notifyPartner);
            $statusMessage = ucfirst($this->singular).' created. Activation invite prepared'
                .($notifyPartner ? ' and notification queued.' : '. Share the partner code to activate.');
        } elseif ($activationMode === 'draft') {
            $statusMessage = ucfirst($this->singular).' saved as inactive draft.';
        } elseif ($activation->requiresActivation($record)) {
            $activation->sendActivationInvite($record, $request->user('admin'), notify: false);
        }

        $this->auditAdminCreated($record);

        $placed = $this->placeWaitingValuerJobs($record->fresh(), $request->user('admin'));
        $statusMessage .= $this->valuerOpsNextSteps($record->fresh(), $placed, onCreate: true);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', trim($statusMessage));
    }

    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorize('update', $vendor);
        $data = $this->transform($request->validate($this->rules($vendor)), $vendor);
        $data = $this->normalizeApplicantCategory($data);
        $this->validateAffiliateCode($data, $vendor);
        $this->validateRegions($data);
        $vendor->update($data);
        $this->storeBusinessDocuments($request, $vendor);

        $fresh = $vendor->fresh();
        $placed = $this->placeWaitingValuerJobs($fresh, $request->user('admin'));
        $status = ucfirst($this->singular).' updated.';
        $status .= $this->valuerOpsNextSteps($fresh, $placed, onCreate: false);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', trim($status));
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

        $merger = app(\App\Services\DocumentPageMerger::class);

        foreach ($map as $input => $docType) {
            $pages = array_values(array_filter($request->file($input.'_pages', []) ?? []));
            $single = $request->file($input);
            if ($single instanceof UploadedFile) {
                array_unshift($pages, $single);
            }
            if ($pages === []) {
                continue;
            }

            $path = $merger->mergeTo($pages, 'partners/'.$partner->id.'/compliance', $docType);
            $stored = \Illuminate\Support\Facades\Storage::disk('public')->exists($path)
                ? \Illuminate\Support\Facades\Storage::disk('public')->size($path)
                : 0;

            PartnerDocument::create([
                'partner_id' => $partner->id,
                'label' => PartnerApplicationDocument::DOC_TYPES[$docType] ?? $docType,
                'doc_type' => $docType,
                'file_path' => $path,
                'mime' => str_ends_with(strtolower($path), '.pdf') ? 'application/pdf' : ($pages[0]->getClientMimeType() ?? 'application/pdf'),
                'size_bytes' => $stored,
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
        $contactPerson = trim((string) ($data['contact_person_name'] ?? ''));
        $nationalId = trim((string) ($data['national_id'] ?? ''));
        $residence = array_filter([
            'region' => $data['address_region'] ?? null,
            'district' => $data['address_district'] ?? null,
            'ward' => $data['address_ward'] ?? null,
            'street' => $data['address_street'] ?? null,
        ]);
        $payoutType = $data['payout_type'] ?? null;
        $payout = $payoutType ? array_filter([
            'type' => $payoutType,
            'account_name' => $data['payout_account_name'] ?? null,
            'mobile_provider' => $data['payout_mobile_provider'] ?? null,
            'mobile_number' => $data['payout_mobile_number'] ?? null,
            'bank_name' => $data['payout_bank_name'] ?? null,
            'account_number' => $data['payout_account_number'] ?? null,
        ]) : [];

        unset(
            $data['vendor_number'],
            $data['doc_brela'],
            $data['doc_tin_certificate'],
            $data['doc_business_licence'],
            $data['doc_national_id_front'],
            $data['doc_national_id_back'],
            $data['doc_other'],
            $data['contact_person_name'],
            $data['national_id'],
            $data['address_region'],
            $data['address_district'],
            $data['address_ward'],
            $data['address_street'],
            $data['payout_type'],
            $data['payout_account_name'],
            $data['payout_mobile_provider'],
            $data['payout_mobile_number'],
            $data['payout_bank_name'],
            $data['payout_account_number'],
        );

        if (! $existing instanceof Vendor) {
            $data['vendor_number'] = app(PartnerCodeService::class)->generate((string) ($data['category'] ?? 'supplier'));
        }

        if (($data['category'] ?? '') === 'affiliate' && empty($data['affiliate_code']) && $existing instanceof Vendor) {
            $data['affiliate_code'] = app(AffiliateService::class)->ensureCode($existing);
        }

        $roles = array_values(array_filter($data['roles'] ?? []));
        $category = (string) ($data['category'] ?? ($existing?->category ?? ''));

        // Category from the form is authoritative. Roles may add capabilities
        // (e.g. debt_collector + auctioneer) but must not demote the partner type.
        if ($category !== '') {
            if ($roles === []) {
                $roles = [$category];
            } else {
                $roles = array_values(array_unique(array_merge([$category], $roles)));
            }
            $data['category'] = $category;
            $data['roles'] = $roles;
        } elseif ($roles !== []) {
            $data['roles'] = $roles;
            $data['category'] = $roles[0];
        }

        if (array_key_exists('regions', $data)) {
            $data['regions'] = array_values(array_filter($data['regions'] ?? []));
        }

        $coverage = (string) ($data['coverage_type'] ?? 'regions');
        $data['coverage_type'] = in_array($coverage, ['regions', 'nationwide'], true) ? $coverage : 'regions';
        if ($data['coverage_type'] === 'nationwide') {
            $data['regions'] = [];
        }

        $meta = is_array($existing?->metadata ?? null) ? $existing->metadata : [];
        if ($contactPerson !== '') {
            $meta['contact_person'] = ['name' => $contactPerson];
        }
        if ($nationalId !== '') {
            $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
            $identity['national_id'] = $nationalId;
            $meta['identity'] = $identity;
        }
        if ($residence !== []) {
            $meta['residence'] = $residence;
            $data['address'] = collect([
                $residence['street'] ?? null,
                $residence['ward'] ?? null,
                $residence['district'] ?? null,
                $residence['region'] ?? null,
            ])->filter()->implode(', ');
        }
        if ($payout !== []) {
            $meta['payout_account'] = $payout;
        }
        if ($meta !== []) {
            $data['metadata'] = $meta;
        }

        return app(\App\Services\PartnerDefaultsService::class)
            ->applyPartnerPricingMeta($data, $existing instanceof Vendor ? $existing : null);
    }

    public function show($id)
    {
        if (request()->routeIs('admin.vendors.show')) {
            return redirect()->route('admin.partners.show', $id);
        }

        $record = Vendor::findOrFail($id);
        $deletion = app(\App\Services\PartnerDeletionService::class);
        $openTasks = $deletion->openTasks($record);
        $openValuations = $deletion->openValuationAssignments($record);
        $recentTasks = $record->tasks()->orderByDesc('id')->limit(8)->get();
        $payouts = $record->payments()->latest()->limit(8)->get();
        $affiliateStats = $record->isAffiliate()
            ? app(AffiliateService::class)->stats($record)
            : null;
        $affiliateEvaluations = $record->isAffiliate()
            ? $record->affiliateEvaluations()->latest('evaluated_at')->limit(6)->get()
            : collect();
        $recoveryStats = $record->isRecoveryPartner()
            ? app(\App\Services\RecoveryPartnerService::class)->statsForVendor($record)
            : null;
        $membership = $record->isAffiliate()
            ? app(\App\Services\AffiliateMembershipService::class)->summary($record)
            : null;

        return view("admin.{$this->viewFolder}.show", array_merge(
            [
                'record' => $record,
                'affiliateStats' => $affiliateStats,
                'affiliateEvaluations' => $affiliateEvaluations,
                'recoveryStats' => $recoveryStats,
                'membership' => $membership,
                'openTasks' => $openTasks,
                'openValuations' => $openValuations,
                'recentTasks' => $recentTasks,
                'payouts' => $payouts,
            ],
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

    public function approveMembershipPayment(Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        app(\App\Services\AffiliateMembershipService::class)->approvePendingPayment($vendor);

        $this->auditAdmin('vendor.membership.approved', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Affiliate membership payment approved. Membership is now active.');
    }

    public function rejectMembershipPayment(Vendor $vendor): \Illuminate\Http\RedirectResponse
    {
        abort_unless($vendor->isAffiliate(), 404);

        app(\App\Services\AffiliateMembershipService::class)->rejectPendingPayment($vendor);

        $this->auditAdmin('vendor.membership.rejected', $vendor);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Affiliate membership payment rejected.');
    }

    public function edit($id)
    {
        if (request()->routeIs('admin.vendors.edit')) {
            return redirect()->route('admin.partners.edit', $id);
        }

        $record = Vendor::findOrFail($id);

        abort_unless(request()->user()?->can('update', $record), 403, 'Only the Partners Management team or an admin can edit partners.');

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

    public function haltOpenWork($id)
    {
        $record = Vendor::findOrFail($id);
        $this->authorize('delete', $record);

        $result = app(\App\Services\PartnerDeletionService::class)
            ->haltOpenWork($record, auth('admin')->user());

        $this->auditAdmin('vendor.open_work.halted', $record, [
            'halted_tasks' => $result['halted_tasks'],
            'halted_assignments' => $result['halted_assignments'],
            'reassigned' => $result['reassigned'],
        ]);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', $result['message']);
    }

    public function resetPin(Request $request, Vendor $vendor)
    {
        $this->authorize('update', $vendor);
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        app(\App\Services\PartnerActivationService::class)->setPortalPin($vendor, $data['pin']);

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Partner portal PIN updated. Share it with them out of band.');
    }

    public function reissueActivation(Request $request, Vendor $vendor)
    {
        $this->authorize('update', $vendor);
        $activation = app(\App\Services\PartnerActivationService::class);
        $plain = $activation->prepareActivation($vendor);
        $fresh = $vendor->fresh();
        $url = $activation->activationUrl($fresh, $plain);

        if ($request->boolean('notify_partner')) {
            $message = 'Activate your '.brand_name().' partner account: '.$url;
            if (filled($fresh->email)) {
                app(\App\Services\NotificationService::class)->sendEmail(
                    $fresh->email,
                    'Activate your partner account',
                    $message,
                );
            }
            if (filled($fresh->phone)) {
                app(\App\Services\NotificationService::class)->sendSms($fresh->phone, $message);
            }
        }

        return redirect()
            ->route("{$this->routePrefix}.show", $vendor)
            ->with('status', 'Activation re-issued. They can open the link and create a new PIN.')
            ->with('partner_activation_url', $url);
    }

    public function destroy($id)
    {
        $record = Vendor::findOrFail($id);
        $this->authorize('delete', $record);

        $service = app(\App\Services\PartnerDeletionService::class);

        try {
            $result = $service->hardDelete($record, auth('admin')->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = $e->validator->errors()->first()
                ?: 'This partner has history. Deactivate instead of deleting.';

            return redirect()
                ->back()
                ->with('error', $message);
        }

        $this->auditAdminDeleted($record);

        return redirect()
            ->route('admin.partners.all')
            ->with('status', $result['message'] ?? ucfirst($this->singular).' deleted.');
    }

    public function deactivate($id)
    {
        $record = Vendor::findOrFail($id);
        $this->authorize('delete', $record);

        $result = app(\App\Services\PartnerDeletionService::class)
            ->deactivate($record, auth('admin')->user());

        $this->auditAdmin('vendor.deactivated', $record->fresh() ?? $record);

        return redirect()
            ->route('admin.partners.all')
            ->with('status', $result['message'] ?? 'Partner deactivated.');
    }

    private function placeWaitingValuerJobs(Vendor $vendor, ?\App\Models\User $actor = null): int
    {
        if (! $vendor->isValuer()) {
            return 0;
        }

        try {
            return app(\App\Services\ValuationPartnerService::class)
                ->assignWaitingJobsCoveredBy($vendor, $actor);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function valuerOpsNextSteps(Vendor $vendor, int $placed, bool $onCreate = false): string
    {
        if (! $vendor->isValuer()) {
            return '';
        }

        if ($placed > 0) {
            return ' '.$placed.' waiting valuation file(s) assigned to this partner.';
        }

        if ($vendor->status !== 'active') {
            return ' Next: set coverage to Nationwide or the borrower region, then activate the portal PIN. Waiting files auto-match once this valuer is active. Leftover files: Assign valuer on the credit file.';
        }

        $cover = app(\App\Services\PartnerRegionCoverage::class)->label($vendor);
        if ($cover === 'No regions set') {
            return ' This valuer has no region coverage yet. Set Nationwide or the borrower region so waiting files can match.';
        }

        if (! $onCreate) {
            return '';
        }

        return ' Coverage is '.$cover.'. Waiting files that match auto-assign. If a credit file is still waiting, open Collateral → Assign valuer.';
    }
}
