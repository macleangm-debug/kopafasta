<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerController extends ResourceController
{
    protected string $model = Customer::class;
    protected string $routePrefix = 'admin.customers';
    protected string $viewFolder = 'customers';
    protected string $singular = 'customer';

    protected function rules(?Model $model = null): array
    {
        $id = $model?->id;

        return [
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'email'            => ['nullable', 'email', 'max:150'],
            'phone'            => ['required', 'string', 'max:30'],
            'type'             => ['required', 'in:individual,business'],
            'status'           => ['required', 'in:active,inactive,suspended'],
            'customer_number'  => ['nullable', 'string', 'max:50'],
            'national_id'      => ['nullable', 'string', 'max:50'],
            'date_of_birth'    => ['nullable', 'date'],
            'address'          => ['nullable', 'string', 'max:500'],
            'employment_type'  => ['nullable', 'string', 'max:50'],
            'business_name'    => ['nullable', 'string', 'max:150'],
            'monthly_income'   => ['nullable', 'numeric', 'min:0'],
            'branch_id'        => ['nullable', 'exists:branches,id'],
        ];
    }

    protected function formData(): array
    {
        return [
            'branches' => \App\Models\Branch::orderBy('name')->pluck('name', 'id'),
            'types'    => ['individual' => 'Individual', 'business' => 'Business'],
            'statuses' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['customer_number'])) {
            $data['customer_number'] = 'CU-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        }
        return $data;
    }
}
