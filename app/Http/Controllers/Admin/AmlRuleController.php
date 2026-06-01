<?php

namespace App\Http\Controllers\Admin;

use App\Models\AmlRule;
use Illuminate\Database\Eloquent\Model;

class AmlRuleController extends ResourceController
{
    protected string $model = AmlRule::class;
    protected string $routePrefix = 'admin.aml-rules';
    protected string $viewFolder = 'aml-rules';
    protected string $singular = 'AML rule';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'rule_type' => ['required', 'in:large_txn,velocity,structuring,repeated_early_settle,multi_account,geo,pattern'],
            'threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'threshold_count'  => ['nullable', 'integer', 'min:0'],
            'window_days'      => ['nullable', 'integer', 'min:1'],
            'action'   => ['required', 'in:flag,block,review,report'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'rule_types' => ['large_txn'=>'Large transaction','velocity'=>'Velocity','structuring'=>'Structuring','repeated_early_settle'=>'Repeated early settlement','multi_account'=>'Multiple accounts','geo'=>'Geographic','pattern'=>'Pattern'],
            'actions'    => ['flag'=>'Flag','block'=>'Block','review'=>'Review','report'=>'Report (STR)'],
            'severities' => ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
