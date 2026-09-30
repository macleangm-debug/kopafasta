@php
    $customer = $record->customer;
    $agent = $record->assignee;
    $events = $record->events ?? collect();
    $conversation = $record->conversation;
    $isGuest = ($record->contact_kind === 'guest') || (! $record->customer_id && filled($record->guest_name));
    $locale = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw';
    $sla = $sla ?? ['label' => 'On track', 'state' => 'on_track'];
    $slaState = $sla['state'] ?? 'on_track';
    $slaBadge = match ($slaState) {
        'overdue' => 'bg-rose-100 text-rose-900 ring-rose-200',
        'warning' => 'bg-amber-100 text-amber-950 ring-amber-200',
        'resolved' => 'bg-emerald-100 text-emerald-900 ring-emerald-200',
        default => 'bg-slate-100 text-slate-800 ring-slate-200',
    };
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
    $activity = $events->sortBy('id');
    $seedMessages = [];
    if ($conversation) {
        foreach ($conversation->messages as $message) {
            $seedMessages[] = [
                'id' => (int) $message->id,
                'role' => in_array($message->sender_type, ['staff', 'bot'], true) ? 'bot' : 'user',
                'text' => (string) $message->body,
                'time' => $message->created_at ? format_app_datetime($message->created_at, 'H:i') : null,
            ];
        }
    }
    $quickReplyBodies = collect($quickReplies ?? [])->mapWithKeys(fn ($qr) => [
        $qr['key'] => $qr['body_'.$locale] ?? $qr['body_sw'] ?? '',
    ])->all();
    $isOpen = ! in_array($record->status, ['resolved', 'closed'], true);
