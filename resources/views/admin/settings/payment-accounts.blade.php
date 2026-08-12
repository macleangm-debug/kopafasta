<x-admin.layout
    title="Payment Accounts"
    heading="Payment Accounts"
    subheading="Map payment types and methods to bank or mobile money collection accounts">
    @include('admin.settings._tabs', ['active' => 'payment-accounts'])

<div class="mb-6 rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 px-5 py-4 text-sm text-gray-800 space-y-2">
        <p class="font-semibold text-brand">Collection channels (bank + mobile money)</p>
        <p class="text-xs text-gray-600">Borrowers repay via bank transfer or mobile money only. Create the PSP / collection mobile account once, then map it here. You do not need separate repayment-method catalogs.</p>
        <div class="flex flex-wrap gap-3 pt-1">
            <a href="{{ route('admin.mobile-money-accounts.create') }}" class="inline-flex text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-2 rounded-xl">+ Add PSP / collection account</a>
            <a href="{{ route('admin.bank-accounts.create') }}" class="inline-flex text-xs font-semibold text-brand ring-1 ring-brand/20 hover:bg-brand-muted px-3 py-2 rounded-xl">+ Add bank account</a>
            <a href="{{ route('admin.mobile-money-accounts.index') }}" class="inline-flex text-xs font-semibold text-brand hover:underline px-2 py-2">View mobile accounts →</a>
            <a href="{{ route('admin.bank-accounts.index') }}" class="inline-flex text-xs font-semibold text-brand hover:underline px-2 py-2">View bank accounts →</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.payment-accounts.default-collection') }}" class="bg-white rounded-2xl ring-1 ring-brand/10 p-5 mb-8 space-y-4">
        @csrf @method('PUT')

        <div>
            <h2 class="text-sm font-semibold text-brand">PSP collection account</h2>
            <p class="text-xs text-gray-500 mt-1">
                One aggregation paybill/till for all mobile money collections. If the dropdown is empty, add a collection account first (button above).
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Default mobile money account</label>
                <select name="default_collection_mobile_money_account_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">— Not set —</option>
                    @foreach ($mobileAccounts as $account)
                        <option value="{{ $account->id }}" @selected($defaultCollectionId === $account->id)>
                            {{ $account->name }} · {{ $account->provider }}
                            @if ($account->paybill_number) (Paybill {{ $account->paybill_number }})@elseif ($account->till_number) (Till {{ $account->till_number }})@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2.5">
                <input type="hidden" name="apply_to_all_mappings" value="0">
                <input type="checkbox" name="apply_to_all_mappings" value="1" class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">Apply to all mobile money mappings</span>
            </label>
        </div>

        @if ($defaultCollection)
            <p class="text-xs text-emerald-800 bg-emerald-50 ring-1 ring-emerald-100 rounded-lg px-3 py-2">
                Active: <strong>{{ $defaultCollection->name }}</strong>
                @if ($defaultCollection->paybill_number)
                    · Paybill <span class="font-mono">{{ $defaultCollection->paybill_number }}</span>
                @elseif ($defaultCollection->till_number)
                    · Till <span class="font-mono">{{ $defaultCollection->till_number }}</span>
                @endif
            </p>
        @endif

        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2.5 rounded-xl">
            Save PSP collection account
        </button>
    </form>

    <form method="POST" action="{{ route('admin.settings.payment-accounts.default-collection-bank') }}" class="bg-white rounded-2xl ring-1 ring-brand/10 p-5 mb-8 space-y-4">
        @csrf @method('PUT')

        <div>
            <h2 class="text-sm font-semibold text-brand">Collection bank account</h2>
            <p class="text-xs text-gray-500 mt-1">
                One inbound bank account for all bank transfers (amounts above {{ format_money($mobileMoneyThreshold ?? payment_mobile_money_threshold()) }} must use bank, not mobile money).
                Borrowers pay this account and upload proof for staff verification.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Default bank account (e.g. TCB)</label>
                <select name="default_collection_bank_account_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">— Not set —</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected(($defaultCollectionBankId ?? 0) === $account->id)>
                            {{ $account->bank_name }} · {{ $account->name }} · {{ $account->account_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2.5">
                <input type="hidden" name="apply_to_all_bank_mappings" value="0">
                <input type="checkbox" name="apply_to_all_bank_mappings" value="1" checked class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">Apply to all bank transfer mappings</span>
            </label>
        </div>

        @if ($defaultCollectionBank ?? null)
            <p class="text-xs text-emerald-800 bg-emerald-50 ring-1 ring-emerald-100 rounded-lg px-3 py-2">
                Active:
                <strong>{{ $defaultCollectionBank->bank_name }}</strong>
                · {{ $defaultCollectionBank->name }}
                · Account <span class="font-mono">{{ $defaultCollectionBank->account_number }}</span>
            </p>
        @endif

        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2.5 rounded-xl">
            Save collection bank account
        </button>
    </form>

    <form method="POST" action="{{ route('admin.settings.payment-accounts.default-disbursement') }}" class="bg-white rounded-2xl ring-1 ring-brand/10 p-5 mb-8 space-y-4">
        @csrf @method('PUT')

        <div>
            <h2 class="text-sm font-semibold text-brand">Disbursement account</h2>
            <p class="text-xs text-gray-500 mt-1">
                Mobile money wallet used for outbound payouts. In live mode, API credentials on the account are required.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Default disbursement account</label>
                <select name="default_disbursement_mobile_money_account_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">— Not set —</option>
                    @foreach ($mobileAccounts as $account)
                        <option value="{{ $account->id }}" @selected($defaultDisbursementId === $account->id)>
                            {{ $account->name }} · {{ $account->provider }}
                            ({{ str_replace('_', ' ', $account->purpose) }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($defaultDisbursement)
            <p class="text-xs text-emerald-800 bg-emerald-50 ring-1 ring-emerald-100 rounded-lg px-3 py-2">
                Active: <strong>{{ $defaultDisbursement->name }}</strong> · {{ $defaultDisbursement->msisdn }}
            </p>
        @endif

        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2.5 rounded-xl">
            Save disbursement account
        </button>
    </form>

    <form method="POST" action="{{ route('admin.settings.payment-accounts.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden mb-8">
        @csrf @method('PUT')

        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Default account mappings</h2>
            <p class="text-xs text-gray-500 mt-1">Used unless a loan product has a specific override below.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Payment type</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3">Instructions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($mappings as $mapping)
                        <tr class="align-top">
                            <td class="px-5 py-3 font-medium">{{ $mapping->typeLabel() }}</td>
                            <td class="px-5 py-3">{{ $mapping->methodLabel() }}</td>
                            <td class="px-5 py-3">
                                @if ($mapping->payment_method === 'bank_transfer')
                                    <select name="mappings[{{ $mapping->payment_type }}][{{ $mapping->payment_method }}][bank_account_id]"
                                            class="w-full min-w-[200px] rounded-lg border-gray-200 text-sm">
                                        <option value="">— Select bank account —</option>
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}" @selected($mapping->bank_account_id === $account->id)>
                                                {{ $account->bank_name }} · {{ $account->name }} ({{ $account->account_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <select name="mappings[{{ $mapping->payment_type }}][{{ $mapping->payment_method }}][mobile_money_account_id]"
                                            class="w-full min-w-[200px] rounded-lg border-gray-200 text-sm">
                                        <option value="">— Select mobile money account —</option>
                                        @foreach ($mobileAccounts as $account)
                                            <option value="{{ $account->id }}" @selected($mapping->mobile_money_account_id === $account->id)>
                                                {{ $account->name }} · {{ $account->provider }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <textarea name="mappings[{{ $mapping->payment_type }}][{{ $mapping->payment_method }}][payment_instructions]"
                                          rows="2"
                                          class="w-full min-w-[220px] rounded-lg border-gray-200 text-xs"
                                          placeholder="Optional payment instructions">{{ $mapping->payment_instructions }}</textarea>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">
                Save default mappings
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Loan product overrides</h2>
            <p class="text-xs text-gray-500 mt-1">Override the default account for a specific product and payment type.</p>
        </div>

        @if ($overrides->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3">Account</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($overrides as $override)
                            <tr>
                                <td class="px-5 py-3">{{ $override->loanProduct?->name }}</td>
                                <td class="px-5 py-3">{{ config("payment_types.types.{$override->payment_type}.label", $override->payment_type) }}</td>
                                <td class="px-5 py-3">{{ config("payment_types.methods.{$override->payment_method}.label", $override->payment_method) }}</td>
                                <td class="px-5 py-3 text-xs">
                                    @if ($override->bankAccount)
                                        {{ $override->bankAccount->bank_name }} · {{ $override->bankAccount->account_number }}
                                    @elseif ($override->mobileMoneyAccount)
                                        {{ $override->mobileMoneyAccount->name }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.settings.payment-accounts.overrides.destroy', $override) }}"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js('Remove this override?'),
                                              message: @js('Remove this override?'),
                                              confirmLabel: @js('Remove'),
                                              confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                              tone: 'warning',
                                          })">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.payment-accounts.overrides.save') }}" class="px-5 py-4 border-t border-gray-100 space-y-3">
            @csrf
            <h3 class="text-xs font-semibold uppercase text-gray-500">Add override</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <select name="loan_product_id" required class="rounded-lg border-gray-200 text-sm">
                    <option value="">Product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <select name="payment_type" required class="rounded-lg border-gray-200 text-sm">
                    @foreach ($types as $key => $type)
                        <option value="{{ $key }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <select name="payment_method" required class="rounded-lg border-gray-200 text-sm" x-data x-on:change="$dispatch('method-changed')">
                    @foreach ($methods as $key => $method)
                        <option value="{{ $key }}">{{ $method['label'] }}</option>
                    @endforeach
                </select>
                <select name="bank_account_id" class="rounded-lg border-gray-200 text-sm">
                    <option value="">Bank account (if bank)</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_number }}</option>
                    @endforeach
                </select>
                <select name="mobile_money_account_id" class="rounded-lg border-gray-200 text-sm">
                    <option value="">Mobile account (if mobile)</option>
                    @foreach ($mobileAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">
                Add product override
            </button>
        </form>
    </div>
</x-admin.layout>
