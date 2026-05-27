<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoanApplicationController extends ResourceController
{
    protected string $model = LoanApplication::class;
    protected string $routePrefix = 'admin.loan-applications';
    protected string $viewFolder = 'loan-applications';
    protected string $singular = 'application';

    protected function rules(?Model $model = null): array
    {
        return [
            'customer_id'              => ['required', 'exists:customers,id'],
            'loan_product_id'          => ['required', 'exists:loan_products,id'],
            'branch_id'                => ['nullable', 'exists:branches,id'],
            'application_number'       => ['nullable', 'string', 'max:50'],
            'requested_amount'         => ['required', 'numeric', 'min:0'],
            'requested_tenure_months'  => ['required', 'integer', 'min:1', 'max:120'],
            'recommended_amount'       => ['nullable', 'numeric', 'min:0'],
            'status'                   => ['required', 'in:draft,submitted,under_review,pre_approved,approved,rejected,withdrawn,disbursed'],
            'current_stage'            => ['nullable', 'string', 'max:80'],
            'purpose'                  => ['nullable', 'string', 'max:500'],
            'rejection_reason'         => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function formData(): array
    {
        return [
            'customers' => Customer::orderBy('first_name')->limit(500)->get()
                ->mapWithKeys(fn($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
            'products'  => LoanProduct::orderBy('name')->pluck('name', 'id'),
            'branches'  => Branch::orderBy('name')->pluck('name', 'id'),
            'statuses'  => [
                'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under review',
                'pre_approved' => 'Pre-approved', 'approved' => 'Approved', 'rejected' => 'Rejected',
                'withdrawn' => 'Withdrawn', 'disbursed' => 'Disbursed',
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['application_number'])) {
            $data['application_number'] = 'APP-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        }
        return $data;
    }
}
