<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\LoanProduct;
use App\Models\LoanProductPaymentAccountOverride;
use App\Models\MobileMoneyAccount;
use App\Models\PaymentAccountMapping;
use App\Models\Setting;
use App\Services\PaymentAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentAccountSettingsController extends Controller
{
    public function index(PaymentAccountService $accounts): View
    {
        $accounts->ensureDefaultMappings();

        $defaultCollectionId = (int) (Setting::get('payments.default_collection_mobile_money_account_id') ?? 0);
        $defaultDisbursementId = (int) (Setting::get('payments.default_disbursement_mobile_money_account_id') ?? 0);

        return view('admin.settings.payment-accounts', [
            'mappings'              => PaymentAccountMapping::with(['bankAccount', 'mobileMoneyAccount'])->orderBy('payment_type')->orderBy('payment_method')->get(),
            'bankAccounts'          => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'mobileAccounts'        => MobileMoneyAccount::where('is_active', true)->orderBy('name')->get(),
            'defaultCollectionId'   => $defaultCollectionId,
            'defaultCollection'   => $defaultCollectionId > 0
                ? MobileMoneyAccount::find($defaultCollectionId)
                : null,
            'defaultDisbursementId' => $defaultDisbursementId,
            'defaultDisbursement'   => $defaultDisbursementId > 0
                ? MobileMoneyAccount::find($defaultDisbursementId)
                : null,
            'types'                 => config('payment_types.types', []),
            'methods'               => config('payment_types.methods', []),
            'products'              => LoanProduct::orderBy('name')->get(['id', 'name', 'code']),
            'overrides'             => LoanProductPaymentAccountOverride::with(['loanProduct', 'bankAccount', 'mobileMoneyAccount'])->get(),
        ]);
    }

    public function saveDefaultCollection(Request $request, PaymentAccountService $accounts): RedirectResponse
    {
        $data = $request->validate([
            'default_collection_mobile_money_account_id' => ['nullable', 'exists:mobile_money_accounts,id'],
            'apply_to_all_mappings'                      => ['nullable', 'boolean'],
        ]);

        $accountId = (int) ($data['default_collection_mobile_money_account_id'] ?? 0);
        Setting::set('payments.default_collection_mobile_money_account_id', $accountId > 0 ? $accountId : '');

        $updated = 0;
        if ($request->boolean('apply_to_all_mappings') && $accountId > 0) {
            $updated = $accounts->applyDefaultCollectionToAllMappings($accountId);
        }

        $message = 'Default PSP collection account saved.';
        if ($updated > 0) {
            $message .= " Applied to {$updated} mobile money mapping(s).";
        }

        return back()->with('status', $message);
    }

    public function saveDefaultDisbursement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_disbursement_mobile_money_account_id' => ['nullable', 'exists:mobile_money_accounts,id'],
        ]);

        $accountId = (int) ($data['default_disbursement_mobile_money_account_id'] ?? 0);
        Setting::set('payments.default_disbursement_mobile_money_account_id', $accountId > 0 ? $accountId : '');

        return back()->with('status', 'Default disbursement mobile money account saved.');
    }

    public function saveDefaults(Request $request, PaymentAccountService $accounts): RedirectResponse
    {
        $types = array_keys(config('payment_types.types', []));
        $methods = array_keys(config('payment_types.methods', []));

        $rules = ['mappings' => ['required', 'array']];
        foreach ($types as $type) {
            foreach ($methods as $method) {
                $rules["mappings.{$type}.{$method}.bank_account_id"] = ['nullable', 'exists:bank_accounts,id'];
                $rules["mappings.{$type}.{$method}.mobile_money_account_id"] = ['nullable', 'exists:mobile_money_accounts,id'];
                $rules["mappings.{$type}.{$method}.payment_instructions"] = ['nullable', 'string', 'max:2000'];
            }
        }

        $data = $request->validate($rules);
        $rows = [];

        foreach ($types as $type) {
            foreach ($methods as $method) {
                $row = $data['mappings'][$type][$method] ?? [];
                $rows[] = [
                    'payment_type'            => $type,
                    'payment_method'          => $method,
                    'bank_account_id'         => $row['bank_account_id'] ?? null,
                    'mobile_money_account_id' => $row['mobile_money_account_id'] ?? null,
                    'payment_instructions'    => $row['payment_instructions'] ?? null,
                    'is_active'               => true,
                ];
            }
        }

        $accounts->syncDefaultMappings($rows);

        return back()->with('status', 'Payment account mappings saved.');
    }

    public function saveOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'payment_type'            => ['required', 'string', 'max:40'],
            'payment_method'          => ['required', 'string', 'max:30'],
            'bank_account_id'         => ['nullable', 'exists:bank_accounts,id'],
            'mobile_money_account_id' => ['nullable', 'exists:mobile_money_accounts,id'],
            'payment_instructions'    => ['nullable', 'string', 'max:2000'],
        ]);

        LoanProductPaymentAccountOverride::updateOrCreate(
            [
                'loan_product_id' => $data['loan_product_id'],
                'payment_type'    => $data['payment_type'],
                'payment_method'  => $data['payment_method'],
            ],
            [
                'bank_account_id'         => $data['bank_account_id'] ?? null,
                'mobile_money_account_id' => $data['mobile_money_account_id'] ?? null,
                'payment_instructions'    => $data['payment_instructions'] ?? null,
            ],
        );

        return back()->with('status', 'Product payment account override saved.');
    }

    public function deleteOverride(LoanProductPaymentAccountOverride $override): RedirectResponse
    {
        $override->delete();

        return back()->with('status', 'Product override removed.');
    }
}
