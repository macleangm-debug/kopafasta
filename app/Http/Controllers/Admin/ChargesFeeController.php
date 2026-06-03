<?php

namespace App\Http\Controllers\Admin;

use App\Models\ChargesFee;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;

class ChargesFeeController extends ResourceController
{
    protected string $model = ChargesFee::class;
    protected string $routePrefix = 'admin.charges-fees';
    protected string $viewFolder = 'charges-fees';
    protected string $singular = 'fee';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'type' => ['required', 'in:origination,processing,late_fee,penalty,insurance,gps,valuation,restructure,early_settlement,other'],
            'basis' => ['required', 'in:fixed,percentage,per_day,per_installment'],
            'amount' => ['required', 'numeric', 'min:0'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'charge_when' => ['required', 'in:application,post_approval,disbursement,repayment,late,event'],
            'gl_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'types' => ['origination'=>'Origination','processing'=>'Processing','late_fee'=>'Late fee','penalty'=>'Penalty','insurance'=>'Insurance','gps'=>'GPS','valuation'=>'Valuation','restructure'=>'Restructure','early_settlement'=>'Early settlement','other'=>'Other'],
            'bases' => ['fixed'=>'Fixed amount','percentage'=>'Percentage','per_day'=>'Per day','per_installment'=>'Per installment'],
            'whens' => [
                'application'    => 'At application',
                'post_approval'  => 'After approval (before disbursement)',
                'disbursement'   => 'At disbursement',
                'repayment'      => 'At repayment',
                'late'           => 'When late / in arrears',
                'event'          => 'On event',
            ],
            'glAccounts' => ChartOfAccount::where('type', 'income')->orderBy('code')->pluck('name', 'id'),
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
