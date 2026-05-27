<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends ResourceController
{
    protected string $model = User::class;
    protected string $routePrefix = 'admin.users';
    protected string $viewFolder = 'users';
    protected string $singular = 'user';

    protected function rules(?Model $model = null): array
    {
        $id = $model?->id;

        return [
            'name'           => ['required', 'string', 'max:150'],
            'email'          => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone'          => ['nullable', 'string', 'max:30'],
            'role'           => ['required', 'in:admin,super_admin,manager,officer,agent,customer'],
            'branch_id'      => ['nullable', 'exists:branches,id'],
            'approval_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
            'password'       => [$id ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }

    protected function formData(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'roles'    => [
                'super_admin' => 'Super admin',
                'admin'       => 'Admin',
                'manager'     => 'Manager',
                'officer'     => 'Officer',
                'agent'       => 'Agent',
                'customer'    => 'Customer',
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
