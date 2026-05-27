<?php

namespace App\Http\Controllers\Admin;

use App\Models\ApprovalLimit;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;

class ApprovalLimitController extends ResourceController
{
    protected string $model = ApprovalLimit::class;
    protected string $routePrefix = 'admin.approval-limits';
    protected string $viewFolder = 'approval-limits';
    protected string $singular = 'approval limit';

    protected function rules(?Model $model = null): array
    {
        return [
            'role_code' => ['required', 'string', 'max:50'],
            'action'    => ['required', 'string', 'max:50'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'currency'  => ['required', 'string', 'size:3'],
            'requires_dual_control' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function formData(): array
    {
        return [
            'roles'   => Role::orderBy('name')->pluck('name', 'code'),
            'actions' => collect(['loan_approve','loan_disburse','write_off','restructure','fee_waiver','manual_payment'])->mapWithKeys(fn($a)=>[$a=>ucwords(str_replace('_',' ',$a))]),
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['requires_dual_control'] = (bool) ($data['requires_dual_control'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
