<?php

namespace App\Http\Controllers\Admin;

use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderInvestment;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LenderInvestmentController extends ResourceController
{
    protected string $model = LenderInvestment::class;
    protected string $routePrefix = 'admin.lender-investments';
    protected string $viewFolder = 'lender-investments';
    protected string $singular = 'investment';

    protected function rules(?Model $model = null): array
    {
        return [
            'lender_id'       => ['required', 'exists:lenders,id'],
            'funding_pool_id' => ['nullable', 'exists:funding_pools,id'],
            'loan_id'         => ['nullable', 'exists:loans,id'],
            'reference'       => ['nullable', 'string', 'max:80'],
            'principal'       => ['required', 'numeric', 'min:0'],
            'return_amount'   => ['nullable', 'numeric', 'min:0'],
            'return_rate'     => ['nullable', 'numeric', 'min:0', 'max:1'],
            'invested_at'     => ['nullable', 'date'],
            'matures_at'      => ['nullable', 'date'],
            'status'          => ['required', 'in:pending,active,matured,closed,defaulted'],
        ];
    }

    protected function formData(): array
    {
        return [
            'lenders' => Lender::orderBy('name')->pluck('name', 'id'),
            'pools'   => FundingPool::orderBy('name')->pluck('name', 'id'),
            'loans'   => Loan::orderByDesc('id')->limit(200)->pluck('loan_number', 'id'),
            'statuses'=> ['pending' => 'Pending', 'active' => 'Active', 'matured' => 'Matured', 'closed' => 'Closed', 'defaulted' => 'Defaulted'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['reference'])) {
            $data['reference'] = 'INV-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        }
        return $data;
    }
}
