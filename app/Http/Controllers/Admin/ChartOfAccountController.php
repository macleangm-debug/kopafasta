<?php

namespace App\Http\Controllers\Admin;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccountController extends ResourceController
{
    protected string $model = ChartOfAccount::class;
    protected string $routePrefix = 'admin.chart-of-accounts';
    protected string $viewFolder = 'chart-of-accounts';
    protected string $singular = 'GL account';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'    => ['required', 'string', 'max:20'],
            'name'    => ['required', 'string', 'max:150'],
            'type'    => ['required', 'in:asset,liability,equity,income,expense'],
            'category'=> ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'opening_balance' => ['nullable', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'parents' => ChartOfAccount::orderBy('code')->pluck('name', 'id'),
            'types'   => ['asset'=>'Asset','liability'=>'Liability','equity'=>'Equity','income'=>'Income','expense'=>'Expense'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        return $data;
    }
}
