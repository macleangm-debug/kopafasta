@props([
    'pending' => null,
])

@php
    $rows = collect($pending ?? []);
    $first = $rows->first();
    $onRequestPage = request()->routeIs('site.borrower.guarantor-requests.show');
@endphp

@if ($first && ! $onRequestPage)
    @php
        $borrowerName = trim(($first->borrower->first_name ?? '').' '.($first->borrower->last_name ?? '')) ?: __('borrower.guarantor.loan');
        $productName = $first->application?->product?->name
            ?? $first->invitation?->product?->name
            ?? __('borrower.guarantor.loan');
        $amount = (float) ($first->application?->requested_amount ?? $first->invitation?->requested_amount ?? 0);
        $link = $first->link;
    @endphp
    <div
        x-data="{ open: true }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[10040] flex items-end sm:items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="guarantor-invite-popup-title"
    >
        <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="open = false"></div>
        <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.guarantor.action_required') }}</p>
                <h2 id="guarantor-invite-popup-title" class="text-xl font-bold mt-1">{{ __('borrower.guarantor_invite.notify_request_title') }}</h2>
                <p class="mt-2 text-sm text-white/85">
                    {{ __('borrower.guarantor_invite.guarantor_received', [
                        'borrower' => $borrowerName,
                        'reference' => $first->application?->application_number ?? '—',
                    ]) }}
                </p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <dl class="rounded-2xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-3 text-sm space-y-2">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
                        <dd class="font-semibold text-gray-900 text-right">{{ $borrowerName }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                        <dd class="font-semibold text-gray-900 text-right">{{ $productName }}</dd>
                    </div>
                    @if ($amount > 0)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
                            <dd class="font-bold text-gray-900 tabular-nums text-right">{{ format_money($amount) }}</dd>
                        </div>
                    @endif
                </dl>
                <p class="text-xs text-gray-500">{{ __('borrower.guarantor_notifications.popup_hint') }}</p>
                <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}">
                        @csrf
                        <input type="hidden" name="action" value="reject">
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex justify-center px-4 py-2.5 rounded-xl text-sm font-semibold text-red-700 bg-white ring-1 ring-red-200 hover:bg-red-50">
                            {{ __('borrower.guarantor_notifications.decline_cta') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}">
                        @csrf
                        <input type="hidden" name="action" value="approve">
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex justify-center px-5 py-2.5 rounded-xl text-sm font-bold bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                            {{ __('borrower.guarantor_notifications.accept_cta') }}
                        </button>
                    </form>
                </div>
                @if ($rows->count() > 1)
                    <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor']) }}"
                       class="block text-center text-xs font-semibold text-brand hover:underline">
                        {{ __('borrower.guarantor_notifications.view_all_requests', ['count' => $rows->count()]) }}
                    </a>
                @endif
                <button type="button" @click="open = false" class="w-full text-sm text-gray-500 hover:text-gray-700 py-1">
                    {{ __('borrower.guarantor_notifications.dismiss_popup') }}
                </button>
            </div>
        </div>
    </div>
@endif
