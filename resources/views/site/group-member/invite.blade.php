<x-site.layout :title="brand_title(__('borrower.apply.group.invite_subject'))">
    <div class="max-w-lg mx-auto py-12 px-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 space-y-5">
            <h1 class="text-2xl font-bold">{{ __('borrower.apply.group.invite_subject') }}</h1>
            <p class="text-sm text-gray-600">
                {{ __('borrower.apply.group.invite_intro', [
                    'leader' => $invitation->leader?->full_name ?? brand_name(),
                    'name' => $invitation->displayName(),
                ]) }}
            </p>
            @if ($invitation->draft_reference)
                <p class="text-sm"><span class="text-gray-500">{{ __('borrower.apply.group.reference') }}:</span> <span class="font-mono font-semibold">{{ $invitation->draft_reference }}</span></p>
            @endif
            @if ($invitation->product)
                <p class="text-sm text-gray-500">{{ __('borrower.apply.group.invite_product', ['product' => $invitation->product->name]) }}</p>
            @endif
            @if ($invitation->amount_per_member)
                <p class="text-sm text-gray-700">{{ __('borrower.apply.group_setup.amount_per_member') }}: <strong>{{ format_money((float) $invitation->amount_per_member) }}</strong></p>
            @endif
            @if ($invitation->requested_tenure_months)
                <p class="text-sm text-gray-700">{{ __('borrower.apply.group_setup.tenure') }}: <strong>{{ $invitation->requested_tenure_months }} {{ __('borrower.apply.quote.months') }}</strong></p>
            @endif
            @if ($invitation->invitation_reason)
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">{{ __('borrower.apply.group.invitation_reason') }}</p>
                    <p>{{ $invitation->invitation_reason }}</p>
                </div>
            @endif
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <form method="POST" action="{{ route('site.group-member.accept', $invitation->token) }}">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                        {{ __('borrower.apply.group.accept_invite') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('site.group-member.reject', $invitation->token) }}">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto inline-flex bg-white hover:bg-gray-50 text-gray-700 font-semibold px-6 py-3 rounded-full text-sm ring-1 ring-gray-200">
                        {{ __('borrower.apply.group.decline_invite') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-site.layout>
