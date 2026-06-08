<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStageHistory;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\LoanApplicationWorkflowService;
use App\Services\ReferenceNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', LoanApplication::class);

        return response()->json(
            LoanApplication::with(['customer', 'product'])->latest()->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', LoanApplication::class);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_tenure_months' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $data['branch_id'] = $data['branch_id'] ?? $customer->branch_id;

        if (! $data['branch_id']) {
            return response()->json([
                'message' => 'Branch is required for loan application',
            ], 422);
        }

        $product = LoanProduct::findOrFail($data['loan_product_id']);

        $application = LoanApplication::create(array_merge($data, [
            'application_number' => app(ReferenceNumberService::class)->applicationReference($product),
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'submitted_at' => now(),
        ]));

        ApplicationStageHistory::create([
            'loan_application_id' => $application->id,
            'from_stage' => null,
            'to_stage' => 'submitted',
            'remarks' => 'Application submitted by customer portal',
        ]);

        return response()->json($application, 201);
    }

    public function show(LoanApplication $loanApplication)
    {
        $this->authorize('view', $loanApplication);

        return response()->json($loanApplication->load(['customer', 'product', 'stageHistory', 'loan']));
    }

    public function update(Request $request, LoanApplication $loanApplication)
    {
        $this->authorize('update', $loanApplication);

        $data = $request->validate([
            'recommended_amount' => ['nullable', 'numeric', 'min:0'],
            'screening_payload' => ['nullable', 'array'],
            'credit_appraisal_payload' => ['nullable', 'array'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $loanApplication->update($data);

        return response()->json($loanApplication->fresh());
    }

    public function destroy(LoanApplication $loanApplication)
    {
        $this->authorize('delete', $loanApplication);

        $loanApplication->delete();

        return response()->json(status: 204);
    }

    public function transition(Request $request, LoanApplication $loanApplication, LoanApplicationWorkflowService $workflow)
    {
        $this->authorize('transition', $loanApplication);

        $data = $request->validate([
            'to_stage' => ['required', 'string', 'in:'.implode(',', LoanApplication::STAGES)],
            'remarks' => ['nullable', 'string'],
        ]);

        try {
            $application = $workflow->transitionToStage(
                $loanApplication,
                $request->user(),
                $data['to_stage'],
                $data['remarks'] ?? null,
                filter_var($request->input('override_affordability'), FILTER_VALIDATE_BOOL),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($application->load('stageHistory'));
    }
}
