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
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-6 sm:px-6 sm:py-7 mb-6 shadow-[0_20px_50px_rgba(0,77,64,0.22)]">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 12% 20%, #fbbf24 0, transparent 42%), radial-gradient(circle at 90% 0%, #fff 0, transparent 36%);"></div>
        <div class="relative">
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-brand-gold">{{ brand_name() }}</p>
            <h1 class="mt-2 text-2xl sm:text-[1.65rem] font-extrabold tracking-tight leading-snug">{{ __('borrower.apply.group.invite_subject') }}</h1>
            <p class="mt-3 text-sm text-white/80 leading-relaxed">
                {{ __('borrower.apply.group.invite_intro', ['leader' => $leaderName, 'name' => $inviteeName]) }}
            </p>
        </div>
    </div>

    <div class="rounded-3xl ring-1 ring-brand/12 bg-gradient-to-b from-brand-muted/35 to-white overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-brand/10">
            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-brand">{{ __('borrower.apply.group.loan_label') }}</p>
        </div>
        <dl class="divide-y divide-brand/10 text-sm">
            @if ($invitation->group_name)
                <div class="px-5 py-3.5 flex justify-between gap-4">
                    <dt class="text-gray-500 shrink-0">{{ __('borrower.apply.group_setup.name') }}</dt>
                    <dd class="font-semibold text-right text-gray-900">{{ $invitation->group_name }}</dd>
                </div>
            @endif
            @if ($invitation->draft_reference)
                <div class="px-5 py-3.5 flex justify-between gap-4">
                    <dt class="text-gray-500 shrink-0">{{ __('borrower.apply.group.reference') }}</dt>
                    <dd class="font-mono font-semibold text-right text-gray-900">{{ $invitation->draft_reference }}</dd>
                </div>
            @endif
            @if ($invitation->product)
                <div class="px-5 py-3.5 flex justify-between gap-4">
                    <dt class="text-gray-500 shrink-0">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                    <dd class="font-semibold text-right text-gray-900">{{ $invitation->product->name }}</dd>
                </div>
            @endif
            @if ($invitation->amount_per_member)
                <div class="px-5 py-3.5 flex justify-between gap-4">
                    <dt class="text-gray-500 shrink-0">{{ __('borrower.apply.group_setup.amount_per_member') }}</dt>
                    <dd class="font-extrabold tabular-nums text-right text-brand">{{ format_money((float) $invitation->amount_per_member) }}</dd>
                </div>
            @endif
            @if ($invitation->requested_tenure_months)
                <div class="px-5 py-3.5 flex justify-between gap-4">
                    <dt class="text-gray-500 shrink-0">{{ __('borrower.apply.group_setup.tenure') }}</dt>
                    <dd class="font-semibold text-right text-gray-900">{{ $invitation->requested_tenure_months }} {{ __('borrower.apply.quote.months') }}</dd>
                </div>
            @endif
            <div class="px-5 py-3.5 flex justify-between gap-4 bg-white/60">
                <dt class="text-gray-500 shrink-0">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
                <dd class="font-semibold text-right text-brand">{{ $leaderName }}</dd>
            </div>
        </dl>
    </div>

    @if ($invitation->invitation_reason)
        <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3.5 text-sm mb-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1.5">{{ __('borrower.apply.group.invitation_reason') }}</p>
            <p class="text-gray-800 leading-relaxed">{{ $invitation->invitation_reason }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <form method="POST" action="{{ route('site.group-member.accept', $invitation->token) }}"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.apply.group.accept_confirm_title')), message: @js(__('borrower.apply.group.accept_confirm_message')), confirmLabel: @js(__('borrower.apply.group.accept_invite')), confirmClass: 'bg-brand-gold hover:brightness-95 text-brand' })">
            @csrf
            <button type="submit" class="w-full bg-brand-gold hover:brightness-95 text-brand font-extrabold px-5 py-3.5 rounded-2xl text-sm shadow-sm shadow-brand-gold/30">
                {{ __('borrower.apply.group.accept_invite') }}
            </button>
        </form>
        <form method="POST" action="{{ route('site.group-member.reject', $invitation->token) }}"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.apply.group.decline_confirm_title')), message: @js(__('borrower.apply.group.decline_confirm_message')), confirmLabel: @js(__('borrower.apply.group.decline_invite')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
            @csrf
            <button type="submit" class="w-full bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3.5 rounded-2xl text-sm">
                {{ __('borrower.apply.group.decline_invite') }}
            </button>
        </form>
    </div>
</x-site.guarantor-invite-shell>
