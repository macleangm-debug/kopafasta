<?php

namespace App\Http\Controllers\Admin;

use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateController extends ResourceController
{
    protected string $model = DocumentTemplate::class;
    protected string $routePrefix = 'admin.document-templates';
    protected string $viewFolder = 'document-templates';
    protected string $singular = 'document template';

    protected function rules(?Model $model = null): array
    {
        return [
            'name'    => ['required', 'string', 'max:150'],
            'code'    => ['required', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
