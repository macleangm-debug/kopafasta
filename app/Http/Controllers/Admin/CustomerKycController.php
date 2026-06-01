<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerKycController extends ResourceController
{
    protected string $model = CustomerKyc::class;
    protected string $routePrefix = 'admin.customer-kycs';
    protected string $viewFolder = 'customer-kycs';
    protected string $singular = 'KYC record';

    protected function rules(?Model $model = null): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'status'      => ['required', 'in:pending,in_review,approved,rejected'],
            'verified_by' => ['nullable', 'exists:users,id'],
            'verified_at' => ['nullable', 'date'],
            'payload'     => ['nullable', 'string'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'customers'  => Customer::orderBy('first_name')->limit(500)->get()
                ->mapWithKeys(fn($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
            'reviewers'  => User::whereIn('role', ['admin', 'manager', 'officer'])->orderBy('name')->pluck('name', 'id'),
            'statuses'   => ['pending' => 'Pending', 'in_review' => 'In review', 'approved' => 'Approved', 'rejected' => 'Rejected'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! empty($data['payload']) && is_string($data['payload'])) {
            $decoded = json_decode($data['payload'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['payload'] = $decoded;
            }
        }
        return $data;
    }
}
