<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Support\SupportTaxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends ResourceController
{
    protected string $model = SupportTicket::class;
    protected string $routePrefix = 'admin.support-tickets';
    protected string $viewFolder = 'support-tickets';
    protected string $singular = 'ticket';

    public function __construct(
        private readonly SupportTicketService $tickets,
    ) {}

    protected function rules(?Model $model = null): array
    {
        return [
            'ticket_number'    => ['nullable', 'string', 'max:50'],
            'customer_id'      => ['nullable', 'exists:customers,id'],
            'guest_name'       => ['nullable', 'string', 'max:120'],
            'guest_email'      => ['nullable', 'email', 'max:150'],
            'guest_phone'      => ['nullable', 'string', 'max:30'],
            'contact_kind'     => ['nullable', 'in:customer,guest'],
            'source'           => ['nullable', Rule::in(SupportTicketService::SOURCES)],
            'assigned_to'      => ['nullable', 'exists:users,id'],
            'subject'          => ['required', 'string', 'max:200'],
            'subject_other'    => ['nullable', 'string', 'max:200'],
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
        $taxonomy = SupportTaxonomy::all();

        return [
            'customers' => Customer::query()
                ->orderBy('first_name')
                ->limit(50)
                ->get()
                ->mapWithKeys(function (Customer $c) {
                    $label = trim($c->first_name.' '.$c->last_name);
                    if ($c->customer_number) {
                        $label .= ' · '.$c->customer_number;
                    }
                    if ($c->phone) {
                        $label .= ' · '.$c->phone;
                    }

                    return [$c->id => $label !== '' ? $label : 'Customer #'.$c->id];
                }),
            'agents' => User::query()
                ->where(function ($q) {
                    $q->where('role', 'agent')
                        ->orWhereIn('role', ['admin', 'manager']);
                })
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->orderByRaw("CASE WHEN role = 'agent' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $u) => [
                    $u->id => $u->name.($u->role === 'agent' ? '' : ' ('.$u->role.')'),
                ]),
            'agentCount' => User::query()
                ->where('role', 'agent')
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->count(),
            'priorities' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
            'statuses' => [
                'open' => 'Open',
                'in_progress' => 'In progress',
                'waiting' => 'Waiting',
                'resolved' => 'Resolved',
                'closed' => 'Closed',
            ],
            'categories' => $taxonomy['categories'],
            'subjectsByCategory' => $taxonomy['subjects'],
            'sources' => collect(SupportTicketService::SOURCES)
                ->mapWithKeys(fn ($s) => [$s => ucwords(str_replace('_', ' ', $s))])
                ->all(),
            'contactKinds' => ['customer' => 'Customer', 'guest' => 'Guest'],
        ];
    }

    public function store(Request $request)
    {
        $this->normalizeMoneyRequest($request);
        $data = $request->validate($this->rules());
        $data['actor'] = $request->user('admin');
        $data['source'] = $data['source'] ?? 'admin';
        if (empty($data['contact_kind'])) {
            $data['contact_kind'] = ! empty($data['customer_id']) ? 'customer' : 'guest';
        }
        if (($data['contact_kind'] ?? '') === 'guest') {
            $request->validate([
                'guest_name' => ['required', 'string', 'max:120'],
                'guest_phone' => ['required', 'string', 'max:30'],
            ]);
            $data['guest_name'] = $request->input('guest_name');
            $data['guest_phone'] = \App\Support\PhoneNumber::digits($request->input('guest_phone'))
                ?: $request->input('guest_phone');
            $data['customer_id'] = null;
        }
        if (($data['contact_kind'] ?? '') === 'customer' && empty($data['customer_id'])) {
            return back()
                ->withInput()
                ->withErrors(['customer_id' => 'Select a customer, or switch contact kind to Guest.']);
        }

        $record = $this->tickets->create($data);
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function show($id)
    {
        $record = SupportTicket::query()
            ->with(['customer', 'assignee', 'events.actor'])
            ->findOrFail($id);

        return view("admin.{$this->viewFolder}.show", ['record' => $record]);
    }

    public function update(Request $request, $id)
    {
        $record = SupportTicket::query()->findOrFail($id);
        $before = app(\App\Services\AuditService::class)->snapshot($record);
        $this->normalizeMoneyRequest($request);
        $data = $request->validate($this->rules($record));

        $actor = $request->user('admin');
        $subject = \App\Support\SupportTaxonomy::resolveSubject(
            $data['subject'] ?? null,
            $data['subject_other'] ?? null,
        );

        $statusChanged = isset($data['status']) && $data['status'] !== $record->status;
        $assigneeChanged = array_key_exists('assigned_to', $data)
            && (int) ($data['assigned_to'] ?? 0) !== (int) ($record->assigned_to ?? 0);

        $record->update([
            'customer_id' => $data['customer_id'] ?? null,
            'guest_name' => $data['guest_name'] ?? $record->guest_name,
            'guest_email' => $data['guest_email'] ?? $record->guest_email,
            'guest_phone' => $data['guest_phone'] ?? $record->guest_phone,
            'contact_kind' => $data['contact_kind']
                ?? ((! empty($data['customer_id']) || $record->customer_id) ? 'customer' : 'guest'),
            'source' => $data['source'] ?? $record->source,
            'subject' => $subject,
            'description' => $data['description'] ?? $record->description,
            'priority' => $data['priority'],
            'category' => $data['category'] ?? $record->category,
            'resolution_notes' => $data['resolution_notes'] ?? $record->resolution_notes,
            'resolved_at' => $data['resolved_at'] ?? $record->resolved_at,
        ]);

        if ($assigneeChanged) {
            $newAssignee = isset($data['assigned_to']) && $data['assigned_to'] !== ''
                ? (int) $data['assigned_to']
                : null;
            if ($newAssignee) {
                $this->tickets->reassign($record, $newAssignee, $actor);
            } else {
                $from = $record->assigned_to;
                $record->update(['assigned_to' => null]);
                $this->tickets->addEvent($record, 'reassigned', $actor, 'Unassigned', [
                    'from' => $from,
                    'to' => null,
                ]);
            }
        }

        if ($statusChanged) {
            $this->tickets->transitionStatus(
                $record->fresh(),
                $data['status'],
                $actor,
                null,
                [
                    'priority' => $data['priority'],
                    'escalated' => ($data['priority'] ?? '') === 'urgent' && $data['status'] === 'waiting',
                    'resolution_notes' => $data['resolution_notes'] ?? null,
                ],
            );
        } elseif (($data['priority'] ?? '') === 'urgent' && $record->priority !== 'urgent') {
            $record->update(['priority' => 'urgent']);
            $this->tickets->addEvent($record, 'escalated', $actor, null, ['priority' => 'urgent']);
        }

        $record->refresh();
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
    }

    public function searchCustomers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $rows = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $digits = preg_replace('/\D+/', '', $q) ?: '';
                $query->where(function ($inner) use ($term, $digits) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('customer_number', 'like', $term);
                    if ($digits !== '') {
                        $inner->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderBy('first_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email', 'customer_number']);

        return response()->json([
            'data' => $rows->map(fn (Customer $c) => [
                'id' => $c->id,
                'label' => trim($c->first_name.' '.$c->last_name)
                    .($c->customer_number ? ' · '.$c->customer_number : '')
                    .($c->phone ? ' · '.$c->phone : '')
                    .($c->email ? ' · '.$c->email : ''),
            ]),
        ]);
    }

    public function linkCustomer(Request $request, $id)
    {
        $ticket = SupportTicket::query()->findOrFail($id);
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
        ]);

        $this->tickets->linkCustomer($ticket, (int) $data['customer_id'], $request->user('admin'));

        return redirect()
            ->route("{$this->routePrefix}.show", $ticket)
            ->with('status', 'Guest ticket linked to customer.');
    }
}
