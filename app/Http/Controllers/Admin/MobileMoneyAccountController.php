<?php

namespace App\Http\Controllers\Admin;

use App\Models\ChartOfAccount;
use App\Models\MobileMoneyAccount;
use Illuminate\Database\Eloquent\Model;

class MobileMoneyAccountController extends ResourceController
{
    protected string $model = MobileMoneyAccount::class;
    protected string $routePrefix = 'admin.mobile-money-accounts';
    protected string $viewFolder = 'mobile-money-accounts';
    protected string $singular = 'mobile money account';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'provider' => ['required', 'in:m_pesa,tigo_pesa,airtel_money,halopesa,other'],
            'msisdn' => ['required', 'string', 'max:20'],
            'paybill_number' => ['nullable', 'string', 'max:20'],
            'till_number' => ['nullable', 'string', 'max:20'],
            'api_username' => ['nullable', 'string', 'max:150'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', 'string', 'max:30'],
            'opening_balance' => ['nullable', 'numeric'],
            'gl_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'purpose' => ['required', 'in:disbursement,collection,both'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function formData(): array
    {
        return [
            'providers'  => ['m_pesa'=>'M-Pesa','tigo_pesa'=>'Tigo Pesa','airtel_money'=>'Airtel Money','halopesa'=>'HaloPesa','other'=>'Other'],
            'purposes'   => ['disbursement'=>'Disbursement','collection'=>'Collection','both'=>'Both'],
            'environments' => ['production'=>'Production','sandbox'=>'Sandbox'],
            'glAccounts' => ChartOfAccount::where('type', 'asset')->orderBy('code')->pluck('name', 'id'),
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        return $data;
    }
}
