<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(): View
    {
        return view('site.feedback.index', [
            'categories' => $this->categories(),
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

        $customerId = auth()->user()?->customer?->id;
        $description = trim(collect([
            $validated['message'],
            filled($validated['reference'] ?? null) ? __('site.feedback.reference_label').': '.$validated['reference'] : null,
            filled($validated['phone'] ?? null) ? __('site.feedback.phone_label').': '.$validated['phone'] : null,
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
            SupportTicket::create([
                'ticket_number' => 'TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
                'customer_id' => $customerId,
                'subject' => $validated['subject'],
                'description' => $description,
                'priority' => in_array($validated['category'], ['technical', 'complaint'], true) ? 'high' : 'normal',
                'status' => 'open',
                'category' => $validated['category'],
            ]);
        }

        return redirect()
            ->route('site.feedback')
            ->with('status', __('site.feedback.success'));
    }

    /** @return array<string, array{label: string, description: string, fields: list<string>}> */
    private function categories(): array
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
            'investment_inquiry' => [
                'label' => __('site.feedback.categories.investment_inquiry'),
                'description' => __('site.feedback.categories.investment_inquiry_desc'),
                'fields' => ['subject', 'message'],
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
