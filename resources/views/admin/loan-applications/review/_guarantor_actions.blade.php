@php
    $canReview = auth()->user()?->hasPermission('applications.review')
        || auth()->user()?->hasPermission('applications.view');
    $supplementOpen = app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($record);
    $openRequest = app(\App\Services\GuarantorSupplementService::class)->openRequest($record);
@endphp

@if ($canReview)
    <details class="rounded-xl ring-1 ring-slate-200 bg-slate-50/80 overflow-hidden {{ ($compact ?? false) ? '' : 'mb-0' }}">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-between gap-2">
            <span>{{ __('borrower.guarantor_supplement.admin_button') }}</span>
            @if ($supplementOpen)
                <span class="text-xs font-semibold text-amber-700">
                    {{ ($openRequest['kind'] ?? '') === 'change' ? 'Change request open' : 'Request open' }}
                </span>
            @else
                <span class="text-xs font-normal text-gray-500">Keep current · add another</span>
            @endif
        </summary>
        <form method="POST" action="{{ route('admin.loan-applications.request-guarantor-supplement', $record) }}" class="px-4 pb-4 space-y-3 border-t border-slate-200">
            @csrf
            <p class="text-xs text-gray-500 pt-3">Ask the borrower to add another guarantor without removing the current one.</p>
            <label class="block text-xs font-medium text-gray-600">{{ __('borrower.guarantor_supplement.admin_notes') }}</label>
            <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Optional note"></textarea>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2">
                {{ __('borrower.guarantor_supplement.admin_button') }}
            </button>
        </form>
    </details>
@endif
