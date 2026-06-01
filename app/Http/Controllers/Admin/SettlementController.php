<?php

namespace App\Http\Controllers\Admin;

use App\Models\Settlement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SettlementController extends ResourceController
{
    protected string $model = Settlement::class;
    protected string $routePrefix = 'admin.settlements';
    protected string $viewFolder = 'settlements';
    protected string $singular = 'settlement';

    protected function rules(?Model $model = null): array
    {
        return [
            'reference'           => ['nullable', 'string', 'max:80'],
            'partner'             => ['required', 'string', 'max:100'],
            'settlement_date'     => ['required', 'date'],
            'gross_amount'        => ['required', 'numeric', 'min:0'],
            'fees'                => ['nullable', 'numeric', 'min:0'],
            'net_amount'          => ['nullable', 'numeric'],
            'transactions_count'  => ['nullable', 'integer', 'min:0'],
            'status'              => ['required', 'in:pending,processing,settled,failed'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'statuses' => ['pending' => 'Pending', 'processing' => 'Processing', 'settled' => 'Settled', 'failed' => 'Failed'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['reference'])) {
            $data['reference'] = 'STL-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        }
        if (! isset($data['net_amount']) || $data['net_amount'] === '') {
            $data['net_amount'] = (float) $data['gross_amount'] - (float) ($data['fees'] ?? 0);
        }
        return $data;
    }
}
