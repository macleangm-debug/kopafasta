<x-site.borrower-layout :title="brand_title(__('borrower.apply.group.contract_page_title'))" active="dashboard" content-width="wide">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.apply.group.contract_label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.apply.group.contract_page_title') }}</h1>
            <p class="text-sm text-gray-500 mt-2">
                {{ __('borrower.apply.group.contract_intro', [
                    'reference' => $application->application_number,
                    'leader' => $application->customer?->full_name ?? brand_name(),
                ]) }}
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('borrower.apply.group.contract_allocation') }}</h2>
            <p class="text-2xl font-bold text-gray-900">{{ format_money($member->requested_amount ?? 0) }}</p>
            @if (! empty($snap['group_name']))
                <p class="text-sm text-gray-500 mt-2">{{ __('borrower.apply.group_setup.name') }}: {{ $snap['group_name'] }}</p>
            @endif
        </div>

        @if ($progress)
            <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-4 mb-6 text-sm space-y-1">
                @foreach ($progress['summary'] as $line)
                    <p class="font-medium text-gray-800">{{ $line }}</p>
                @endforeach
            </div>
        @endif

        @if ($signed)
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-5 text-sm text-emerald-900">
                {{ __('borrower.apply.group.contract_already_signed', ['date' => optional($member->contract_signed_at)->format('d M Y H:i')]) }}
            </div>
        @elseif ($declined)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-5 text-sm text-amber-900">
                {{ __('borrower.apply.group.contract_you_declined') }}
            </div>
        @else
            <form method="POST" action="{{ route('site.borrower.group-contract.sign', $application) }}" class="space-y-6"
                  @submit.prevent="
                    const sig = $el.elements['signature_data'];
                    if (! sig?.value) { alert(@js(__('borrower.apply.group.draw_signature'))); return; }
                    window.confirmForm($el, { title: @js(__('borrower.apply.group.contract_confirm_title')), message: @js(__('borrower.apply.group.contract_confirm_message')), confirmLabel: @js(__('borrower.apply.group.contract_sign_cta')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' });
                  ">
                @csrf
                <x-site.signature-pad
                    :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))"
                    :readonly-name="true"
                    :verified="true"
                />
                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <span>{{ __('borrower.apply.group.contract_consent') }}</span>
                </label>
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    {{ __('borrower.apply.group.contract_sign_cta') }}
                </button>
            </form>

            <form method="POST" action="{{ route('site.borrower.group-contract.decline', $application) }}" class="mt-4"
                  onsubmit="return confirm(@js(__('borrower.apply.group.contract_decline_confirm')))">
                @csrf
                <button type="submit" class="text-sm text-red-700 underline">{{ __('borrower.apply.group.contract_decline_cta') }}</button>
            </form>
        @endif
    </div>
</x-site.borrower-layout>
