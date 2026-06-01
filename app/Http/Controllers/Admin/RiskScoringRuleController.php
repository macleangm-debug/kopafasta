<?php

namespace App\Http\Controllers\Admin;

use App\Models\RiskScoringRule;
use Illuminate\Database\Eloquent\Model;

class RiskScoringRuleController extends ResourceController
{
    protected string $model = RiskScoringRule::class;
    protected string $routePrefix = 'admin.risk-scoring-rules';
    protected string $viewFolder = 'risk-scoring-rules';
    protected string $singular = 'risk scoring rule';

    protected function rules(?Model $model = null): array
    {
        return [
            'factor'   => ['required', 'string', 'max:100'],
            'operator' => ['required', 'string', 'max:20'],
            'value'    => ['required', 'string', 'max:200'],
            'weight'   => ['required', 'integer'],
            'category' => ['required', 'in:demographic,financial,behavioural,collateral,external'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'operators'  => ['='=>'Equals','!='=>'Not equals','<'=>'Less than','>'=>'Greater than','<='=>'Less or equal','>='=>'Greater or equal','between'=>'Between','in'=>'In list'],
            'categories' => ['demographic'=>'Demographic','financial'=>'Financial','behavioural'=>'Behavioural','collateral'=>'Collateral','external'=>'External / CRB'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
