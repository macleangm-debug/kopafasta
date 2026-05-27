<?php

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
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
            'vendor_number' => ['nullable', 'string', 'max:50'],
            'name'          => ['required', 'string', 'max:150'],
            'category'      => ['required', 'in:gps_installer,insurance,valuer,towing,yard,auctioneer'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:150'],
            'address'       => ['nullable', 'string', 'max:500'],
            'status'        => ['required', 'in:active,inactive,suspended'],
        ];
    }

    protected function formData(): array
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
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['vendor_number'])) {
            $data['vendor_number'] = 'VND-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        }
        return $data;
    }
}
