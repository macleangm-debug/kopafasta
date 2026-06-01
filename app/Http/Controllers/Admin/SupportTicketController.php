<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportTicketController extends ResourceController
{
    protected string $model = SupportTicket::class;
    protected string $routePrefix = 'admin.support-tickets';
    protected string $viewFolder = 'support-tickets';
    protected string $singular = 'ticket';

    protected function rules(?Model $model = null): array
    {
        return [
            'ticket_number'    => ['nullable', 'string', 'max:50'],
            'customer_id'      => ['nullable', 'exists:customers,id'],
            'assigned_to'      => ['nullable', 'exists:users,id'],
            'subject'          => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string'],
            'priority'         => ['required', 'in:low,normal,high,urgent'],
            'status'           => ['required', 'in:open,in_progress,waiting,resolved,closed'],
            'category'         => ['nullable', 'string', 'max:80'],
            'resolved_at'      => ['nullable', 'date'],
            'resolution_notes' => ['nullable', 'string'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'customers' => Customer::orderBy('first_name')->limit(500)->get()
                ->mapWithKeys(fn($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
            'agents'    => User::whereIn('role', ['admin', 'manager', 'officer', 'agent'])->orderBy('name')->pluck('name', 'id'),
            'priorities'=> ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
            'statuses'  => ['open' => 'Open', 'in_progress' => 'In progress', 'waiting' => 'Waiting', 'resolved' => 'Resolved', 'closed' => 'Closed'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['ticket_number'])) {
            $data['ticket_number'] = 'TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        }
        return $data;
    }
}
