<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\PepFlag;
use Illuminate\Database\Eloquent\Model;

class PepFlagController extends ResourceController
{
    protected string $model = PepFlag::class;
    protected string $routePrefix = 'admin.pep-flags';
    protected string $viewFolder = 'pep-flags';
    protected string $singular = 'PEP flag';

    protected function rules(?Model $model = null): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'full_name'   => ['required', 'string', 'max:200'],
            'position'    => ['nullable', 'string', 'max:200'],
            'organization'=> ['nullable', 'string', 'max:200'],
            'category'    => ['required', 'in:domestic,foreign,international_org,family,associate'],
            'risk_level'  => ['required', 'in:low,medium,high,extreme'],
            'listed_on'   => ['nullable', 'date'],
            'is_active'   => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'customers'   => Customer::orderBy('first_name')->limit(500)->get()->mapWithKeys(fn($c)=>[$c->id=>trim(($c->first_name ?? '').' '.($c->last_name ?? ''))]),
            'categories'  => ['domestic'=>'Domestic','foreign'=>'Foreign','international_org'=>'International org','family'=>'Family','associate'=>'Associate'],
            'risk_levels' => ['low'=>'Low','medium'=>'Medium','high'=>'High','extreme'=>'Extreme'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
