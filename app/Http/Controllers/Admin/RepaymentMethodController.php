<?php

namespace App\Http\Controllers\Admin;

use App\Models\RepaymentMethod;
use Illuminate\Database\Eloquent\Model;

class RepaymentMethodController extends ResourceController
{
    protected string $model = RepaymentMethod::class;
    protected string $routePrefix = 'admin.repayment-methods';
    protected string $viewFolder = 'repayment-methods';
    protected string $singular = 'repayment method';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'channel' => ['required', 'in:bank_transfer,mobile_money,cash,cheque,standing_order,wallet'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0'],
            'percentage_fee' => ['nullable', 'numeric', 'min:0'],
            'auto_reconcile' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function formData(): array
    {
        return [
            'channels' => ['bank_transfer'=>'Bank transfer','mobile_money'=>'Mobile money','cash'=>'Cash','cheque'=>'Cheque','standing_order'=>'Standing order','wallet'=>'Wallet'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['auto_reconcile'] = (bool) ($data['auto_reconcile'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
