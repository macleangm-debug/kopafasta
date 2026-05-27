<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;

class BranchController extends ResourceController
{
    protected string $model = Branch::class;
    protected string $routePrefix = 'admin.branches';
    protected string $viewFolder = 'branches';
    protected string $singular = 'branch';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'      => ['required', 'string', 'max:30'],
            'name'      => ['required', 'string', 'max:150'],
            'region'    => ['nullable', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'address'   => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
