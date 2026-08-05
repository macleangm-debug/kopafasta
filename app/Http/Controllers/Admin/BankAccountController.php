<?php

namespace App\Http\Controllers\Admin;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Model;

class BankAccountController extends ResourceController
{
    protected string $model = BankAccount::class;
    protected string $routePrefix = 'admin.bank-accounts';
    protected string $viewFolder = 'bank-accounts';
    protected string $singular = 'bank account';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'bank_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:50'],
            'branch' => ['nullable', 'string', 'max:150'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['nullable', 'numeric'],
            'gl_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'purpose' => ['required', 'in:operating,disbursement,collection,reserve,escrow'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'glAccounts' => ChartOfAccount::where('type', 'asset')->orderBy('code')->pluck('name', 'id'),
            'purposes'   => [
                'operating'           => 'Operating',
                'disbursement'        => 'Disbursement',
                'collection'          => 'Collection',
                'reserve'             => 'Reserve',
                'escrow'              => 'Escrow',
                'registration_fee'    => 'Membership fee',
                'application_fee'     => 'Application fee',
                'post_approval_fee'   => 'Post-approval fee',
                'penalty'             => 'Penalty',
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        return $data;
    }
}
