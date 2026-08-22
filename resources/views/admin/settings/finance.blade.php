<x-admin.layout title="Finance defaults" heading="Finance defaults" subheading="GL accounts used for automatic journal posting">
    @include('admin.settings._tabs', ['active' => 'finance'])

    <x-admin.settings-editor
        action="{{ route('admin.settings.finance.save') }}"
        submit-label="Save finance defaults"
        :tabs="[
            'controls' => 'Controls',
            'accounts' => 'GL accounts',
        ]"
    >
        <x-admin.settings-panel id="controls">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
                <p class="text-sm text-gray-600">
                    These accounts are used when posting automatic journal entries (e.g. on loan disbursement).
                    Disbursement moves cash to loan receivable only — fee income is not recognized until earned.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Capital partner interest share (%)</label>
                        <input type="number" name="capital_partner_interest_share_percent" min="0" max="100" step="0.01"
                               value="{{ $values['capital_partner_interest_share_percent'] ?? 60 }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Partner share of interest collected on repayments. Company receives the remainder.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Capital allocation strategy</label>
                        @php $strategy = $values['capital_allocation_strategy'] ?? 'proportional'; @endphp
                        <select name="capital_allocation_strategy" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="proportional" @selected($strategy === 'proportional')>Proportional (by available pool balance)</option>
                            <option value="round_robin" @selected($strategy === 'round_robin')>Round robin (one partner per loan when possible)</option>
                            <option value="priority" @selected($strategy === 'priority')>Priority waterfall (by lender priority field)</option>
                            <option value="manual" @selected($strategy === 'manual')>Manual (block automatic allocation)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Used when disbursing loans funded by capital partners.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Write-off approval required</label>
                        <input type="hidden" name="write_off_approval_required" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800 mt-2">
                            <input type="checkbox" name="write_off_approval_required" value="1"
                                   @checked(array_key_exists('write_off_approval_required', $values) ? ! empty($values['write_off_approval_required']) : true)
                                   class="rounded border-gray-300 text-brand">
                            Require manager and finance approval before write-off
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Repayment maker-checker</label>
                        <input type="hidden" name="repayment_approval_required" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800 mt-2">
                            <input type="checkbox" name="repayment_approval_required" value="1"
                                   @checked(! empty($values['repayment_approval_required']))
                                   class="rounded border-gray-300 text-brand">
                            Require supervisor approval before admin-recorded repayments post to ledger
                        </label>
                        <p class="text-xs text-gray-500 mt-1">When enabled, the recorder and approver must be different users.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Collections security</label>
                        <input type="hidden" name="collections_gateway_only" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800 mt-2">
                            <input type="checkbox" name="collections_gateway_only" value="1"
                                   @checked(! empty($values['collections_gateway_only']))
                                   class="rounded border-gray-300 text-brand">
                            Gateway-only repayments (disable manual admin recording)
                        </label>
                        <p class="text-xs text-gray-500 mt-1">When enabled, repayments must arrive via borrower/gateway channels — staff cannot type cash payments into the ledger.</p>
                    </div>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="accounts">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
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
                        ['recovery_partner_payable_gl_account_id',  'Recovery partner payable',         'Partner cost accrual (recovery, valuation, GPS, insurance) until payout.'],
                        ['supplier_payable_gl_account_id',          'Asset supplier payable',           'Principal repayments owed to managed-loan suppliers.'],
                        ['asset_lending_principal_clearing_gl_account_id', 'Asset lending principal clearing', 'Debit when principal is allocated to supplier payable (defaults to loan receivable).'],
                        ['valuation_revenue_gl_account_id',         'Valuation revenue',                'Company markup on valuation fees (not the valuer’s base cost).'],
                        ['gps_revenue_gl_account_id',               'GPS revenue',                      'Company markup on GPS device and monitoring fees.'],
                        ['asset_lending_revenue_gl_account_id',     'Asset lending revenue',            'Deposit markup and asset-lending fees (not interest income).'],
                        ['capital_partner_pool_gl_account_id',      'Capital partner pool account',     'Credited instead of cash when a loan is funded from partner pools.'],
                        ['registration_fee_income_gl_account_id',   'Membership fee income',          'Credited when membership fees are verified.'],
                        ['application_fee_income_gl_account_id',    'Application fee income',           'Credited when application fees are verified.'],
                        ['fee_income_gl_account_id',                'Default fee income account',       'Fallback when a specific fee income account is not set.'],
                        ['interest_income_gl_account_id',           'Interest income account',          'Credited when interest accrues / is collected.'],
                        ['penalty_income_gl_account_id',            'Penalty income account',           'Credited when late-payment penalties are charged.'],
                        ['bad_debt_expense_gl_account_id',          'Bad debt expense account',         'Debited when a loan is written off.'],
                        ['default_expense_gl_account_id',           'Default expense account',          'Used when an expense has no GL account set.'],
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
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>

    <div class="mt-4">
        <a href="{{ route('admin.chart-of-accounts.index') }}" class="text-sm text-brand hover:underline">Manage chart of accounts →</a>
    </div>
</x-admin.layout>
