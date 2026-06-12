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
    protected string $routePrefix = 'admin.vendors';
    protected string $viewFolder = 'vendors';
    protected string $singular = 'vendor';

    protected function rules(?Model $model = null): array
    {
        return [
            'vendor_number'                  => ['nullable', 'string', 'max:50'],
            'name'                           => ['required', 'string', 'max:150'],
            'category'                       => ['required', 'in:gps_installer,insurance,valuer,towing,yard,auctioneer,supplier,affiliate'],
            'roles'                          => ['nullable', 'array'],
            'roles.*'                        => ['string', 'in:gps_installer,insurance,valuer,towing,yard,auctioneer,supplier,affiliate,capital'],
            'phone'                          => ['nullable', 'string', 'max:30'],
            'email'                          => ['nullable', 'email', 'max:150'],
            'address'                        => ['nullable', 'string', 'max:500'],
            'status'                         => ['required', 'in:active,inactive,suspended'],
            'partner_cost'                   => ['nullable', 'numeric', 'min:0'],
            'markup_percent'                 => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_markup_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'affiliate_code'                 => ['nullable', 'string', 'max:32'],
            'registration_discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'application_discount_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'affiliate_commission_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
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
            ],
            'roleOptions' => app(\App\Services\PartnerService::class)->roleOptions(),
            'defaultCategory' => request()->query('category'),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $record = Vendor::create($data);

        if ($record->isAffiliate() && blank($record->affiliate_code)) {
            app(AffiliateService::class)->ensureCode($record);
            $record->refresh();
        }

        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['vendor_number'])) {
            $data['vendor_number'] = 'VND-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
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

        return $data;
    }

    public function show($id)
    {
        $record = Vendor::findOrFail($id);
        $affiliateStats = $record->isAffiliate()
            ? app(AffiliateService::class)->stats($record)
            : null;

        return view("admin.{$this->viewFolder}.show", array_merge(
            ['record' => $record, 'affiliateStats' => $affiliateStats],
            $this->formData($record),
        ));
    }
}
