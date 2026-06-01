<?php

namespace App\Http\Controllers\Admin;

use App\Models\Reconciliation;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Model;

class ReconciliationController extends ResourceController
{
    protected string $model = Reconciliation::class;
    protected string $routePrefix = 'admin.reconciliations';
    protected string $viewFolder = 'reconciliations';
    protected string $singular = 'reconciliation';

    protected function rules(?Model $model = null): array
    {
        return [
            'settlement_id'   => ['nullable', 'exists:settlements,id'],
            'period_start'    => ['required', 'date'],
            'period_end'      => ['required', 'date', 'after_or_equal:period_start'],
            'system_total'    => ['required', 'numeric'],
            'bank_total'      => ['required', 'numeric'],
            'variance'        => ['nullable', 'numeric'],
            'status'          => ['required', 'in:pending,matched,variance,resolved'],
            'reconciled_at'   => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'settlements' => Settlement::orderByDesc('id')->limit(200)->pluck('reference', 'id'),
            'statuses'    => ['pending' => 'Pending', 'matched' => 'Matched', 'variance' => 'Variance', 'resolved' => 'Resolved'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! isset($data['variance']) || $data['variance'] === '') {
            $data['variance'] = (float) $data['system_total'] - (float) $data['bank_total'];
        }
        if (! $existing && empty($data['reconciled_by']) && auth()->id()) {
            $data['reconciled_by'] = auth()->id();
        }
        return $data;
    }
}
