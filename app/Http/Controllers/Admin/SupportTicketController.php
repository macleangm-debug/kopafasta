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
            'customers' => Customer::orderBy('first_name')->limit(500)->get()
                ->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
            'agents' => User::query()
                ->where(function ($q) {
                    $q->where('role', 'agent')
                        ->orWhereIn('role', ['admin', 'manager']);
                })
                ->orderByRaw("CASE WHEN role = 'agent' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->pluck('name', 'id'),
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
                $query->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('member_number', 'like', $term);
                });
            })
            ->orderBy('first_name')
            ->limit(25)
            ->get(['id', 'first_name', 'last_name', 'phone', 'member_number']);

        return response()->json([
            'data' => $rows->map(fn (Customer $c) => [
                'id' => $c->id,
                'label' => trim($c->first_name.' '.$c->last_name)
                    .($c->member_number ? ' · '.$c->member_number : '')
                    .($c->phone ? ' · '.$c->phone : ''),
            ]),
        ]);
    }
}
