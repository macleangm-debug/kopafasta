@php
    $isMember = (bool) $conversation->customer_id;
    $name = $serialized['name'] ?? ($isMember ? __('admin.support.conversation.member') : __('admin.support.guest_non_member'));
@endphp
<x-admin.layout :title="__('admin.support.conversation.title')" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.support.inbox') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('admin.support.conversation.back_inbox') }}</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <p class="text-sm font-bold text-gray-900">{{ $name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $isMember ? __('admin.support.conversation.member_conversation') : __('admin.support.guest_non_member') }}
                        · {{ app(\App\Services\Support\SupportConversationService::class)->deskState($conversation) }}
                        @if ($conversation->needs_human) · {{ __('admin.support.conversation.needs_human') }} @endif
                        @if ($conversation->conversation_number) · {{ $conversation->publicNumber() }} @endif
                    </p>
                    @php
                        $autoMeta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
                        $steps = $autoMeta['steps_attempted'] ?? [];
                    @endphp
                    @if (is_array($steps) && $steps !== [])
                        <div class="mt-3 rounded-xl bg-brand-muted/40 px-3 py-2 text-xs text-gray-700">
                            <p class="font-bold text-brand mb-1">Automation attempted</p>
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach ($steps as $step)
                                    @if (is_array($step))
                                        <li>{{ ($step['type'] ?? '') }}{{ isset($step['key']) ? ': '.$step['key'] : '' }}{{ isset($step['slug']) ? ': '.$step['slug'] : '' }}{{ !empty($step['creates_ticket']) ? ' (ticket)' : '' }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="p-5 max-h-[28rem] overflow-y-auto space-y-3">
                    @forelse ($conversation->messages as $message)
                        <div class="rounded-xl px-4 py-3 text-sm {{ $message->sender_type === 'staff' ? 'bg-amber-50 ml-8' : ($message->sender_type === 'bot' ? 'bg-gray-50 mr-8' : 'bg-sky-50 mr-8') }}">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">
                                {{ $message->sender_type }}
                                · {{ $message->created_at?->format('d M H:i') }}
                            </p>
                            <p class="whitespace-pre-wrap text-gray-800">{{ $message->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 text-center py-8">{{ __('admin.support.inbox.no_messages') }}</p>
                    @endforelse
                </div>
            </div>

            <form method="POST" action="{{ route('admin.support.inbox.reply', $conversation) }}" class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-3">
                @csrf
                <label class="block text-sm font-semibold text-gray-900">{{ __('admin.support.conversation.reply') }}</label>
                <textarea name="body" rows="4" required maxlength="5000" class="w-full rounded-xl border-gray-300 text-sm focus:ring-brand/40"></textarea>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex rounded-xl bg-brand-gold text-brand font-semibold text-sm px-5 py-2.5 hover:brightness-95">{{ __('admin.support.conversation.send_reply') }}</button>
                    @if (! $conversation->assigned_to)
                        <button form="accept-form" type="submit" class="inline-flex rounded-xl ring-1 ring-brand/20 text-brand font-semibold text-sm px-5 py-2.5 hover:bg-brand-muted/40">{{ __('admin.support.conversation.accept') }}</button>
                    @endif
                </div>
            </form>
            @if (! $conversation->assigned_to)
                <form id="accept-form" method="POST" action="{{ route('admin.support.inbox.accept', $conversation) }}" class="hidden">@csrf</form>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Member context</p>
                @if ($isMember && $conversation->customer)
                    <p><span class="text-gray-500">Name:</span> <span class="font-semibold">{{ $name }}</span></p>
                    <p><span class="text-gray-500">Phone:</span> {{ $conversation->customer->phone ?: '—' }}</p>
                    <p><span class="text-gray-500">Member #:</span> {{ $conversation->customer->customer_number ?: '—' }}</p>
                    <div class="pt-2 flex flex-wrap gap-2">
                        <a href="{{ route('admin.customers.show', $conversation->customer) }}" class="inline-flex rounded-xl ring-1 ring-gray-200 px-3 py-2 text-xs font-semibold text-gray-800 hover:bg-gray-50">View member</a>
                    </div>
                @else
                    <p class="font-semibold text-amber-800">{{ __('admin.support.guest_non_member') }}</p>
                    <p><span class="text-gray-500">Name:</span> {{ $conversation->user?->name ?: '—' }}</p>
                    <p><span class="text-gray-500">Phone:</span> {{ $conversation->user?->phone ?: '—' }}</p>
                    <p><span class="text-gray-500">Email:</span> {{ $conversation->user?->email ?: '—' }}</p>
                    <p class="text-xs text-gray-500 pt-1">They do not need to become a member to receive support.</p>
                @endif
            </div>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Case</p>
                <p class="text-xs text-gray-500">Not every chat needs a ticket. Create a case only when investigation is required.</p>
                <form method="POST" action="{{ route('admin.support.inbox.create-case', $conversation) }}" class="space-y-2">
                    @csrf
                    <input type="text" name="subject" placeholder="Case subject (optional)" class="w-full rounded-xl border-gray-300 text-sm">
                    <button type="submit" class="w-full inline-flex justify-center rounded-xl bg-brand text-white text-xs font-semibold px-4 py-2.5 hover:brightness-95">Create case</button>
                </form>
                @if ($openCases->isNotEmpty())
                    <ul class="pt-2 space-y-2 border-t border-gray-100">
                        @foreach ($openCases as $case)
                            <li>
                                <a href="{{ route('admin.support-tickets.show', $case) }}" class="text-xs font-semibold text-brand hover:underline">
                                    {{ $case->ticket_number }} · {{ $case->subject ?: $case->category }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</x-admin.layout>
