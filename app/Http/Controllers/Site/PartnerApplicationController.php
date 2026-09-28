<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\AffiliateApplicationFeePaymentService;
use App\Services\PartnerEnrollmentService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function create(): View
    {
        $feeService = app(AffiliateApplicationFeePaymentService::class);

        return view('site.affiliate.apply', [
            'regions' => array_keys(config('tanzania_locations', [])),
            'feeRequired' => $feeService->isRequired(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isCompany = $request->input('applicant_category') === 'company';
        $registeredBusiness = $request->input('registered_business') === 'yes';

        $data = $request->validate([
            'applicant_category' => ['required', 'in:individual,company'],
            'full_name' => ['required', 'string', 'max:150'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female,other'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'tin' => ['nullable', 'string', 'max:40'],
            'region' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'ward' => ['required', 'string', 'max:100'],
            'coverage_regions' => ['nullable', 'array'],
            'coverage_regions.*' => ['string', 'max:100'],
            'occupation' => ['required', 'string', 'max:150'],
            'occupation_other' => ['nullable', 'string', 'max:150', 'required_if:occupation,other'],
            'sales_experience' => ['required', 'string', 'max:2000'],
            'financial_services_experience' => ['nullable', 'string', 'max:2000'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:40'],
            'previous_agent' => ['required', 'in:yes,no'],
            'previous_agent_details' => ['nullable', 'string', 'max:2000', 'required_if:previous_agent,yes'],
            'why_affiliate' => ['required', 'string', 'max:2000'],
            'acquisition_methods' => ['required', 'array', 'min:1'],
            'acquisition_methods.*' => ['string', 'max:40'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:whatsapp,instagram,tiktok,facebook,physical,business,other'],
            'social_profile_url' => ['nullable', 'url', 'max:255'],
            'monthly_reach' => ['required', 'in:1-10,11-30,31-50,51-100,100+'],
            'how_heard' => ['required', 'string', 'max:500'],
            'first_10_customers' => ['required', 'string', 'max:2000'],
            'registered_business' => ['required', 'in:yes,no'],
            'declaration_accepted' => ['accepted'],
            'conduct_accepted' => ['accepted'],
            'doc_brela' => [$isCompany ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate' => [$isCompany ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($isCompany && blank($data['business_name'] ?? null)) {
            return back()->withErrors(['business_name' => __('site.affiliate_apply.business_required')])->withInput();
        }

        if (! $isCompany && $registeredBusiness) {
            if (blank($data['business_name'] ?? null)) {
                return back()->withErrors(['business_name' => __('site.affiliate_apply.business_required')])->withInput();
            }
            if (blank($data['registration_number'] ?? null)) {
                return back()->withErrors(['registration_number' => __('site.partner_apply.registration_required')])->withInput();
            }
            if (blank($data['tin'] ?? null)) {
                return back()->withErrors(['tin' => __('site.partner_apply.tin_required')])->withInput();
            }
        }

        if ($data['applicant_category'] === 'individual' && ! $registeredBusiness) {
            $data['business_name'] = ($data['business_name'] ?? null) ?: $data['full_name'];
        }

        if (($data['occupation'] ?? '') === 'other' && filled($data['occupation_other'] ?? null)) {
            $data['occupation'] = $data['occupation_other'];
        }

        $acceptedAt = now()->toIso8601String();

        $application = app(PartnerEnrollmentService::class)->submitApplication(
            [
                ...$data,
                'partner_category' => 'affiliate',
                'type' => 'affiliate',
                'message' => $data['why_affiliate'],
                'payload' => [
                    'identity' => [
                        'date_of_birth' => $data['date_of_birth'],
                        'gender' => $data['gender'],
                        'phone_alt' => $data['phone_alt'] ?? null,
                        'district' => $data['district'],
                        'ward' => $data['ward'],
                    ],
                    'occupation' => $data['occupation'],
                    'sales_experience' => $data['sales_experience'],
                    'financial_services_experience' => $data['financial_services_experience'] ?? null,
                    'languages' => array_values($data['languages']),
                    'previous_agent' => $data['previous_agent'],
                    'previous_agent_details' => $data['previous_agent_details'] ?? null,
                    'why_affiliate' => $data['why_affiliate'],
                    'acquisition_methods' => array_values($data['acquisition_methods']),
                    'channels' => array_values($data['channels']),
                    'social_profile_url' => $data['social_profile_url'] ?? null,
                    'reach' => $data['monthly_reach'],
                    'monthly_reach' => $data['monthly_reach'],
                    'how_heard' => $data['how_heard'],
                    'first_10_customers' => $data['first_10_customers'],
                    'registered_business' => $data['registered_business'],
                    'declarations' => [
                        'applicant' => [
                            'accepted' => true,
                            'accepted_at' => $acceptedAt,
                            'full_name' => $data['full_name'],
                            'date_of_birth' => $data['date_of_birth'],
                            'gender' => $data['gender'],
                        ],
                        'conduct' => [
                            'version' => 'application_conduct_v1',
                            'accepted' => true,
                            'accepted_at' => $acceptedAt,
                        ],
                    ],
                ],
            ],
            $this->documentUploads($request),
        );

        $feeService = app(AffiliateApplicationFeePaymentService::class);
        if ($feeService->isRequired()) {
            $payment = $feeService->open($application);

            return redirect()->to($feeService->payUrl($payment));
        }

        return redirect()
            ->route('site.partners.apply.tracking', ['phone' => $application->phone])
            ->with('partner_submitted', true);
    }

    public function tracking(Request $request): View
    {
        $phone = PhoneNumber::fromRequest($request, 'phone')
            ?? trim((string) $request->query('phone', $request->input('phone', '')));
        $applications = collect();
        $enrolledPartner = null;

        if ($phone !== '') {
            $applications = \App\Models\PartnerApplication::query()
                ->with('partner')
                ->where(function ($q) use ($phone) {
                    PhoneNumber::constrain($q, 'phone', $phone);
                })
                ->latest()
                ->limit(10)
                ->get();

            $enrolledPartner = Vendor::query()
                ->where(function ($q) use ($phone) {
                    PhoneNumber::constrain($q, 'phone', $phone);
                })
                ->latest()
                ->first();
        }

        return view('site.partners.tracking', [
            'phone' => $phone,
            'applications' => $applications,
            'enrolledPartner' => $enrolledPartner,
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
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'coverage_regions' => ['nullable', 'array'],
            'coverage_regions.*' => ['string', 'max:100'],
            'requested_roles' => ['nullable', 'array'],
            'requested_roles.*' => ['string', 'in:debt_collector,auctioneer'],
            'message' => ['nullable', 'string', 'max:2000'],
            'doc_brela' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_tin_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_business_licence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_national_id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'doc_other' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'collection_conduct_accepted' => ['accepted'],
        ], [
            'collection_conduct_accepted.accepted' => __('site.partner_apply.conduct_required'),
        ]);

        $category = $enrollment->normalizeCategory($data['partner_category']);

        $locationDetail = collect([
            'District' => $data['district'] ?? null,
            'Ward' => $data['ward'] ?? null,
            'Street' => $data['street'] ?? null,
        ])->filter()->map(fn ($value, $label) => "{$label}: {$value}")->implode('; ');
        if ($locationDetail !== '') {
            $data['message'] = trim(($data['message'] ?? '')."\n".$locationDetail);
        }

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

        if ($category === 'debt_collector') {
            $roles = array_values(array_intersect(
                $data['requested_roles'] ?? [],
                ['debt_collector', 'auctioneer']
            ));
            if ($roles === []) {
                return back()->withErrors(['requested_roles' => __('site.partner_apply.capabilities_required')])->withInput();
            }
            $data['requested_roles'] = $roles;
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
