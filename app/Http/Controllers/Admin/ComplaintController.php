<?php

namespace App\Http\Controllers\Admin;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ComplaintController extends ResourceController
{
    protected string $model = Complaint::class;
    protected string $routePrefix = 'admin.complaints';
    protected string $viewFolder = 'complaints';
    protected string $singular = 'complaint';

    protected function rules(?Model $model = null): array
    {
        return [
            'complaint_number' => ['nullable', 'string', 'max:50'],
            'customer_id'      => ['nullable', 'exists:customers,id'],
            'handled_by'       => ['nullable', 'exists:users,id'],
            'subject'          => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string'],
            'severity'         => ['required', 'in:low,medium,high,critical'],
            'status'           => ['required', 'in:open,investigating,resolved,closed,escalated'],
            'channel'          => ['nullable', 'string', 'max:50'],
            'resolved_at'      => ['nullable', 'date'],
            'resolution_notes' => ['nullable', 'string'],
        ];
    }

    protected function formData(): array
    {
        return [
            'customers' => Customer::orderBy('first_name')->limit(500)->get()
                ->mapWithKeys(fn($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
            'agents'    => User::whereIn('role', ['admin', 'manager', 'officer', 'agent'])->orderBy('name')->pluck('name', 'id'),
            'severities'=> ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'],
            'statuses'  => ['open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'closed' => 'Closed', 'escalated' => 'Escalated'],
            'channels'  => ['phone' => 'Phone', 'email' => 'Email', 'walk_in' => 'Walk in', 'web' => 'Web', 'social' => 'Social'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['complaint_number'])) {
            $data['complaint_number'] = 'CMP-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        }
        return $data;
    }
}