@endphp
<x-admin.layout :title="$record->ticket_number" heading="" subheading="">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.support-tickets.index') }}" class="text-sm font-semibold text-brand hover:underline">← Tickets</a>
        @if ($conversation)
            <a href="{{ route('admin.support.inbox.show', $conversation) }}" class="text-sm font-semibold text-slate-600 hover:underline">Open Inbox conversation →</a>
        @endif
    </div>

    {{-- HERO --}}
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Ticket {{ $record->ticket_number }}</p>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">{{ $record->subject ?: 'Untitled ticket' }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold">{{ display_label($record->status, 'ticket_status') }}</span>
                    <span class="rounded-full bg-amber-50 text-amber-800 px-2.5 py-1 font-semibold">{{ ucfirst($record->priority) }}</span>
                    @if ($record->category)
                        <span class="rounded-full bg-brand-muted/60 text-brand px-2.5 py-1 font-semibold">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</span>
                    @endif
                    <span class="rounded-full ring-1 px-2.5 py-1 font-semibold {{ $slaBadge }}">{{ $sla['label'] }}</span>
                    @if ($record->escalated_to_role)
                        <span class="rounded-full bg-rose-50 text-rose-800 px-2.5 py-1 font-semibold">Escalated · {{ $record->escalated_to_role }}</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-2">
                    Opened {{ format_app_datetime($record->created_at, 'd M Y · H:i') }}
                    · Assigned {{ $agent?->name ?? 'Unassigned' }}
                    @if ($record->assigned_at) · {{ format_app_datetime($record->assigned_at, 'd M Y · H:i') }} @endif
                </p>
            </div>
            @if ($isOpen)
                <div class="flex flex-wrap gap-2" x-data="{ action: null }">
                    <button type="button" @click="action = action === 'note' ? null : 'note'" class="rounded-xl ring-1 ring-brand/20 text-brand text-sm font-semibold px-4 py-2.5">Internal note</button>
                    <button type="button" @click="action = action === 'escalate' ? null : 'escalate'" class="rounded-xl ring-1 ring-rose-200 text-rose-800 text-sm font-semibold px-4 py-2.5">Escalate / Reassign</button>
                    <button type="button" @click="action = action === 'resolve' ? null : 'resolve'" class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Resolve</button>

                    <div class="w-full" x-show="action" x-cloak>
                        <div x-show="action === 'note'" class="mt-3 rounded-xl bg-amber-50/60 p-4 space-y-3">
                            <form method="POST" action="{{ route('admin.support-tickets.note', $record) }}">
                                @csrf
                                <textarea name="body" rows="3" required maxlength="5000" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Staff only…"></textarea>
                                <div class="mt-2 flex gap-2">
                                    <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2">Save note</button>
                                    <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                                </div>
                            </form>
                        </div>
                        <div x-show="action === 'escalate'" class="mt-3 rounded-xl bg-rose-50/50 p-4 space-y-3">
                            <form method="POST" action="{{ route('admin.support-tickets.escalate', $record) }}" class="space-y-3"
                                  onsubmit="event.preventDefault(); confirmForm(this, { title: 'Escalate this ticket?', message: 'The selected team will be notified. Support keeps customer communication ownership unless reassigned.' })">
                                @csrf
                                <label class="block text-xs font-semibold text-gray-700">Destination
                                    <select name="escalated_to_role" required class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                        <option value="">Select…</option>
                                        @foreach ($escalationRoles as $role)
                                            <option value="{{ $role }}">{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-xs font-semibold text-gray-700">Reason
                                    <textarea name="reason" rows="2" required maxlength="1000" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea>
                                </label>
                                <label class="block text-xs font-semibold text-gray-700">Internal note (optional)
                                    <textarea name="internal_note" rows="2" maxlength="5000" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea>
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                                    <input type="checkbox" name="notify_member" value="1" checked class="rounded border-gray-300 text-brand">
                                    Notify member (customer-safe message only)
                                </label>
                                <div class="flex gap-2">
                                    <button class="rounded-xl bg-rose-700 text-white text-sm font-semibold px-4 py-2">Escalate</button>
                                    <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                                </div>
                            </form>
                        </div>
                        <div x-show="action === 'resolve'" class="mt-3 rounded-xl bg-emerald-50/50 p-4 space-y-3">
                            <form method="POST" action="{{ route('admin.support-tickets.resolve', $record) }}" class="space-y-3"
                                  onsubmit="event.preventDefault(); confirmForm(this, { title: 'Resolve this ticket?', message: 'The member receives a resolution message and rating request through the linked conversation.' })">
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
                                <input type="hidden" name="invite_rating" value="1">
                                <div class="flex gap-2">
                                    <button class="rounded-xl bg-emerald-700 text-white text-sm font-semibold px-4 py-2">Resolve ticket</button>
                                    <button type="button" @click="action = null" class="text-sm text-gray-500">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1.7fr)_minmax(16rem,0.95fr)] gap-6"
         x-data="ticket360Desk(@js([
             'draft' => '',
             'bodies' => $quickReplyBodies,
             'messages' => $seedMessages,
             'replyUrl' => $conversation ? route('admin.support-tickets.reply', $record) : null,
             'csrf' => csrf_token(),
         ]))">
        {{-- MAIN: shared chat + composer --}}
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden flex flex-col min-h-[28rem]">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-brand">Conversation</h2>
                <p class="text-xs text-slate-500">Same thread as Inbox — not a duplicate ticket chat</p>
            </div>
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2 bg-[#f7f8fa] max-h-[28rem]" x-ref="scroll">
                <template x-for="msg in messages" :key="msg.id || msg.text + (msg.time || '')">
                    <div class="flex" :class="msg.role === 'bot' ? 'justify-end' : 'justify-start'">
                        <div :class="msg.role === 'bot' ? 'kf-support-bubble kf-support-bubble--outbound' : 'kf-support-bubble kf-support-bubble--inbound'">
                            <div class="kf-support-bubble__body whitespace-pre-wrap" x-text="msg.text"></div>
                            <p class="kf-support-bubble__time" x-text="msg.time || ''"></p>
                        </div>
                    </div>
                </template>
                <p class="text-sm text-slate-500 text-center py-10" x-show="!messages.length">
                    {{ $conversation ? 'No messages yet.' : 'No linked conversation. Create from Inbox with Create ticket.' }}
                </p>
            </div>
            @if ($conversation && $isOpen)
                <div class="border-t border-slate-200/80 p-4 space-y-3 bg-white">
                    @if (! empty($quickReplies))
                        <div x-data="{ tq: '', replies: @js(collect($quickReplies)->map(fn ($qr) => [
                            'key' => $qr['key'],
                            'group' => $qr['group'] ?? '',
                            'label' => $qr['label_'.$locale] ?? $qr['label_sw'],
                        ])->values()) }" class="space-y-2">
                            <input type="search" x-model="tq" placeholder="Search templates…"
                                   class="w-full rounded-lg border-slate-200 text-xs">
                            <div class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                                <template x-for="qr in replies.filter(r => !tq || (r.label + ' ' + r.group + ' ' + r.key).toLowerCase().includes(tq.toLowerCase()))" :key="qr.key">
                                    <button type="button" @click="insertQuick(qr.key)"
                                            class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-brand-muted"
                                            x-text="qr.label"></button>
                                </template>
                            </div>
                        </div>
                    @endif
                    <form @submit.prevent="sendReply()" class="flex gap-3 items-end">
                        <textarea x-model="draft" x-ref="composer" rows="3" required maxlength="5000"
                                  placeholder="Write a reply…"
                                  class="flex-1 min-h-[4.5rem] rounded-xl border-slate-200 text-base focus:ring-brand/30"></textarea>
                        <button type="submit" :disabled="sending || !draft.trim()"
                                class="shrink-0 rounded-xl bg-brand-gold text-brand font-semibold text-sm px-5 py-3 disabled:opacity-60">Send</button>
                    </form>
                    <p class="text-xs text-red-700" x-show="error" x-text="error" x-cloak></p>
                </div>
            @endif
        </div>

        {{-- CONTEXT --}}
        <aside class="space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Customer</p>
                <p class="font-semibold text-gray-900">{{ $record->contactLabel() }} · {{ $isGuest ? 'Guest' : 'Member' }}</p>
                <p class="text-xs text-gray-500">{{ $customer?->phone ?: $record->guest_phone ?: '—' }}
                    @if ($customer?->customer_number) · {{ $customer->customer_number }} @endif
                </p>
                @if ($customer)
                    <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex text-xs font-semibold text-brand hover:underline">Open 360 →</a>
                @endif
            </div>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Related</p>
                @if ($relatedLabel)
                    <p class="font-semibold text-gray-900">{{ $relatedLabel }}
                        @if ($record->related_id && $record->related_type !== 'account') #{{ $record->related_id }} @endif
                    </p>
                    @if ($relatedUrl)
                        <a href="{{ $relatedUrl }}" class="inline-flex text-xs font-semibold text-brand hover:underline">View →</a>
                    @endif
                @elseif (! empty($context['application']) || ! empty($context['loan']) || ! empty($context['payment']))
                    @if (! empty($context['application']))
                        <a href="{{ $context['application']['url'] }}" class="block font-semibold text-brand hover:underline">App {{ $context['application']['number'] }}</a>
                    @endif
                    @if (! empty($context['loan']))
                        <a href="{{ $context['loan']['url'] }}" class="block font-semibold text-brand hover:underline">Loan {{ $context['loan']['number'] }}</a>
                    @endif
                    @if (! empty($context['payment']))
                        <p class="text-slate-700">Payment TZS {{ number_format((float) $context['payment']['amount']) }}</p>
                    @endif
                @else
                    <p class="text-xs text-gray-500">No linked application / loan / payment.</p>
                @endif
                @if ($conversation)
                    <p class="text-xs text-slate-500 pt-1">Originating conversation #{{ $conversation->id }}</p>
                @endif
            </div>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-brand">Activity</h2>
                    <p class="text-[10px] text-slate-500">Event-driven audit</p>
                </div>
                <ul class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                    @forelse ($activity as $event)
                        <li class="px-5 py-3 text-sm">
                            <p class="font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $event->event)) }}
                                @if ($event->actor)<span class="font-normal text-gray-500">· {{ $event->actor->name }}</span>@endif
                            </p>
                            @if ($event->body)
                                <p class="text-xs text-gray-600 mt-0.5 {{ $event->event === 'internal_note' ? 'bg-amber-50 rounded-lg px-2 py-1' : '' }}">{{ $event->body }}</p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-1">{{ format_app_datetime($event->created_at, 'd M Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-gray-500">No activity yet.</li>
                    @endforelse
                </ul>
            </div>

            @if ($record->rating)
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">CSAT</p>
                    <p class="text-lg font-bold mt-1 text-amber-500">{{ str_repeat('★', (int) $record->rating->rating) }}{{ str_repeat('☆', 5 - (int) $record->rating->rating) }}</p>
                    @if ($record->rating->comment)
                        <p class="text-xs text-gray-600 mt-1">{{ $record->rating->comment }}</p>
                    @endif
                </div>
            @endif

            @if ($record->escalated_to_role && $isOpen)
                <div class="rounded-2xl bg-white ring-1 ring-rose-200 shadow-sm p-5 space-y-3">
                    <p class="text-[10px] uppercase tracking-widest text-rose-700 font-semibold">Specialist response</p>
                    <p class="text-xs text-gray-600">Internal only — customer never sees this panel.</p>
                    <form method="POST" action="{{ route('admin.support-tickets.specialist-response', $record) }}" class="space-y-2">
                        @csrf
                        <textarea name="body" rows="3" required maxlength="5000" class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                        <button class="w-full rounded-xl bg-rose-700 text-white text-sm font-semibold px-4 py-2.5">Send internal response</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>

    @once
        <script>
            document.addEventListener('alpine:init', function () {
                Alpine.data('ticket360Desk', function (config) {
                    return {
                        draft: config.draft || '',
                        bodies: config.bodies || {},
                        messages: Array.isArray(config.messages) ? config.messages.slice() : [],
                        replyUrl: config.replyUrl,
                        csrf: config.csrf,
                        sending: false,
                        error: '',
                        csrfToken() {
                            var meta = document.querySelector('meta[name="csrf-token"]');
                            return (meta && meta.content) || this.csrf;
                        },
                        insertQuick(key) {
                            this.draft = this.bodies[key] || '';
                            this.$refs.composer && this.$refs.composer.focus();
                        },
                        scrollBottom() {
                            var self = this;
                            this.$nextTick(function () {
                                if (self.$refs.scroll) self.$refs.scroll.scrollTop = self.$refs.scroll.scrollHeight;
                            });
                        },
                        async sendReply() {
                            var body = (this.draft || '').trim();
                            if (!body || !this.replyUrl || this.sending) return;
                            this.sending = true;
                            this.error = '';
                            try {
                                var res = await fetch(this.replyUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ body: body }),
                                });
                                if (res.redirected || res.ok) {
                                    window.location.reload();
                                    return;
                                }
                                this.error = 'Reply failed.';
                            } catch (e) {
                                this.error = 'Reply failed.';
                            } finally {
                                this.sending = false;
                            }
                        },
                        init() { this.scrollBottom(); },
                    };
                });
            });
        </script>
    @endonce
</x-admin.layout>
