<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Services\AuditService;
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
        $id = $model?->id;

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
        abort_unless(auth()->user()?->hasPermission('customers.edit'), 403);

        $rules = match ($section) {
            'personal' => [
                'first_name'    => ['required', 'string', 'max:100'],
                'last_name'     => ['required', 'string', 'max:100'],
                'national_id'   => ['nullable', 'string', 'max:50'],
                'date_of_birth' => ['nullable', 'date'],
                'gender'        => ['nullable', 'in:male,female,other'],
                'phone'         => ['required', 'string', 'max:30'],
                'email'         => ['nullable', 'email', 'max:150'],
            ],
            'residence' => [
                'region'   => ['nullable', 'string', 'max:100'],
                'district' => ['nullable', 'string', 'max:100'],
                'ward'     => ['nullable', 'string', 'max:100'],
                'street'   => ['nullable', 'string', 'max:255'],
            ],
            'activity' => [
                'activity_type'   => ['nullable', 'string', 'max:40'],
                'income_range'    => ['nullable', 'string', 'max:30'],
                'employment_type' => ['nullable', 'string', 'max:50'],
                'business_name'   => ['nullable', 'string', 'max:150'],
                'monthly_income'  => ['nullable', 'numeric', 'min:0'],
            ],
            'kin' => [
                'nok_name'         => ['nullable', 'string', 'max:150'],
                'nok_relationship' => ['nullable', 'string', 'max:40'],
                'nok_phone'        => ['nullable', 'string', 'max:20'],
                'nok_region'       => ['nullable', 'string', 'max:100'],
                'nok_district'     => ['nullable', 'string', 'max:100'],
            ],
            'account' => [
                'status'    => ['required', 'in:active,inactive,suspended'],
                'branch_id' => ['nullable', 'exists:branches,id'],
                'type'      => ['required', 'in:individual,business'],
            ],
            default => abort(404),
        };

        $data = $request->validate($rules);

        if ($section === 'residence') {
            $data['address'] = trim(collect([
                $data['street'] ?? null,
                $data['ward'] ?? null,
                $data['district'] ?? null,
                $data['region'] ?? null,
            ])->filter()->implode(', ')) ?: $customer->address;
        }

        if ($section === 'personal' && $customer->identity_locked) {
            unset($data['first_name'], $data['last_name'], $data['national_id'], $data['date_of_birth']);
        }

        $before = app(AuditService::class)->snapshot($customer);
        $customer->update($data);
        $this->auditAdmin('admin.customers.section_updated', $customer, [
            'section' => $section,
            'before'  => $before,
            'after'   => app(AuditService::class)->snapshot($customer),
        ]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', ucfirst($section).' details saved.')
            ->withFragment('customer-'.$section);
    }

    public function uploadDocument(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('customers.edit'), 403);

        $data = $request->validate([
            'document_type_id' => ['required', 'exists:document_types,id'],
            'file'             => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('file')->store("admin/customer/{$customer->id}/documents", 'public');

        $document = CustomerDocument::create([
            'customer_id'      => $customer->id,
            'document_type_id' => $data['document_type_id'],
            'file_path'        => $path,
            'status'           => 'pending_review',
            'notes'            => $data['notes'] ?? null,
        ]);

        $this->auditAdmin('admin.customers.document_uploaded', $document, [
            'customer_id'      => $customer->id,
            'document_type_id' => $data['document_type_id'],
        ]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Document uploaded successfully.')
            ->withFragment('customer-documents');
    }

    public function verifyDocument(Customer $customer, CustomerDocument $document): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('customers.edit'), 403);
        abort_unless((int) $document->customer_id === (int) $customer->id, 404);

        $document->update([
            'status'      => 'verified',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        $this->auditAdmin('admin.customers.document_verified', $document, ['customer_id' => $customer->id]);

        return back()->with('status', 'Document marked as verified.');
    }

    public function rejectDocument(Request $request, Customer $customer, CustomerDocument $document): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('customers.edit'), 403);
        abort_unless((int) $document->customer_id === (int) $customer->id, 404);

        $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $document->update([
            'status' => 'rejected',
            'notes'  => $request->input('notes') ?: $document->notes,
        ]);

        $this->auditAdmin('admin.customers.document_rejected', $document, [
            'customer_id' => $customer->id,
            'notes'       => $request->input('notes'),
        ]);

        return back()->with('status', 'Document rejected.');
    }

    public function unlockNidaIdentity(Customer $customer, \App\Services\NidaVerificationService $nida): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('customers.edit'), 403);

        $nida->unlockIdentityVerification($customer, auth()->user());

        return back()->with('status', 'NIDA identity lock cleared. The borrower can sign in and verify again.');
    }
}
