{{-- Operational expense form only — partner payouts are automated on the payout ledger. --}}
@php
    $r = $record ?? null;
    $categoryService = app(\App\Services\OperationalExpenseCategoryService::class);
    $standards = $categoryService->standards();
    $isCustomExisting = $r && filled($r->category) && ! array_key_exists($r->category, $standards);
    $initialCategory = old('category', $isCustomExisting ? 'other' : ($r?->category ?? ''));
    $initialCustom = old('category_custom', $isCustomExisting ? $categoryService->labelFor((string) $r->category) : '');
@endphp

<div class="md:col-span-2 mb-2 rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3 text-sm text-sky-950">
    <p class="font-semibold">Manual operational expenses</p>
    <p class="mt-1 text-sky-900/80">
        Partner, supplier, and capital payouts are recorded automatically when disbursed or settled (see
        <a href="{{ route('admin.payouts.ledger') }}" class="font-semibold underline">Payout ledger</a>).
        Use this form for rent, payroll, marketing, utilities, and other operating costs that are not automated.
    </p>
</div>

<x-admin.step title="Details">
    <x-admin.select name="branch_id" label="Branch" :options="$branches" :value="$r?->branch_id" placeholder="— None —" />
    <div x-data="{ category: @js($initialCategory) }">
        <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
        <select name="category" x-model="category" required
                class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="">— Select category —</option>
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <div class="mt-3" x-show="category === 'other'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1">New expense type <span class="text-red-500">*</span></label>
            <input type="text" name="category_custom" maxlength="80"
                   value="{{ $initialCustom }}"
                   placeholder="e.g. Staff welfare, Cleaning, Security"
                   class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"
                   :required="category === 'other'">
            <p class="text-[11px] text-gray-500 mt-1">Saved for reuse the next time you add an expense.</p>
        </div>
        @error('category')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('category_custom')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <x-admin.select name="gl_account_id" label="Expense GL account" :options="$accounts" :value="$r?->gl_account_id" placeholder="— Default from category —" />
    <x-admin.select name="status" label="Status" :options="$statuses" :value="$r?->status ?? 'pending'" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
</x-admin.step>

<x-admin.step title="Amount">
    <x-admin.input name="amount" label="Amount" :value="$r?->amount" money required />
    <x-admin.input name="currency" label="Currency" :value="$r?->currency ?? 'TZS'" required />
    <x-admin.input name="expense_date" label="Expense date" :value="optional($r?->expense_date)->format('Y-m-d')" type="date" required />
    <x-admin.select name="payment_method" label="Payment method" :options="$methods" :value="$r?->payment_method" placeholder="— Select —" />
    <x-admin.input name="reference" label="Reference" :value="$r?->reference" />
</x-admin.step>
