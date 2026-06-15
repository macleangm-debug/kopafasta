<x-admin.layout title="Finance defaults" heading="Finance defaults" subheading="GL accounts used for automatic journal posting">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.finance.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">
            These accounts are used when posting automatic journal entries (e.g. on loan disbursement).
            Disbursement moves cash to loan receivable only — fee income is not recognized until earned.
        </p>

        @php
            $field = function(string $key, string $label, string $hint = '') use ($values, $accounts) {
                $name = $key;
                $val  = (int) ($values[$key] ?? 0);
                return [$name, $label, $val, $hint];
            };
            $fields = [
                ['cash_gl_account_id',                      'Cash / Bank account',              'Credited for net amount paid out on disbursement.'],
                ['customer_gl_account_id',                  'Customer account',                 'Debited when borrower payments are collected.'],
                ['loan_receivable_gl_account_id',           'Loan receivable account',          'Debited on disbursement when loan exposure begins.'],
                ['deferred_fee_liability_gl_account_id',    'Deferred fee liability',           'Credited when fees are withheld at disbursement (not income yet).'],
                ['borrower_refunds_payable_gl_account_id',  'Borrower refunds payable',         'Credited when auction surplus is owed to borrowers; debited on payout.'],
                ['recovery_revenue_gl_account_id',          'Recovery revenue',                 'Company markup on recovery/repossession charges (not interest or penalty).'],
                ['recovery_partner_payable_gl_account_id',  'Recovery partner payable',         'Partner cost accrual until payout.'],
                ['valuation_revenue_gl_account_id',         'Valuation revenue',                'Company markup on valuation fees.'],
                ['gps_revenue_gl_account_id',               'GPS revenue',                      'Company markup on GPS device and monitoring fees.'],
                ['capital_partner_pool_gl_account_id',      'Capital partner pool account',     'Credited instead of cash when a loan is funded from partner pools.'],
                ['registration_fee_income_gl_account_id',   'Registration fee income',          'Credited when registration fees are verified.'],
                ['application_fee_income_gl_account_id',    'Application fee income',           'Credited when application fees are verified.'],
                ['fee_income_gl_account_id',                'Default fee income account',       'Fallback when a specific fee income account is not set.'],
                ['interest_income_gl_account_id',           'Interest income account',          'Credited when interest accrues / is collected.'],
                ['penalty_income_gl_account_id',            'Penalty income account',           'Credited when late-payment penalties are charged.'],
                ['bad_debt_expense_gl_account_id',          'Bad debt expense account',         'Debited when a loan is written off.'],
                ['default_expense_gl_account_id',           'Default expense account',          'Used when an expense has no GL account set.'],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Capital partner interest share (%)</label>
                <input type="number" name="capital_partner_interest_share_percent" min="0" max="100" step="0.01"
                       value="{{ $values['capital_partner_interest_share_percent'] ?? 60 }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-gray-500 mt-1">Partner share of interest collected on repayments. Company receives the remainder.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Write-off approval required</label>
                <input type="hidden" name="write_off_approval_required" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-gray-800 mt-2">
                    <input type="checkbox" name="write_off_approval_required" value="1"
                           @checked(array_key_exists('write_off_approval_required', $values) ? ! empty($values['write_off_approval_required']) : true)
                           class="rounded border-gray-300 text-amber-600">
                    Require manager and finance approval before write-off
                </label>
            </div>
        </div>

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
