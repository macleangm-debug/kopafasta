<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\Support\SupportTicketService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $tickets,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $from = (string) request('from', '');
        $inBorrowerShell = $user && ($user->customer || $from === 'borrower');
        $inPartnerShell = $user && ! $user->customer && ($user->vendor || $from === 'partner');

        if ($inBorrowerShell) {
            return view('site.borrower.feedback', [
                'categories' => $this->categories(),
                'customer' => $user->customer,
                'supportHome' => route('site.borrower.support'),
            ]);
        }

        if ($inPartnerShell) {
            return view('site.partner.feedback', [
                'categories' => $this->categories(),
                'supportHome' => Route::has('site.partner.support')
                    ? route('site.partner.support')
                    : route('site.vendor.support'),
            ]);
        }

        return view('site.feedback.index', [
            'categories' => $this->categories(),
            'openOnLoad' => request()->boolean('open') || old('category') || session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $authenticated = (bool) $user;
        $categories = array_keys($this->categories());

        $rules = [
            'category' => ['required', 'string', 'in:'.implode(',', $categories)],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'string', 'max:32'],
        ];

        if ($authenticated) {
            $rules['name'] = ['nullable', 'string', 'max:120'];
            $rules['email'] = ['nullable', 'email', 'max:150'];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        } else {
            $rules['name'] = ['required', 'string', 'max:120'];
            $rules['email'] = ['nullable', 'email', 'max:150'];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        $validated = $request->validate($rules);

        $customer = $user?->customer;
        $name = $authenticated
            ? (trim((string) ($customer?->first_name.' '.$customer?->last_name)) ?: (string) $user->name)
            : $validated['name'];
        $email = $authenticated
            ? (operator_email_display($user->email) !== '—' ? $user->email : ($validated['email'] ?? null))
            : ($validated['email'] ?? null);
        $phone = $authenticated
            ? ($customer?->phone ?: $user->phone ?: PhoneNumber::fromRequest($request, 'phone', 'TZ'))
            : PhoneNumber::fromRequest($request, 'phone', 'TZ');

        $customerId = $customer?->id;
        $description = trim(collect([
            $validated['message'],
            filled($validated['reference'] ?? null) ? __('site.feedback.reference_label').': '.$validated['reference'] : null,
            (! $authenticated && filled($phone)) ? __('site.feedback.phone_label').': '.$phone : null,
            (! $authenticated && filled($email)) ? __('site.feedback.email_label').': '.$email : null,
        ])->filter()->join("\n\n"));

        // Feedback record only — never auto-convert compliment/suggestion into operational case work.
        // Complaints may create a Complaint record; Support decides if a Case is needed.
        if ($validated['category'] === 'complaint') {
            Complaint::create([
                'complaint_number' => 'CMP-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
                'customer_id' => $customerId,
                'subject' => $validated['subject'],
                'description' => $description,
                'severity' => 'moderate',
                'status' => 'received',
                'channel' => $authenticated ? 'in_app' : 'website',
            ]);
        } else {
            $this->tickets->create([
                'customer_id' => $customerId,
                'guest_name' => $customerId ? null : $name,
                'guest_email' => $customerId ? null : $email,
                'guest_phone' => $customerId ? null : $phone,
                'contact_kind' => $customerId ? 'customer' : 'guest',
                'source' => 'public_feedback',
                'subject' => '['.ucfirst(str_replace('_', ' ', $validated['category'])).'] '.$validated['subject'],
                'description' => $description,
                'priority' => $validated['category'] === 'technical' ? 'high' : 'normal',
                'priority_locked' => false,
                'status' => 'open',
                'category' => $validated['category'],
                'actor' => $user,
            ]);
        }

        $from = (string) ($validated['from'] ?? $request->query('from', ''));
        if ($customerId || $from === 'borrower') {
            return redirect()
                ->route('site.borrower.support')
                ->with('status', __('site.feedback.success'));
        }
        if ($from === 'partner' || ($user && ! $customerId && method_exists($user, 'vendor') && $user->vendor)) {
            $home = \Illuminate\Support\Facades\Route::has('site.partner.support')
                ? 'site.partner.support'
                : 'site.vendor.support';

            return redirect()
                ->route($home)
                ->with('status', __('site.feedback.success'));
        }

        return redirect()
            ->route('site.feedback', ['open' => 1])
            ->with('status', __('site.feedback.success'));
    }

    /** @return array<string, array{label: string, description: string, fields: list<string>}> */
    public function categories(): array
    {
        return [
            'complaint' => [
                'label' => __('site.feedback.categories.complaint'),
                'description' => __('site.feedback.categories.complaint_desc'),
                'fields' => ['subject', 'message', 'reference'],
            ],
            'suggestion' => [
                'label' => __('site.feedback.categories.suggestion'),
                'description' => __('site.feedback.categories.suggestion_desc'),
                'fields' => ['subject', 'message'],
            ],
            'technical' => [
                'label' => __('site.feedback.categories.technical'),
                'description' => __('site.feedback.categories.technical_desc'),
                'fields' => ['subject', 'message', 'reference'],
            ],
            'loan_inquiry' => [
                'label' => __('site.feedback.categories.loan_inquiry'),
                'description' => __('site.feedback.categories.loan_inquiry_desc'),
                'fields' => ['subject', 'message', 'reference'],
            ],
            'general' => [
                'label' => __('site.feedback.categories.general'),
                'description' => __('site.feedback.categories.general_desc'),
                'fields' => ['subject', 'message'],
            ],
            'compliment' => [
                'label' => __('site.feedback.categories.compliment'),
                'description' => __('site.feedback.categories.compliment_desc'),
                'fields' => ['subject', 'message'],
            ],
        ];
    }
}
