<?php

namespace App\Http\Controllers\Admin;

use App\Models\DisbursementMethod;
use Illuminate\Database\Eloquent\Model;

class DisbursementMethodController extends ResourceController
{
    protected string $model = DisbursementMethod::class;
    protected string $routePrefix = 'admin.disbursement-methods';
    protected string $viewFolder = 'disbursement-methods';
    protected string $singular = 'disbursement method';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'channel' => ['required', 'in:bank_transfer,mobile_money,cash,cheque,wallet'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0'],
            'percentage_fee' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function formData(): array
    {
        return [
            'channels' => ['bank_transfer'=>'Bank transfer','mobile_money'=>'Mobile money','cash'=>'Cash','cheque'=>'Cheque','wallet'=>'Wallet'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
