<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Vendor;
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
            'vendor_id'      => ['nullable', 'exists:vendors,id'],
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

    protected function formData(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'vendors'  => Vendor::orderBy('name')->pluck('name', 'id'),
            'accounts' => ChartOfAccount::where('type', 'expense')->orderBy('code')->pluck('name', 'id'),
            'statuses' => ['recorded' => 'Recorded', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'],
            'methods'  => ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cheque' => 'Cheque'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! $existing && empty($data['recorded_by']) && auth()->id()) {
            $data['recorded_by'] = auth()->id();
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
        return redirect()->route('admin.expenses.show', $expense)->with('status', 'Expense recorded.');
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        $was = $expense->status;
        $data = $this->transform($request->validate($this->rules($expense)), $expense);
        $expense->update($data);
        if ($expense->status === 'paid' && $was !== 'paid') {
            app(ExpensePostingService::class)->post($expense->fresh());
        }
        return redirect()->route('admin.expenses.show', $expense)->with('status', 'Expense updated.');
    }

    public function post(Expense $expense)
    {
        $entry = app(ExpensePostingService::class)->post($expense);
        return back()->with('status', $entry ? 'Journal '.$entry->entry_number.' posted.' : 'Could not post (check finance defaults).');
    }
}

