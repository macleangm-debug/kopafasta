<?php

namespace App\Http\Controllers\Admin;

use App\Models\WriteOffRule;
use Illuminate\Database\Eloquent\Model;

class WriteOffRuleController extends ResourceController
{
    protected string $model = WriteOffRule::class;
    protected string $routePrefix = 'admin.write-off-rules';
    protected string $viewFolder = 'write-off-rules';
    protected string $singular = 'write-off rule';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'days_past_due' => ['required', 'integer', 'min:1'],
            'min_outstanding' => ['nullable', 'numeric', 'min:0'],
            'max_outstanding' => ['nullable', 'numeric', 'min:0'],
            'require_committee_approval' => ['nullable', 'boolean'],
            'auto_propose' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['require_committee_approval'] = (bool) ($data['require_committee_approval'] ?? false);
        $data['auto_propose'] = (bool) ($data['auto_propose'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
