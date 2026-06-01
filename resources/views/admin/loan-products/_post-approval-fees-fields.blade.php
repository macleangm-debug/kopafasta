@php
    $existing = collect(old('post_approval_fees', ($postApprovalFees ?? collect())->map(fn ($f) => [
        'code'      => $f->code ?? '',
        'name'      => $f->name ?? '',
        'fee_type'  => $f->fee_type ?? 'fixed',
        'amount'    => $f->amount ?? 0,
        'is_active' => (bool) ($f->is_active ?? true),
    ])->all()));

    if ($existing->isEmpty()) {
        $existing = collect([]);
    }
@endphp

<x-admin.step title="Post-approval fees" id="post-approval-fees">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-4">Fees due after approval and signatures, before disbursement prep. Use fixed amount or percent of approved principal.</p>
        <div class="space-y-3" id="post-approval-fee-rows">
            @foreach ($existing as $i => $row)
                <div class="grid md:grid-cols-5 gap-3 rounded-lg bg-gray-50 p-3">
                    <x-admin.input :name="'post_approval_fees['.$i.'][code]'" label="Code" :value="$row['code']" placeholder="DISB_FEE" />
                    <x-admin.input :name="'post_approval_fees['.$i.'][name]'" label="Name" :value="$row['name']" />
                    <x-admin.select :name="'post_approval_fees['.$i.'][fee_type]'" label="Type" :options="['fixed' => 'Fixed', 'percent' => '% of principal']" :value="$row['fee_type']" />
                    <x-admin.input :name="'post_approval_fees['.$i.'][amount]'" label="Amount" type="number" step="0.01" :value="$row['amount']" />
                    <x-admin.select :name="'post_approval_fees['.$i.'][is_active]'" label="Active" :options="['1' => 'Yes', '0' => 'No']" :value="($row['is_active'] ?? true) ? '1' : '0'" />
                </div>
            @endforeach
        </div>
        <button type="button" class="mt-3 text-xs font-semibold text-amber-700" onclick="addPostApprovalFeeRow()">+ Add fee</button>
    </div>
</x-admin.step>

@once
    @push('scripts')
    <script>
        let postApprovalFeeIndex = {{ $existing->count() }};
        function addPostApprovalFeeRow() {
            const host = document.getElementById('post-approval-fee-rows');
            const i = postApprovalFeeIndex++;
            host.insertAdjacentHTML('beforeend', `
                <div class="grid md:grid-cols-5 gap-3 rounded-lg bg-gray-50 p-3">
                    <div><label class="text-xs font-medium text-gray-600">Code</label><input name="post_approval_fees[${i}][code]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="text-xs font-medium text-gray-600">Name</label><input name="post_approval_fees[${i}][name]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="text-xs font-medium text-gray-600">Type</label><select name="post_approval_fees[${i}][fee_type]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="fixed">Fixed</option><option value="percent">% of principal</option></select></div>
                    <div><label class="text-xs font-medium text-gray-600">Amount</label><input type="number" step="0.01" name="post_approval_fees[${i}][amount]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="text-xs font-medium text-gray-600">Active</label><select name="post_approval_fees[${i}][is_active]" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="1">Yes</option><option value="0">No</option></select></div>
                </div>`);
        }
    </script>
    @endpush
@endonce
