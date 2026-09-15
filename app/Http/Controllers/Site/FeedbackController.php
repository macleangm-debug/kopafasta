<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\Support\SupportTicketService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $tickets,
    ) {}

    public function index(): View
    {
        return view('site.feedback.index', [
            'categories' => $this->categories(),
            'openOnLoad' => request()->boolean('open') || old('category') || session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $categories = array_keys($this->categories());
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', $categories)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        $phone = PhoneNumber::fromRequest($request, 'phone', 'TZ');
        $validated['phone'] = $phone;

        $customerId = auth()->user()?->customer?->id;
        $description = trim(collect([
            $validated['message'],
            filled($validated['reference'] ?? null) ? __('site.feedback.reference_label').': '.$validated['reference'] : null,
            filled($phone) ? __('site.feedback.phone_label').': '.$phone : null,
            filled($validated['email'] ?? null) ? __('site.feedback.email_label').': '.$validated['email'] : null,
        ])->filter()->join("\n\n"));

        if ($validated['category'] === 'complaint') {
            Complaint::create([
                'complaint_number' => 'CMP-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
                'customer_id' => $customerId,
                'subject' => $validated['subject'],
                'description' => $description,
                'severity' => 'moderate',
                'status' => 'received',
                'channel' => 'website',
            ]);
        } else {
            $priority = in_array($validated['category'], ['technical', 'complaint'], true) ? 'high' : 'normal';

            $this->tickets->create([
                'customer_id' => $customerId,
                'guest_name' => $customerId ? null : $validated['name'],
                'guest_email' => $customerId ? null : ($validated['email'] ?? null),
                'guest_phone' => $customerId ? null : $phone,
                'contact_kind' => $customerId ? 'customer' : 'guest',
                'source' => 'public_feedback',
                'subject' => $validated['subject'],
                'description' => $description,
                'priority' => $priority,
                'priority_locked' => false,
                'status' => 'open',
                'category' => $validated['category'],
                'actor' => auth()->user(),
            ]);
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
