<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lender;
use App\Services\CapitalPartnerMetricsService;
use Illuminate\Database\Eloquent\Model;

class LenderController extends ResourceController
{
    protected string $model = Lender::class;
    protected string $routePrefix = 'admin.lenders';
    protected string $viewFolder = 'lenders';
    protected string $singular = 'lender';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'           => ['required', 'string', 'max:30'],
            'name'           => ['required', 'string', 'max:150'],
            'type'           => ['required', 'in:bank,institutional,individual,sacco,other'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
            'credit_limit'   => ['nullable', 'numeric', 'min:0'],
            'status'         => ['required', 'in:active,inactive,suspended'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'types'    => ['bank' => 'Bank', 'institutional' => 'Institutional', 'individual' => 'Individual', 'sacco' => 'SACCO', 'other' => 'Other'],
            'statuses' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'],
        ];
    }

    public function show($id)
    {
        $record = Lender::with('pools')->findOrFail($id);
        $metrics = app(CapitalPartnerMetricsService::class)->forLender($record);
        $pools = app(CapitalPartnerMetricsService::class)->poolRows($record);

        return view('admin.lenders.show', compact('record', 'metrics', 'pools'));
    }
}
