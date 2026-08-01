@php
    $leaderName = $invitation->leader?->full_name ?? brand_name();
    $inviteeName = $invitation->displayName();
@endphp

<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.apply.group.invite_subject'))"
    :eyebrow="brand_name().' · '.__('borrower.apply.group.loan_label')"
    :heading="__('borrower.apply.group.invite_subject')"
    :lede="__('borrower.apply.group.invite_intro', ['leader' => $leaderName, 'name' => $inviteeName])"
    :asideSteps="[
        [__('borrower.guarantor_invite.shell_step_review'), __('borrower.apply.group.invite_shell_review_hint')],
        [__('borrower.guarantor_invite.shell_step_decide'), __('borrower.apply.group.invite_shell_decide_hint')],
        [__('borrower.guarantor_invite.shell_step_continue'), __('borrower.apply.group.invite_shell_continue_hint')],
    ]"
>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold tracking-tight mb-1">{{ __('borrower.apply.group.invite_subject') }}</h1>
        <p class="text-sm text-gray-600">
            {{ __('borrower.apply.group.invite_intro', ['leader' => $leaderName, 'name' => $inviteeName]) }}
        </p>
    </div>

    <dl class="glass-card rounded-2xl ring-1 ring-brand/10 divide-y divide-gray-100 text-sm mb-6 overflow-hidden">
        @if ($invitation->group_name)
            <div class="px-4 py-3 flex justify-between gap-3 bg-gradient-to-r from-brand-muted/40 to-white">
                <dt class="text-gray-500">{{ __('borrower.apply.group_setup.name') }}</dt>
                <dd class="font-semibold text-right text-brand">{{ $invitation->group_name }}</dd>
            </div>
        @endif
        @if ($invitation->draft_reference)
            <div class="px-4 py-3 flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.apply.group.reference') }}</dt>
                <dd class="font-mono font-semibold text-right">{{ $invitation->draft_reference }}</dd>
            </div>
        @endif
        @if ($invitation->product)
            <div class="px-4 py-3 flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                <dd class="font-semibold text-right">{{ $invitation->product->name }}</dd>
            </div>
        @endif
        @if ($invitation->amount_per_member)
            <div class="px-4 py-3 flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.apply.group_setup.amount_per_member') }}</dt>
                <dd class="font-semibold text-right">{{ format_money((float) $invitation->amount_per_member) }}</dd>
            </div>
        @endif
        @if ($invitation->requested_tenure_months)
            <div class="px-4 py-3 flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.apply.group_setup.tenure') }}</dt>
                <dd class="font-semibold text-right">{{ $invitation->requested_tenure_months }} {{ __('borrower.apply.quote.months') }}</dd>
            </div>
        @endif
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
            <dd class="font-semibold text-right text-brand">{{ $leaderName }}</dd>
        </div>
    </dl>

    @if ($invitation->invitation_reason)
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm mb-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">{{ __('borrower.apply.group.invitation_reason') }}</p>
            <p>{{ $invitation->invitation_reason }}</p>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <form method="POST" action="{{ route('site.group-member.accept', $invitation->token) }}" class="flex-1"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.apply.group.accept_confirm_title')), message: @js(__('borrower.apply.group.accept_confirm_message')), confirmLabel: @js(__('borrower.apply.group.accept_invite')), confirmClass: 'bg-brand-gold hover:brightness-95 text-brand' })">
            @csrf
            <button type="submit" class="w-full bg-brand-gold hover:brightness-95 text-brand font-bold px-5 py-3 rounded-xl text-sm">
                {{ __('borrower.apply.group.accept_invite') }}
            </button>
        </form>
        <form method="POST" action="{{ route('site.group-member.reject', $invitation->token) }}" class="flex-1"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.apply.group.decline_confirm_title')), message: @js(__('borrower.apply.group.decline_confirm_message')), confirmLabel: @js(__('borrower.apply.group.decline_invite')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
            @csrf
            <button type="submit" class="w-full bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3 rounded-xl text-sm">
                {{ __('borrower.apply.group.decline_invite') }}
            </button>
        </form>
    </div>
</x-site.guarantor-invite-shell>
