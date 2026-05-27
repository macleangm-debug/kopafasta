<?php

namespace App\Http\Controllers\Admin;

use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Model;

class LoanProductController extends ResourceController
{
    protected string $model = LoanProduct::class;
    protected string $routePrefix = 'admin.loan-products';
    protected string $viewFolder = 'loan-products';
    protected string $singular = 'loan product';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'                => ['required', 'string', 'max:30'],
            'name'                => ['required', 'string', 'max:150'],
            'category'            => ['nullable', 'string', 'max:50'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'interest_rate'       => ['required', 'numeric', 'min:0', 'max:1'],
            'tenure_min_months'   => ['required', 'integer', 'min:1', 'max:120'],
            'tenure_max_months'   => ['required', 'integer', 'min:1', 'max:120'],
            'min_amount'          => ['required', 'numeric', 'min:0'],
            'max_amount'          => ['required', 'numeric', 'min:0'],
            'requires_collateral' => ['nullable', 'boolean'],
            'requires_guarantor'  => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['requires_collateral'] = (bool) ($data['requires_collateral'] ?? false);
        $data['requires_guarantor']  = (bool) ($data['requires_guarantor'] ?? false);
        $data['is_active']           = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
