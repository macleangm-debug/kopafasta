<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends ResourceController
{
    protected string $model = User::class;
    protected string $routePrefix = 'admin.users';
    protected string $viewFolder = 'users';
    protected string $singular = 'user';

    public function __construct(private RoleService $roles)
    {
    }

    protected function rules(?Model $model = null): array
    {
        $id = $model?->id;
        $allowedRoles = $this->roles->userFormRoles();

        return [
            'name'           => ['required', 'string', 'max:150'],
            'email'          => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone'          => ['nullable', 'string', 'max:30'],
            'role'           => ['required', Rule::in($allowedRoles)],
            'branch_id'      => ['nullable', 'exists:branches,id'],
            'approval_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
            'password'       => [$id ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }

    protected function formData(): array
    {
        $roleOptions = [];

        foreach ($this->roles->userFormRoles() as $code) {
            $roleOptions[$code] = $this->roles->label($code);
        }

        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'roles'    => $roleOptions,
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
