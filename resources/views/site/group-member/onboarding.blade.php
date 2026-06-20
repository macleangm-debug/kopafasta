<x-site.borrower-layout :title="brand_title(__('borrower.apply.group.onboarding_title'))" active="dashboard" content-width="wide">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.apply.group.onboarding_label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.apply.group.onboarding_title') }}</h1>
            <p class="text-sm text-gray-500 mt-2">
                {{ __('borrower.apply.group.onboarding_intro', [
                    'leader' => $invitation->leader?->full_name ?? brand_name(),
                    'product' => $invitation->product?->name ?? __('borrower.apply.group.loan_label'),
                ]) }}
            </p>
        </div>

        <form method="POST" action="{{ route('site.group-member.onboarding.complete') }}" class="space-y-6"
              @submit.prevent="
                const sig = $el.elements['signature_data'];
                if (! sig?.value) { alert(@js(__('borrower.apply.group.draw_signature'))); return; }
                window.confirmForm($el, { title: @js(__('borrower.apply.group.confirm_title')), message: @js(__('borrower.apply.group.confirm_message')), confirmLabel: @js(__('borrower.apply.group.confirm_button')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' });
              ">
            @csrf
            <x-site.signature-pad
                :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))"
                :readonly-name="true"
                :verified="true"
            />
            <label class="flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                <span>{{ __('borrower.apply.group.consent') }}</span>
            </label>
            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                {{ __('borrower.apply.group.confirm_button') }}
            </button>
        </form>
    </div>
</x-site.borrower-layout>
