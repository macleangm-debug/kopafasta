<x-site.borrower-layout :title="brand_title(__('borrower.guarantor.onboarding_title'))" active="loans" portal-mode="guarantor">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-emerald-600 mb-1">{{ __('borrower.guarantor.external_label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.guarantor.onboarding_title') }}</h1>
            <p class="text-sm text-gray-500 mt-2">
                {{ __('borrower.guarantor.onboarding_intro', [
                    'borrower' => trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? '')),
                    'product' => $invitation->application?->product?->name ?? __('borrower.guarantor.loan'),
                ]) }}
            </p>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('borrower.guarantor.next_steps') }}</h2>
            <ol class="space-y-3 text-sm text-gray-600 list-decimal list-inside">
                <li>{{ __('borrower.guarantor.step_membership') }}</li>
                <li>{{ __('borrower.guarantor.step_sign') }}</li>
                <li>{{ __('borrower.guarantor.step_agreement') }}</li>
            </ol>
        </div>

        <form method="POST" action="{{ route('site.guarantor.onboarding.complete') }}" class="space-y-6"
              @submit.prevent="
                const sig = $el.elements['signature_data'];
                if (! sig?.value) { alert(@js(__('borrower.guarantor.draw_signature'))); return; }
                window.confirmForm($el, { title: @js(__('borrower.guarantor.confirm_title')), message: @js(__('borrower.guarantor.confirm_message')), confirmLabel: @js(__('borrower.guarantor.confirm_button')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' });
              ">
            @csrf
            <x-site.signature-pad :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))" />
            <label class="flex items-start gap-3 text-sm text-gray-700">
                <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span>{{ __('borrower.guarantor.consent') }}</span>
            </label>
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 rounded-full text-sm">
                {{ __('borrower.guarantor.confirm_button') }}
            </button>
        </form>
    </div>
</x-site.borrower-layout>
