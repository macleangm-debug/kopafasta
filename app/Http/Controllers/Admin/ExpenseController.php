<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Vendor;
use App\Services\AuditService;
use App\Services\ExpensePostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ExpenseController extends ResourceController
{
    protected string $model = Expense::class;
    protected string $routePrefix = 'admin.expenses';
    protected string $viewFolder = 'expenses';
    protected string $singular = 'expense';

    protected function rules(?Model $model = null): array
    {
        return [
            'branch_id'      => ['nullable', 'exists:branches,id'],
            'vendor_id'      => ['nullable', 'exists:partners,id'],
            'gl_account_id'  => ['nullable', 'exists:chart_of_accounts,id'],
            'category'       => ['required', 'string', 'max:80'],
            'description'    => ['nullable', 'string', 'max:500'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'expense_date'   => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference'      => ['nullable', 'string', 'max:80'],
            'status'         => ['required', 'in:recorded,pending,approved,paid,rejected'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'branches'   => Branch::orderBy('name')->pluck('name', 'id'),
            'vendors'    => Vendor::orderBy('name')->pluck('name', 'id'),
            'accounts'   => ChartOfAccount::where('type', 'expense')->where('is_active', true)->orderBy('code')->get()
                ->mapWithKeys(fn (ChartOfAccount $a) => [$a->id => $a->code.' · '.$a->name]),
            'categories' => [
                'rent'        => 'Rent',
                'salaries'    => 'Salaries & wages',
                'utilities'   => 'Utilities',
                'marketing'   => 'Marketing',
                'legal'       => 'Legal',
                'insurance'   => 'Insurance',
                'gps'         => 'GPS',
                'office'      => 'Office & admin',
                'travel'      => 'Travel',
                'fuel'        => 'Fuel',
                'other'       => 'Other',
            ],
            'statuses' => ['recorded' => 'Recorded', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'],
            'methods'  => ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cheque' => 'Cheque'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! $existing && empty($data['recorded_by']) && auth()->id()) {
            $data['recorded_by'] = auth()->id();
        }

        if (empty($data['gl_account_id']) && ! empty($data['category'])) {
            $code = match ((string) $data['category']) {
                'rent' => '5060',
                'salaries' => '5070',
                'utilities' => '5080',
                'marketing' => '5090',
                'insurance' => '5100',
                'office' => '5110',
                'travel', 'fuel' => '5120',
                'legal' => '5040',
                'gps' => '5030',
                default => '5050',
            };
            $accountId = ChartOfAccount::query()->where('code', $code)->value('id');
            if ($accountId) {
                $data['gl_account_id'] = $accountId;
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $expense = Expense::create($data);
        if (($expense->status ?? null) === 'paid') {
            app(ExpensePostingService::class)->post($expense);
        }
        $this->auditAdminCreated($expense);

        return redirect()->route('admin.expenses.show', $expense)->with('status', 'Expense recorded.');
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        $before = app(AuditService::class)->snapshot($expense);
        $was = $expense->status;
        $data = $this->transform($request->validate($this->rules($expense)), $expense);
        $expense->update($data);
        if ($expense->status === 'paid' && $was !== 'paid') {
            app(ExpensePostingService::class)->post($expense->fresh());
        }
        $expense->refresh();
        $this->auditAdminUpdated($expense, $before);

        return redirect()->route('admin.expenses.show', $expense)->with('status', 'Expense updated.');
    }

    public function post(Expense $expense)
    {
        $entry = app(ExpensePostingService::class)->post($expense);
        $this->auditAdmin('admin.expenses.posted', $expense, [
            'journal' => $entry?->entry_number,
        ]);

        return back()->with('status', $entry ? 'Journal '.$entry->entry_number.' posted.' : 'Could not post (check finance defaults).');
    }
}

