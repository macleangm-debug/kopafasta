@php
    $customer = $record->customer;
    $agent    = $record->assignee;
    $prioritySupport = $customer
        ? app(\App\Services\LoyaltyRedemptionService::class)->activePrioritySupport($customer)
        : null;
    $events = $record->events ?? collect();
@endphp
<x-admin.show-page
    :title="$record->ticket_number"
    :heading="$record->subject ?: 'Ticket'"
    :subheading="$record->ticket_number"
    :backUrl="route('admin.support-tickets.index')"
    :editUrl="route('admin.support-tickets.edit', $record)"
    :fields="array_filter([
        'Ticket #'         => $record->ticket_number,
        'Contact'          => $record->contactLabel(),
        'Contact kind'     => $record->contact_kind ? ucfirst($record->contact_kind) : null,
        'Guest email'      => $record->guest_email,
        'Guest phone'      => $record->guest_phone,
        'Source'           => $record->source ? ucwords(str_replace('_', ' ', $record->source)) : null,
        'Priority customer'=> $prioritySupport ? 'PRIORITY CUSTOMER — Priority Support active until '.$prioritySupport->expires_at?->format('d M Y') : null,
        'Assigned to'      => $agent?->name,
        'Priority'         => ucfirst($record->priority ?? ''),
        'Status'           => display_label($record->status, 'ticket_status'),
        'Category'         => $record->category,
        'Resolved at'      => optional($record->resolved_at)->format('Y-m-d H:i'),
        'Description'      => ['value' => $record->description, 'wide' => true],
        'Resolution notes' => ['value' => $record->resolution_notes, 'wide' => true],
        'Created'          => $record->created_at?->format('Y-m-d H:i'),
    ])">
    <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
        <div class="border-b border-brand/10 px-6 py-4">
            <h2 class="text-sm font-semibold text-brand">Timeline</h2>
            <p class="text-xs text-gray-500 mt-0.5">Assignment and status audit trail</p>
        </div>
        <ul class="divide-y divide-brand/5">
            @forelse ($events as $event)
                <li class="px-6 py-3 flex gap-4">
                    <div class="w-28 shrink-0 text-xs text-gray-500">
                        {{ $event->created_at?->format('d M Y H:i') }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900">
                            {{ ucwords(str_replace('_', ' ', $event->event)) }}
                            @if ($event->actor)
                                <span class="font-normal text-gray-500">· {{ $event->actor->name }}</span>
                            @endif
                        </p>
                        @if ($event->body)
                            <p class="text-sm text-gray-600 mt-0.5">{{ $event->body }}</p>
                        @endif
                        @if (! empty($event->meta))
                            <p class="text-xs text-gray-400 mt-1 font-mono">{{ json_encode($event->meta) }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-6 py-8 text-sm text-gray-500">No events yet.</li>
            @endforelse
        </ul>
    </div>
</x-admin.show-page>
