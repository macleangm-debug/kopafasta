<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DepartmentController extends ResourceController
{
    protected string $model = Department::class;
    protected string $routePrefix = 'admin.departments';
    protected string $viewFolder = 'departments';
    protected string $singular = 'department';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'         => ['required', 'string', 'max:30'],
            'name'         => ['required', 'string', 'max:150'],
            'branch_id'    => ['nullable', 'exists:branches,id'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    protected function formData(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'users'    => User::orderBy('name')->pluck('name', 'id'),
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
