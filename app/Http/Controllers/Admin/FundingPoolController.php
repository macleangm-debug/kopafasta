<?php

namespace App\Http\Controllers\Admin;

use App\Models\FundingPool;
use App\Models\Lender;
use Illuminate\Database\Eloquent\Model;

class FundingPoolController extends ResourceController
{
    protected string $model = FundingPool::class;
    protected string $routePrefix = 'admin.funding-pools';
    protected string $viewFolder = 'funding-pools';
    protected string $singular = 'funding pool';

    protected function rules(?Model $model = null): array
    {
        return [
            'lender_id'        => ['required', 'exists:lenders,id'],
            'name'             => ['required', 'string', 'max:150'],
            'currency'         => ['required', 'string', 'size:3'],
            'amount_committed' => ['required', 'numeric', 'min:0'],
            'amount_deployed'  => ['nullable', 'numeric', 'min:0'],
            'expected_yield'   => ['nullable', 'numeric', 'min:0', 'max:1'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'           => ['required', 'in:draft,active,closed,exhausted'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'lenders'  => Lender::orderBy('name')->pluck('name', 'id'),
            'statuses' => ['draft' => 'Draft', 'active' => 'Active', 'closed' => 'Closed', 'exhausted' => 'Exhausted'],
        ];
    }
}
