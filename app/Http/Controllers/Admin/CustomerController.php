<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Services\CustomerDossierService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends ResourceController
{
    protected string $model = Customer::class;
    protected string $routePrefix = 'admin.customers';
    protected string $viewFolder = 'customers';
    protected string $singular = 'customer';

    protected function rules(?Model $model = null): array
    {
        return [
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'email'            => ['nullable', 'email', 'max:150'],
            'phone'            => ['required', 'string', 'max:30'],
            'type'             => ['required', 'in:individual,business'],
            'status'           => ['required', 'in:active,inactive,suspended'],
            'customer_number'  => ['nullable', 'string', 'max:50'],
            'national_id'      => ['nullable', 'string', 'max:50'],
            'date_of_birth'    => ['nullable', 'date'],
            'address'          => ['nullable', 'string', 'max:500'],
            'employment_type'  => ['nullable', 'string', 'max:50'],
            'business_name'    => ['nullable', 'string', 'max:150'],
            'monthly_income'   => ['nullable', 'numeric', 'min:0'],
            'branch_id'        => ['nullable', 'exists:branches,id'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'branches' => \App\Models\Branch::orderBy('name')->pluck('name', 'id'),
            'types'    => ['individual' => 'Individual', 'business' => 'Business'],
            'statuses' => ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (empty($data['customer_number'])) {
            $data['customer_number'] = 'CU-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        }

        return $data;
    }

    public function show($id): View
    {
        $record = Customer::findOrFail($id);
        $dossierData = app(CustomerDossierService::class)->dossier($record);

        return view('admin.customers.show', [
            'record'  => $record,
            'dossier' => $dossierData,
            'regions' => array_keys(config('tanzania_locations', [])),
            'branches' => \App\Models\Branch::orderBy('name')->pluck('name', 'id'),
            'activityTypes' => config('activity_profiles.types', []),
            'incomeRanges' => collect(config('income_ranges', []))->mapWithKeys(fn ($v, $k) => [$k => $v['label'] ?? $k]),
        ] + $this->formData());
    }

    public function updateSection(Request $request, Customer $customer, string $section): RedirectResponse
    {
        abort(403, 'Customer profile is read-only. Borrowers update their own details in the app; KYC and document requests happen on the loan application.');
    }

    public function uploadDocument(Request $request, Customer $customer): RedirectResponse
    {
        abort(403, 'Upload documents from the loan application under screening — not from the customer profile.');
    }

    public function verifyDocument(Customer $customer, CustomerDocument $document): RedirectResponse
    {
        abort(403, 'Document verification happens on the loan application under screening.');
    }

    public function rejectDocument(Request $request, Customer $customer, CustomerDocument $document): RedirectResponse
    {
        abort(403, 'Document rejection happens on the loan application under screening.');
    }

    public function unlockNidaIdentity(Customer $customer, \App\Services\NidaVerificationService $nida): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('customers.edit'), 403);

        $nida->unlockIdentityVerification($customer, auth()->user());

        return back()->with('status', 'NIDA identity lock cleared. The borrower can sign in and verify again.');
    }
}
