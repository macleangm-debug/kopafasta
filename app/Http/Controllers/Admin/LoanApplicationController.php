<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\LoanApplicationWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanApplicationController extends ResourceController
{
    protected string $model = LoanApplication::class;
    protected string $routePrefix = 'admin.loan-applications';
    protected string $viewFolder = 'loan-applications';
    protected string $singular = 'application';

    protected function rules(?Model $model = null): array
    {
        return [
            'customer_id'              => ['required', 'exists:customers,id'],
            'loan_product_id'          => ['required', 'exists:loan_products,id'],
            'branch_id'                => ['nullable', 'exists:branches,id'],
            'application_number'       => ['nullable', 'string', 'max:50'],
            'requested_amount'         => ['required', 'numeric', 'min:0'],
            'requested_tenure_months'  => ['required', 'integer', 'min:1', 'max:120'],
            'recommended_amount'       => ['nullable', 'numeric', 'min:0'],
            'status'                   => ['required', 'in:draft,submitted,under_review,pre_approved,approved,rejected,withdrawn,disbursed'],
            'current_stage'            => ['nullable', 'string', 'max:80'],
            'purpose'                  => ['nullable', 'string', 'max:500'],
            'rejection_reason'         => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function formData(): array
    {
        return [
            'customers' => Customer::orderBy('first_name')->limit(500)->get()
                ->mapWithKeys(fn($c) => [$c->id => trim($c->first_name.' '.$c->last_name)]),
            'products'  => LoanProduct::orderBy('name')->pluck('name', 'id'),
            'branches'  => Branch::orderBy('name')->pluck('name', 'id'),
            'statuses'  => [
                'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under review',
                'pre_approved' => 'Pre-approved', 'approved' => 'Approved', 'rejected' => 'Rejected',
                'withdrawn' => 'Withdrawn', 'disbursed' => 'Disbursed',
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['application_number'])) {
            $data['application_number'] = 'APP-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        }
        return $data;
    }

    public function show($id): View
    {
        $record = LoanApplication::query()
            ->with(['customer', 'product', 'stageHistory.changedByUser'])
            ->findOrFail($id);

        $workflow = app(LoanApplicationWorkflowService::class);
        $availableActions = $workflow->availableActions($record, auth()->user());
        $stageHistory = $record->stageHistory()->latest()->get();
        $auditLogs = \App\Models\AuditLog::query()
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->id)
            ->latest()
            ->limit(20)
            ->with('user')
            ->get();

        return view("admin.{$this->viewFolder}.show", compact('record', 'availableActions', 'stageHistory', 'auditLogs', 'workflow'));
    }

    public function runWorkflow(Request $request, LoanApplication $loan_application, LoanApplicationWorkflowService $workflow): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('applications.view'), 403);

        $data = $request->validate([
            'action'  => ['required', 'string', 'in:'.implode(',', array_keys(LoanApplicationWorkflowService::ACTIONS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] === 'reject' && empty(trim($data['remarks'] ?? ''))) {
            return back()->withErrors(['remarks' => 'Rejection reason is required.'])->withInput();
        }

        try {
            $workflow->transition(
                $loan_application,
                auth()->user(),
                $data['action'],
                $data['remarks'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $label = LoanApplicationWorkflowService::ACTIONS[$data['action']]['label'] ?? 'Action completed';

        return redirect()
            ->route("{$this->routePrefix}.show", $loan_application)
            ->with('status', $label.' completed successfully.');
    }
}
