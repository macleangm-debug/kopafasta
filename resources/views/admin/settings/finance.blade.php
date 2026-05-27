<x-admin.layout title="Finance defaults" heading="Finance defaults" subheading="GL accounts used for automatic journal posting">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.finance.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">
            These accounts are used when posting automatic journal entries (e.g. on loan disbursement).
            Charges &amp; Fees can override the income account per-fee via their own <em>GL account</em> field.
        </p>

        @php
            $field = function(string $key, string $label, string $hint = '') use ($values, $accounts) {
                $name = $key;
                $val  = (int) ($values[$key] ?? 0);
                return [$name, $label, $val, $hint];
            };
            $fields = [
                ['cash_gl_account_id',             'Cash / Bank account',          'Credited for net amount paid out on disbursement.'],
                ['loan_receivable_gl_account_id',  'Loan receivable account',      'Debited for the full approved loan principal.'],
                ['fee_income_gl_account_id',       'Default fee income account',   'Used as fallback when a charge has no GL account set.'],
                ['interest_income_gl_account_id',  'Interest income account',      'Credited when interest accrues / is collected.'],
                ['penalty_income_gl_account_id',   'Penalty income account',       'Credited when late-payment penalties are charged.'],
                ['bad_debt_expense_gl_account_id', 'Bad debt expense account',     'Debited when a loan is written off.'],
                ['default_expense_gl_account_id',  'Default expense account',      'Used when an expense has no GL account set.'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($fields as [$key, $label, $hint])
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                    <select name="{{ $key }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— not set —</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected((int)($values[$key] ?? 0) === $a->id)>
                                {{ $a->code }} · {{ $a->name }} ({{ $a->type }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="pt-4 border-t border-gray-100">
            <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 rounded-lg text-sm">
                Save finance defaults
            </button>
            <a href="{{ route('admin.chart-of-accounts.index') }}" class="ml-3 text-sm text-amber-700 hover:underline">Manage chart of accounts →</a>
        </div>
    </form>
</x-admin.layout>
