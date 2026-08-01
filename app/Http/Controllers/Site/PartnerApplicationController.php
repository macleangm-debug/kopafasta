<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\PartnerEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function create(): View
    {
        return view('site.affiliate.apply', [
            'regions' => array_keys(config('tanzania_locations', [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_category' => ['required', 'in:individual,company'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'tin' => ['nullable', 'string', 'max:40'],
            'region' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
            'doc_brela' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_other' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($data['applicant_category'] === 'company' && blank($data['business_name'] ?? null)) {
            return back()->withErrors(['business_name' => __('site.affiliate_apply.business_required')])->withInput();
        }

        if ($data['applicant_category'] === 'individual') {
            $data['business_name'] = ($data['business_name'] ?? null) ?: $data['full_name'];
        }

        $application = app(PartnerEnrollmentService::class)->submitApplication(
            [
                ...$data,
                'partner_category' => 'affiliate',
                'type' => 'affiliate',
            ],
            $this->documentUploads($request),
        );

        return redirect()
            ->route('site.partners.apply.tracking', ['phone' => $application->phone])
            ->with('partner_submitted', true);
    }

    public function tracking(Request $request): View
    {
        $phone = trim((string) $request->query('phone', $request->input('phone', '')));
        $applications = collect();

        if ($phone !== '') {
            $normalized = preg_replace('/\D+/', '', $phone) ?: $phone;
            $applications = \App\Models\PartnerApplication::query()
                ->with('partner')
                ->where(function ($q) use ($phone, $normalized) {
                    $q->where('phone', $phone)
                        ->orWhere('phone', 'like', '%'.$normalized)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ['%'.$normalized]);
                })
                ->latest()
                ->limit(10)
                ->get();
        }

        return view('site.partners.tracking', [
            'phone' => $phone,
            'applications' => $applications,
        ]);
    }

    public function createService(?string $category = null): View
    {
        $enrollment = app(PartnerEnrollmentService::class);
        $normalized = $category ? $enrollment->normalizeCategory($category) : 'debt_collector';

        if (! in_array($normalized, $enrollment->enrollableCategoryKeys(), true)) {
            abort(404);
        }

        return view('site.partners.apply', [
            'category' => $normalized,
            'categoryLabel' => $enrollment->categoryLabel($normalized),
            'categories' => PartnerEnrollmentService::ENROLLABLE_CATEGORIES,
            'regions' => array_keys(config('tanzania_locations', [])),
            'docTypes' => \App\Models\PartnerApplicationDocument::DOC_TYPES,
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $enrollment = app(PartnerEnrollmentService::class);
        $allowed = $enrollment->enrollableCategoryKeys();

        $data = $request->validate([
            'partner_category' => ['required', 'string', Rule::in($allowed)],
            'applicant_category' => ['required', 'in:individual,company'],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'tin' => ['nullable', 'string', 'max:40'],
            'region' => ['nullable', 'string', 'max:100'],
            'coverage_regions' => ['nullable', 'array'],
            'coverage_regions.*' => ['string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
            'doc_brela' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_other' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $category = $enrollment->normalizeCategory($data['partner_category']);

        // Only valuers may register as individuals; all other service partners are companies.
        if ($category !== 'valuer') {
            $data['applicant_category'] = 'company';
        }

        if ($data['applicant_category'] === 'company' && blank($data['business_name'] ?? null)) {
            return back()->withErrors(['business_name' => __('validation.required', ['attribute' => 'business name'])])->withInput();
        }

        if ($data['applicant_category'] === 'individual') {
            $data['business_name'] = ($data['business_name'] ?? null) ?: $data['full_name'];
        }

        if ($data['applicant_category'] === 'company'
            && in_array($category, ['debt_collector', 'valuer', 'gps_installer', 'insurance', 'yard'], true)) {
            if (blank($data['registration_number'] ?? null)) {
                return back()->withErrors(['registration_number' => __('site.partner_apply.registration_required')])->withInput();
            }
            if (blank($data['tin'] ?? null)) {
                return back()->withErrors(['tin' => __('site.partner_apply.tin_required')])->withInput();
            }
        }

        $application = $enrollment->submitApplication($data, $this->documentUploads($request));

        return redirect()
            ->route('site.partners.apply.tracking', ['phone' => $application->phone])
            ->with('partner_submitted', true);
    }

    /** @return array<string, \Illuminate\Http\UploadedFile|null> */
    private function documentUploads(Request $request): array
    {
        return [
            'brela' => $request->file('doc_brela'),
            'tin_certificate' => $request->file('doc_tin_certificate'),
            'business_licence' => $request->file('doc_business_licence'),
            'national_id_front' => $request->file('doc_national_id_front'),
            'national_id_back' => $request->file('doc_national_id_back'),
            'other' => $request->file('doc_other'),
        ];
    }
}
