@if ($needsManualCapitalAllocation ?? false)
    @php
        $principal = (float) $loan->principal_amount;
        $preferredId = $loan->application?->preferred_lender_id;
        $oldRows = old('allocations', [['lender_id' => $preferredId, 'amount' => $principal]]);
    @endphp
    <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-amber-200 p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-1">Manual capital allocation</h3>
        <p class="text-xs text-gray-500 mb-4">
            Finance settings require manual partner assignment before disbursement.
            Allocations must total {{ format_money($principal) }}.
            @if ($preferredId)
                Preferred partner from approval is pre-selected.
            @endif
        </p>

        @if ($errors->has('allocations') || $errors->has('capital'))
            <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                {{ $errors->first('allocations') ?: $errors->first('capital') }}
            </div>
        @endif

        @if (($capitalPartnerOptions ?? collect())->isEmpty())
            <p class="text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-4 py-3">
                No external capital partners have available pool funds.
                <a href="{{ route('admin.lenders.index') }}" class="font-semibold underline">Manage partners →</a>
            </p>
        @else
            <form method="POST" action="{{ route('admin.loans.allocate-capital', $loan) }}" id="manual-capital-form" class="space-y-4">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="manual-capital-rows">
                        <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="text-left py-2 pr-4">Partner</th>
                                <th class="text-right py-2 pr-4">Available</th>
                                <th class="text-right py-2">Amount (TZS)</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($oldRows as $index => $row)
                                <tr class="allocation-row">
                                    <td class="py-2 pr-4">
                                        <select name="allocations[{{ $index }}][lender_id]" required
                                                class="w-full rounded-lg border-gray-300 text-sm allocation-lender">
                                            <option value="">— Select partner —</option>
                                            @foreach ($capitalPartnerOptions as $option)
                                                <option value="{{ $option['lender_id'] }}"
                                                        data-available="{{ $option['available'] }}"
                                                        @selected((int) ($row['lender_id'] ?? 0) === (int) $option['lender_id'])>
                                                    {{ $option['lender_name'] }} · {{ $option['pool_name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error("allocations.{$index}.lender_id")
                                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="py-2 pr-4 text-right font-mono text-xs text-gray-600 allocation-available">—</td>
                                    <td class="py-2 pr-4">
                                        <input type="number" name="allocations[{{ $index }}][amount]"
                                               value="{{ $row['amount'] ?? '' }}"
                                               min="0" step="1" required
                                               class="w-full rounded-lg border-gray-300 text-sm text-right allocation-amount">
                                        @error("allocations.{$index}.amount")
                                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="py-2 text-center">
                                        @if ($index > 0)
                                            <button type="button" class="text-red-600 hover:text-red-800 text-xs font-semibold remove-row">Remove</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="add-allocation-row"
                            class="text-sm font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg ring-1 ring-amber-200">
                        + Add partner
                    </button>
                    <p class="text-sm text-gray-600 ml-auto">
                        Total: <span id="allocation-total" class="font-semibold tabular-nums">0</span>
                        / {{ format_money($principal, false) }}
                    </p>
                    <button type="submit"
                            class="inline-flex text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                        Save allocation
                    </button>
                </div>
            </form>

            <template id="allocation-row-template">
                <tr class="allocation-row">
                    <td class="py-2 pr-4">
                        <select name="allocations[__INDEX__][lender_id]" required
                                class="w-full rounded-lg border-gray-300 text-sm allocation-lender">
                            <option value="">— Select partner —</option>
                            @foreach ($capitalPartnerOptions as $option)
                                <option value="{{ $option['lender_id'] }}" data-available="{{ $option['available'] }}">
                                    {{ $option['lender_name'] }} · {{ $option['pool_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td class="py-2 pr-4 text-right font-mono text-xs text-gray-600 allocation-available">—</td>
                    <td class="py-2 pr-4">
                        <input type="number" name="allocations[__INDEX__][amount]" min="0" step="1" required
                               class="w-full rounded-lg border-gray-300 text-sm text-right allocation-amount">
                    </td>
                    <td class="py-2 text-center">
                        <button type="button" class="text-red-600 hover:text-red-800 text-xs font-semibold remove-row">Remove</button>
                    </td>
                </tr>
            </template>

            <script>
                (function () {
                    const tbody = document.querySelector('#manual-capital-rows tbody');
                    const template = document.getElementById('allocation-row-template');
                    const totalEl = document.getElementById('allocation-total');
                    const target = {{ json_encode($principal) }};

                    function formatNum(n) {
                        return new Intl.NumberFormat('en-TZ', { maximumFractionDigits: 0 }).format(n);
                    }

                    function refreshAvailable(select) {
                        const row = select.closest('tr');
                        const cell = row.querySelector('.allocation-available');
                        const opt = select.options[select.selectedIndex];
                        const avail = opt?.dataset?.available;
                        cell.textContent = avail ? formatNum(parseFloat(avail)) : '—';
                    }

                    function refreshTotal() {
                        let sum = 0;
                        tbody.querySelectorAll('.allocation-amount').forEach(function (input) {
                            sum += parseFloat(input.value || 0);
                        });
                        totalEl.textContent = formatNum(sum);
                        totalEl.classList.toggle('text-emerald-700', Math.abs(sum - target) < 0.01);
                        totalEl.classList.toggle('text-amber-700', Math.abs(sum - target) >= 0.01);
                    }

                    function bindRow(row) {
                        const select = row.querySelector('.allocation-lender');
                        const amount = row.querySelector('.allocation-amount');
                        select?.addEventListener('change', function () { refreshAvailable(select); });
                        amount?.addEventListener('input', refreshTotal);
                        row.querySelector('.remove-row')?.addEventListener('click', function () {
                            row.remove();
                            reindexRows();
                            refreshTotal();
                        });
                        if (select) refreshAvailable(select);
                    }

                    function reindexRows() {
                        tbody.querySelectorAll('.allocation-row').forEach(function (row, index) {
                            row.querySelectorAll('[name^="allocations["]').forEach(function (el) {
                                el.name = el.name.replace(/allocations\[\d+\]/, 'allocations[' + index + ']');
                            });
                        });
                    }

                    document.getElementById('add-allocation-row')?.addEventListener('click', function () {
                        const index = tbody.querySelectorAll('.allocation-row').length;
                        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
                        tbody.insertAdjacentHTML('beforeend', html);
                        bindRow(tbody.lastElementChild);
                        refreshTotal();
                    });

                    tbody.querySelectorAll('.allocation-row').forEach(bindRow);
                    refreshTotal();
                })();
            </script>
        @endif
    </div>
@endif

@if ($loan->status === 'pending' && $loan->capitalAllocations->isNotEmpty())
    <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">Capital allocation (pending disbursement)</h3>
                <p class="text-xs text-gray-500">Assigned partners — disburse will use this split.</p>
            </div>
            @if (app(\App\Services\CapitalPartnerAllocationService::class)->allocationStrategy() === 'manual')
                <form method="POST" action="{{ route('admin.loans.clear-capital-allocation', $loan) }}"
                      onsubmit="return confirm('Clear capital allocation and release partner funds?');">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg ring-1 ring-red-200">
                        Clear & re-assign
                    </button>
                </form>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-2 pr-4">Partner</th>
                        <th class="text-right py-2 pr-4">Allocated</th>
                        <th class="text-right py-2">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($loan->capitalAllocations as $allocation)
                        <tr>
                            <td class="py-2 pr-4">{{ $allocation->lender?->name ?? 'Partner #'.$allocation->lender_id }}</td>
                            <td class="py-2 pr-4 text-right font-mono">{{ format_money($allocation->allocated_principal) }}</td>
                            <td class="py-2 text-right font-mono">{{ format_number($allocation->allocation_percent, 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
