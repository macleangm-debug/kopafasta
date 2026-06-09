<x-site.borrower-layout :title="brand_title('Application received')" active="loans">
    <div class="max-w-xl mx-auto text-center py-6">
        <div class="size-16 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center mx-auto mb-5">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-3xl font-bold tracking-tight animate-[fadeIn_0.5s_ease-out]">{{ __('borrower.apply.success.submitted_title') }}</h1>
        <p class="mt-2 text-gray-600">Reference <span class="font-mono font-bold text-gray-900">{{ $application->application_number }}</span></p>

        @if ($guarantorInvitation ?? null)
            <p class="mt-3 text-sm text-amber-800">{{ __('borrower.apply.success.submitted_guarantor_pending_message') }}</p>
        @endif

        @if ($guarantorInvitation ?? null)
            <div class="mt-6 bg-emerald-50 rounded-2xl border border-emerald-200 p-6 text-left" x-data="{ copied: false }">
                <p class="text-sm font-semibold text-emerald-900 mb-2">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                <p class="text-xs text-emerald-800 mb-4">{{ __('borrower.apply.guarantor_fields.share_ready') }}</p>
                <div class="flex flex-wrap gap-2">
                    @if ($guarantorShareUrl ?? null)
                        <a href="{{ $guarantorShareUrl }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-full text-sm">
                            {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                        </a>
                    @endif
                    @if ($guarantorSmsUrl ?? null)
                        <a href="{{ $guarantorSmsUrl }}"
                           class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                            {{ __('borrower.apply.guarantor_fields.share_sms') }}
                        </a>
                    @endif
                    @if ($guarantorEmailUrl ?? null)
                        <a href="{{ $guarantorEmailUrl }}"
                           class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                            {{ __('borrower.apply.guarantor_fields.share_email') }}
                        </a>
                    @endif
                    @if ($guarantorInvitationUrl ?? null)
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($guarantorInvitationUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex items-center gap-2 bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-sm">
                            <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                        </button>
                    @endif
                </div>
            </div>
        @endif

        <div class="mt-8 bg-white rounded-2xl border border-gray-200 p-6 text-left">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-4">{{ __('borrower.loan_profile.application_progress') }}</p>
            <ol class="space-y-3">
                @foreach ($underwritingStages as $stage)
                    @php
                        $dotClass = match ($stage['status']) {
                            'done'   => 'bg-emerald-500',
                            'active' => 'bg-amber-500 ring-4 ring-amber-100',
                            default  => 'bg-gray-200',
                        };
                        $textClass = match ($stage['status']) {
                            'done'   => 'text-emerald-800',
                            'active' => 'text-amber-900 font-semibold',
                            default  => 'text-gray-500',
                        };
                    @endphp
                    <li class="flex items-center gap-3">
                        <span class="size-3 rounded-full shrink-0 {{ $dotClass }}"></span>
                        <span class="text-sm {{ $textClass }}">{{ $stage['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-6 text-left">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="text-gray-500">Product</div><div class="font-medium">{{ $application->product->name }}</div>
                <div class="text-gray-500">Amount</div><div class="font-medium">{{ format_money($application->requested_amount, true, 0) }}</div>
                <div class="text-gray-500">Tenure</div><div class="font-medium">{{ $application->requested_tenure_months }} months</div>
            </div>
        </div>

        <div class="mt-8 flex gap-3 justify-center flex-wrap">
            <a href="{{ route('site.borrower.application', $application->id) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full">Upload documents →</a>
            <a href="{{ route('site.borrower.dashboard') }}" class="border border-gray-300 text-gray-900 font-semibold px-5 py-2.5 rounded-full">Dashboard</a>
        </div>
    </div>
</x-site.borrower-layout>
