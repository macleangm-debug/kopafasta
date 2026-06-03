@php
    use App\Services\FeeCatalogService;
    $catalog = $postApprovalFeeCatalog ?? app(FeeCatalogService::class)->postApprovalFees();
    $catalogById = $catalog->keyBy('id');

    $existing = collect(old('post_approval_fees', ($postApprovalFees ?? collect())->map(fn ($f) => [
        'charges_fee_id' => $f->charges_fee_id ?? null,
        'code'           => $f->code ?? '',
        'name'           => $f->name ?? '',
        'fee_type'       => $f->fee_type ?? 'fixed',
        'amount'         => $f->amount ?? 0,
        'is_active'      => (bool) ($f->is_active ?? true),
    ])->all()));

    $selectedCatalogIds = $existing->pluck('charges_fee_id')->filter()->map(fn ($id) => (int) $id)->all();
    $catalogPayload = $catalog->map(fn ($f) => [
        'id' => $f->id,
        'code' => $f->code,
        'name' => $f->name,
        'fee_type' => app(FeeCatalogService::class)->feeTypeFromCatalog($f),
        'amount' => (float) $f->amount,
        'label' => app(FeeCatalogService::class)->formatAmountLabel($f),
    ])->values();
@endphp

<x-admin.step title="Post-approval fees" id="post-approval-fees">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-2">
            Select fees from <a href="{{ route('admin.charges-fees.index') }}" class="text-amber-700 font-semibold hover:underline">Fee management (Charges &amp; fees)</a>.
            Define each fee once as <strong>fixed (TZS)</strong> or <strong>percentage</strong> with <em>When = After approval</em>; the system applies them when generating borrower payment lines.
        </p>

        @if ($catalog->isEmpty())
            <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 px-4 py-3 text-sm text-amber-900 mb-4">
                No post-approval fees in the catalog yet.
                <a href="{{ route('admin.charges-fees.create') }}" class="font-semibold underline">Create a fee</a>
                and set <strong>When</strong> to <em>After approval (before disbursement)</em>.
            </div>
        @else
            <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50/80 p-4 mb-4">
                <p class="text-xs font-semibold text-gray-700 mb-3">Available fee types</p>
                <div class="space-y-2">
                    @foreach ($catalog as $fee)
                        @php $checked = in_array((int) $fee->id, $selectedCatalogIds, true); @endphp
                        <label class="flex flex-wrap items-start gap-3 rounded-lg bg-white ring-1 ring-gray-100 px-3 py-2.5 cursor-pointer hover:ring-amber-200">
                            <input type="checkbox"
                                   class="mt-1 size-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                   name="post_approval_catalog[]"
                                   value="{{ $fee->id }}"
                                   @checked($checked)
                                   data-catalog-fee="{{ $fee->id }}">
                            <span class="flex-1 min-w-0">
                                <span class="text-sm font-semibold text-gray-900">{{ $fee->name }}</span>
                                <span class="text-xs text-gray-500 block">{{ $fee->code }} · {{ app(FeeCatalogService::class)->formatAmountLabel($fee) }}</span>
                                @if ($fee->description)
                                    <span class="text-xs text-gray-500 block mt-0.5">{{ $fee->description }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-3" id="post-approval-fee-rows">
            @foreach ($existing as $i => $row)
                @php
                    $linked = $row['charges_fee_id'] ? $catalogById->get((int) $row['charges_fee_id']) : null;
                @endphp
                <div class="post-approval-fee-row rounded-lg bg-gray-50 p-3 ring-1 ring-gray-100" data-row-index="{{ $i }}">
                    @if ($linked)
                        <input type="hidden" name="post_approval_fees[{{ $i }}][charges_fee_id]" value="{{ $linked->id }}">
                        <input type="hidden" name="post_approval_fees[{{ $i }}][code]" value="{{ $linked->code }}">
                        <input type="hidden" name="post_approval_fees[{{ $i }}][name]" value="{{ $linked->name }}">
                        <input type="hidden" name="post_approval_fees[{{ $i }}][fee_type]" value="{{ $row['fee_type'] }}">
                        <input type="hidden" name="post_approval_fees[{{ $i }}][amount]" value="{{ $row['amount'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $linked->name }}</p>
                                <p class="text-xs text-gray-500">{{ $linked->code }} · {{ app(FeeCatalogService::class)->formatAmountLabel($linked) }}</p>
                            </div>
                            <x-admin.select :name="'post_approval_fees['.$i.'][is_active]'" label="Active"
                                            :options="['1' => 'Yes', '0' => 'No']"
                                            :value="($row['is_active'] ?? true) ? '1' : '0'" />
                        </div>
                    @else
                        <div class="grid md:grid-cols-5 gap-3">
                            <x-admin.input :name="'post_approval_fees['.$i.'][code]'" label="Code" :value="$row['code']" placeholder="CUSTOM_FEE" />
                            <x-admin.input :name="'post_approval_fees['.$i.'][name]'" label="Name" :value="$row['name']" />
                            <x-admin.select :name="'post_approval_fees['.$i.'][fee_type]'" label="Type" :options="['fixed' => 'Fixed', 'percent' => '% of principal']" :value="$row['fee_type']" />
                            <x-admin.money-input :name="'post_approval_fees['.$i.'][amount]'" label="Amount" :value="$row['amount']"
                                                 :help="($row['fee_type'] ?? 'fixed') === 'percent' ? 'Percent without % sign' : 'TZS amount'" />
                            <x-admin.select :name="'post_approval_fees['.$i.'][is_active]'" label="Active" :options="['1' => 'Yes', '0' => 'No']" :value="($row['is_active'] ?? true) ? '1' : '0'" />
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Legacy custom row — prefer catalog fees above.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin.step>

@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const catalog = @json($catalogPayload);

            const host = document.getElementById('post-approval-fee-rows');
            let rowIndex = host ? host.querySelectorAll('.post-approval-fee-row').length : 0;

            function syncCatalogRows() {
                if (!host) return;
                const selected = [...document.querySelectorAll('input[name="post_approval_catalog[]"]:checked')]
                    .map((el) => parseInt(el.value, 10));

                host.querySelectorAll('.post-approval-fee-row[data-catalog-id]').forEach((row) => row.remove());

                selected.forEach((id) => {
                    const fee = catalog.find((f) => f.id === id);
                    if (!fee) return;
                    const i = rowIndex++;
                    const html = `
                        <div class="post-approval-fee-row rounded-lg bg-gray-50 p-3 ring-1 ring-gray-100" data-catalog-id="${id}" data-row-index="${i}">
                            <input type="hidden" name="post_approval_fees[${i}][charges_fee_id]" value="${id}">
                            <input type="hidden" name="post_approval_fees[${i}][code]" value="${fee.code}">
                            <input type="hidden" name="post_approval_fees[${i}][name]" value="${fee.name}">
                            <input type="hidden" name="post_approval_fees[${i}][fee_type]" value="${fee.fee_type}">
                            <input type="hidden" name="post_approval_fees[${i}][amount]" value="${fee.amount}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">${fee.name}</p>
                                    <p class="text-xs text-gray-500">${fee.code} · ${fee.label}</p>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-600">Active</label>
                                    <select name="post_approval_fees[${i}][is_active]" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                        <option value="1" selected>Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                        </div>`;
                    host.insertAdjacentHTML('beforeend', html);
                });
            }

            document.querySelectorAll('input[name="post_approval_catalog[]"]').forEach((cb) => {
                cb.addEventListener('change', syncCatalogRows);
            });
        });
    </script>
    @endpush
@endonce
