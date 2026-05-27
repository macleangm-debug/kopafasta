<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use Illuminate\Database\Eloquent\Model;

class RoleController extends ResourceController
{
    protected string $model = Role::class;
    protected string $routePrefix = 'admin.roles';
    protected string $viewFolder = 'roles';
    protected string $singular = 'role';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'        => ['required', 'string', 'max:50'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions_text' => ['nullable', 'string'],
            'is_system'   => ['nullable', 'boolean'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $text = (string) ($data['permissions_text'] ?? '');
        unset($data['permissions_text']);
        $data['permissions'] = collect(preg_split('/\r?\n/', $text))
            ->map(fn($l) => trim($l))->filter()->values()->all();
        $data['is_system'] = (bool) ($data['is_system'] ?? false);
        return $data;
    }
}
