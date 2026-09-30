@php
    $customer = $record->customer;
    $agent = $record->assignee;
    $events = $record->events ?? collect();
    $conversation = $record->conversation;
    $isGuest = ($record->contact_kind === 'guest') || (! $record->customer_id && filled($record->guest_name));
    $locale = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw';
    $relatedLabel = match ($record->related_type) {
        'application' => 'Loan application',
        'loan' => 'Loan',
        'payment' => 'Payment',
        'account' => 'Account / Profile',
        default => $record->category ? ucfirst(str_replace('_', ' ', $record->category)) : null,
    };
    $relatedUrl = null;
    if ($record->related_type === 'application' && $record->related_id) {
        $relatedUrl = route('admin.loan-applications.show', $record->related_id);
    } elseif ($record->related_type === 'loan' && $record->related_id) {
        $relatedUrl = route('admin.loans.show', $record->related_id);
    } elseif ($record->related_type === 'account' && $customer) {
        $relatedUrl = route('admin.customers.show', $customer);
    }
    $internalNotes = $events->where('event', 'internal_note');
    $activity = $events->where('event', '!=', 'internal_note');
@endphp
<x-admin.layout :title="$record->ticket_number" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.support-tickets.index') }}" class="text-sm font-semibold text-brand hover:underline">← Cases</a>
    </div>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 mb-6"
         x-data="{ action: null, draft: '', insertQuick(b){ this.draft = b; } }">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Case {{ $record->ticket_number }}</p>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">{{ $record->subject ?: 'Untitled case' }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold">{{ display_label($record->status, 'ticket_status') }}</span>
                    <span class="rounded-full bg-amber-50 text-amber-800 px-2.5 py-1 font-semibold">{{ ucfirst($record->priority) }}</span>
                    @if ($record->category)
                        <span class="rounded-full bg-brand-muted/60 text-brand px-2.5 py-1 font-semibold">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</span>
                    @endif
                    @if ($record->escalated_to_role)
                        <span class="rounded-full bg-rose-50 text-rose-800 px-2.5 py-1 font-semibold">Escalated · {{ $record->escalated_to_role }}</span>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="action = action === 'reply' ? null : 'reply'" class="rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5">Reply</button>
                <button type="button" @click="action = action === 'note' ? null : 'note'" class="rounded-xl ring-1 ring-brand/20 text-brand text-sm font-semibold px-4 py-2.5">Add internal note</button>
                @if (! in_array($record->status, ['resolved', 'closed'], true))
                    <button type="button" @click="action = action === 'escalate' ? null : 'escalate'" class="rounded-xl ring-1 ring-rose-200 text-rose-800 text-sm font-semibold px-4 py-2.5">Escalate</button>
                    <button type="button" @click="action = action === 'resolve' ? null : 'resolve'" class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Resolve case</button>
                @endif
            </div>
        </div>

        <div class="mt-5 grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Customer</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $record->contactLabel() }} · {{ $isGuest ? 'Guest' : 'Member' }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $customer?->phone ?: $record->guest_phone ?: '—' }}
                    @if ($customer?->customer_number) · {{ $customer->customer_number }} @endif
                </p>
                @if ($customer)
                    <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline">Open Member 360 →</a>
                @endif
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Related to</p>
                @if ($relatedLabel)
                    <p class="font-semibold text-gray-900 mt-1">{{ $relatedLabel }}
                        @if ($record->related_id && $record->related_type !== 'account')
                            #{{ $record->related_id }}
                        @endif
                    </p>
                    @if ($relatedUrl)
                        <a href="{{ $relatedUrl }}" class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline">View {{ strtolower($relatedLabel) }} →</a>
                    @endif
                @else
                    <p class="text-gray-500 mt-1 text-xs">Not linked to a specific record.</p>
                @endif
                <p class="text-xs text-gray-400 mt-2">Assigned: {{ $agent?->name ?? 'Unassigned' }}</p>
            </div>
        </div>

        {{-- Inline action panels (same surface) --}}
        <div x-show="action === 'reply'" x-cloak class="mt-5 rounded-xl bg-gray-50 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-900">Reply to member</p>
            @if ($conversation)
                @if (! empty($quickReplies))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($quickReplies as $qr)
                            <button type="button" @click="insertQuick(@js($qr['body_'.$locale] ?? $qr['body_sw']))"
                                    class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-white ring-1 ring-brand/15 text-brand">
                                {{ $qr['label_'.$locale] ?? $qr['label_sw'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.support-tickets.reply', $record) }}">
                    @csrf
                    <textarea name="body" x-model="draft" rows="3" required maxlength="5000" class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                    <div class="mt-2 flex gap-2">
                        <button class="rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2">Send reply</button>
                        <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                    </div>
                </form>
            @else
                <p class="text-xs text-gray-500">No linked conversation. Reply from Inbox or link a chat when creating the case.</p>
            @endif
        </div>

        <div x-show="action === 'note'" x-cloak class="mt-5 rounded-xl bg-amber-50/60 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-900">Internal note <span class="font-normal text-gray-500">(staff only)</span></p>
            <form method="POST" action="{{ route('admin.support-tickets.note', $record) }}">
                @csrf
                <textarea name="body" rows="3" required maxlength="5000" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Visible to Support and escalated teams only…"></textarea>
                <div class="mt-2 flex gap-2">
                    <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2">Save note</button>
                    <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                </div>
            </form>
        </div>

        <div x-show="action === 'escalate'" x-cloak class="mt-5 rounded-xl bg-rose-50/50 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-900">Escalate</p>
            <p class="text-xs text-gray-600">Support stays the customer contact. The department receives an internal handoff.</p>
            <form method="POST" action="{{ route('admin.support-tickets.escalate', $record) }}" class="space-y-3"
                  onsubmit="event.preventDefault(); confirmForm(this, { title: 'Escalate this case?', message: 'The selected team will be notified. You remain the member’s support contact unless reassigned.' })">
                @csrf
                <label class="block text-xs font-semibold text-gray-700">Department / role
                    <select name="escalated_to_role" required class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($escalationRoles as $role)
                            <option value="{{ $role }}">{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-semibold text-gray-700">Reason
                    <textarea name="reason" rows="2" required maxlength="1000" class="mt-1 w-full rounded-xl border-gray-200 text-sm" placeholder="e.g. Application appears stuck after guarantor completion"></textarea>
                </label>
                <label class="block text-xs font-semibold text-gray-700">Internal note (optional)
                    <textarea name="internal_note" rows="2" maxlength="5000" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea>
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="notify_member" value="1" checked class="rounded border-gray-300 text-brand">
                    Notify member (Swahili escalation message)
                </label>
                <div class="flex gap-2">
                    <button class="rounded-xl bg-rose-700 text-white text-sm font-semibold px-4 py-2">Escalate</button>
                    <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                </div>
            </form>
        </div>

        <div x-show="action === 'resolve'" x-cloak class="mt-5 rounded-xl bg-emerald-50/50 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-900">Resolution</p>
            <form method="POST" action="{{ route('admin.support-tickets.resolve', $record) }}" class="space-y-3"
                  onsubmit="event.preventDefault(); confirmForm(this, { title: 'Resolve this case?', message: 'The member will be notified through the linked conversation.' })">
                @csrf
                <label class="block text-xs font-semibold text-gray-700">Resolution type
                    <select name="resolution_type" required class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                        <option value="resolved">Resolved</option>
                        <option value="guidance_provided">Guidance provided</option>
                        <option value="technical_fixed">Technical issue fixed</option>
                        <option value="application_clarified">Application/payment clarified</option>
                        <option value="payment_clarified">Payment clarified</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-gray-700">Resolution note
                    <textarea name="resolution_notes" rows="2" maxlength="5000" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea>
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="invite_rating" value="1" checked class="rounded border-gray-300 text-brand">
                    Ask optional 1–5 support rating
                </label>
                <div class="flex gap-2">
                    <button class="rounded-xl bg-emerald-700 text-white text-sm font-semibold px-4 py-2">Resolve case</button>
                    <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-brand">Conversation</h2>
                </div>
                <div class="p-5 space-y-3 max-h-[24rem] overflow-y-auto">
                    @if ($conversation)
                        @forelse ($conversation->messages as $message)
                            @php $staff = $message->sender_type === 'staff'; @endphp
                            <div class="flex {{ $staff ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[90%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap {{ $staff ? 'bg-brand text-white' : 'bg-sky-50 text-gray-900' }}">
                                    <p>{{ $message->body }}</p>
                                    <p class="text-[10px] mt-1 {{ $staff ? 'text-white/70' : 'text-gray-400' }}">{{ $message->created_at?->format('d M H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No messages on the linked conversation.</p>
                        @endforelse
                    @else
                        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $record->description }}</p>
                        <p class="text-xs text-gray-400 mt-2">No live conversation linked.</p>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-brand">Internal notes</h2>
                    <p class="text-xs text-gray-500">Visible only to staff</p>
                </div>
                <ul class="divide-y divide-gray-50">
                    @forelse ($internalNotes as $note)
                        <li class="px-5 py-3 text-sm">
                            <p class="text-gray-800 whitespace-pre-wrap">{{ $note->body }}</p>
                            <p class="text-[10px] text-gray-400 mt-1">{{ $note->actor?->name }} · {{ $note->created_at?->format('d M Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-gray-500">No internal notes yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-brand">Activity</h2>
                </div>
                <ul class="divide-y divide-gray-50">
                    @forelse ($activity as $event)
                        <li class="px-5 py-3 text-sm">
                            <p class="font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $event->event)) }}
                                @if ($event->actor)<span class="font-normal text-gray-500">· {{ $event->actor->name }}</span>@endif
                            </p>
                            @if ($event->body)
                                <p class="text-xs text-gray-600 mt-0.5">{{ $event->body }}</p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-1">{{ $event->created_at?->format('d M Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-gray-500">No activity yet.</li>
                    @endforelse
                </ul>
            </div>

            @if ($record->rating)
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">CSAT</p>
                    <p class="text-lg font-bold mt-1">{{ $record->rating->rating }} / 5</p>
                    @if ($record->rating->comment)
                        <p class="text-xs text-gray-600 mt-1">{{ $record->rating->comment }}</p>
                    @endif
                </div>
            @endif

            @if ($isGuest && ! $record->customer_id)
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-brand">Link to member</h2>
                    <form method="POST" action="{{ route('admin.support-tickets.link-customer', $record) }}" class="mt-3 space-y-2">
                        @csrf
                        <input type="number" name="customer_id" required min="1" placeholder="Member ID"
                               class="w-full text-sm border border-brand/15 rounded-xl px-3 py-2">
                        <button type="submit" class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Link member</button>
                    </form>
                </div>
            @endif

            <p class="text-[11px] text-gray-400 px-1">
                <a href="{{ route('admin.support-tickets.edit', $record) }}" class="font-semibold text-brand hover:underline">Edit case fields</a>
                · operational metadata retained in audit trail
            </p>
        </aside>
    </div>
</x-admin.layout>
