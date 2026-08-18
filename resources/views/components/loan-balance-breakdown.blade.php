@props(['breakdown', 'recoveryCharges' => null, 'expanded' => false, 'showRecoveryDetail' => true])

@php
    $breakdown = $breakdown ?? [];
    $recoveryCharges = $recoveryCharges ?? ($breakdown['recovery_charges'] ?? null);
    $recoveryTotal = (float) ($recoveryCharges['total'] ?? $breakdown['recovery_costs'] ?? 0);
    $recoveryItems = $recoveryCharges['items'] ?? [];
@endphp

<div x-data="{ open: @json($expanded), recoveryOpen: false }" class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-left hover:bg-gray-50">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-gray-400">How it adds up</p>
            <p class="text-xl font-bold text-gray-900">{{ format_money($breakdown['total_outstanding'] ?? 0) }}</p>
        </div>
        <span class="text-xs font-semibold text-amber-700" x-text="open ? 'Hide breakdown' : 'Show breakdown'"></span>
    </button>
    <div x-show="open" x-cloak class="border-t border-gray-100 px-4 py-3 space-y-3 text-sm">
        <div class="grid sm:grid-cols-2 gap-3">
            <div class="flex justify-between gap-3"><span class="text-gray-500">Principal outstanding</span><span class="font-semibold">{{ format_money($breakdown['principal_outstanding'] ?? 0) }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-gray-500">Interest outstanding</span><span class="font-semibold">{{ format_money($breakdown['interest_outstanding'] ?? 0) }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-gray-500">Penalty outstanding</span><span class="font-semibold">{{ format_money($breakdown['penalty_outstanding'] ?? 0) }}</span></div>
            <div class="flex justify-between gap-3">
                <span class="text-gray-500">Recovery costs</span>
                <span class="font-semibold">{{ format_money($recoveryTotal) }}</span>
            </div>
        </div>

        @if ($showRecoveryDetail && $recoveryTotal > 0 && count($recoveryItems) > 0)
            <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 p-3">
                <button type="button" @click="recoveryOpen = !recoveryOpen" class="w-full flex items-center justify-between text-left">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-amber-700">Recovery charge</p>
                        <p class="text-base font-bold text-amber-900">{{ format_money($recoveryTotal) }}</p>
                    </div>
                    <span class="text-[11px] font-semibold text-amber-800" x-text="recoveryOpen ? 'Hide' : 'Breakdown'"></span>
                </button>
                <div x-show="recoveryOpen" x-cloak class="mt-3 pt-3 border-t border-amber-100 space-y-2 text-xs">
                    @foreach ($recoveryItems as $item)
                        <div class="rounded-md bg-white/80 px-3 py-2">
                            <p class="font-semibold text-gray-900">{{ $item['label'] }}</p>
                            <div class="mt-1 grid sm:grid-cols-2 gap-2 text-gray-600">
                                <div class="flex justify-between gap-2">
                                    <span>{{ __('borrower.loan_servicing.recovery_partner_fee') }}</span>
                                    <span class="font-semibold text-gray-900">{{ format_money($item['partner_amount']) }}</span>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <span>{{ __('borrower.loan_servicing.recovery_company_fee') }}</span>
                                    <span class="font-semibold text-gray-900">{{ format_money($item['company_amount']) }}</span>
                                </div>
                            </div>
                            <div class="mt-1 flex justify-between gap-2 font-semibold text-gray-900">
                                <span>{{ __('borrower.loan_servicing.recovery_line_total') }}</span>
                                <span>{{ format_money($item['total']) }}</span>
                            </div>
                        </div>
                    @endforeach
                    <div class="flex justify-between gap-3 pt-1 font-semibold text-amber-900">
                        <span>{{ __('borrower.loan_servicing.recovery_total') }}</span>
                        <span>{{ format_money($recoveryTotal) }}</span>
                    </div>
                    <p class="text-[10px] text-amber-800/80">{{ __('borrower.loan_servicing.recovery_basis_note') }}</p>
                </div>
            </div>
        @endif

        <div class="flex justify-between gap-3 pt-2 border-t border-gray-100 font-semibold">
            <span>Total outstanding</span><span>{{ format_money($breakdown['total_outstanding'] ?? 0) }}</span>
        </div>
    </div>
</div>
