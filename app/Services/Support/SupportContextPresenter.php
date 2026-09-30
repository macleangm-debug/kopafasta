<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Repayment;
use App\Models\SupportConversation;
use App\Models\SupportTicket;

/**
 * Support-safe context for the right-hand conversation panel.
 */
class SupportContextPresenter
{
    /** @return array<string, mixed> */
    public function forConversation(SupportConversation $conversation): array
    {
        $customer = $conversation->customer;
        if (! $customer) {
            return [
                'kind' => 'guest',
                'name' => $conversation->guest_name ?: ($conversation->user?->name ?: 'Guest / Non-member'),
                'phone' => $conversation->guest_phone ?: ($conversation->user?->phone ?: null),
                'email' => $conversation->user?->email,
                'member_url' => null,
                'application' => null,
                'loan' => null,
                'payment' => null,
                'open_cases' => [],
                'previous_cases' => [],
            ];
        }

        return $this->forCustomer($customer);
    }

    /** @return array<string, mixed> */
    public function forCustomer(Customer $customer): array
    {
        $application = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', LoanApplication::PRE_SUBMIT_STATUSES)
            ->latest('id')
            ->first();

        $loan = Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring'])
            ->latest('id')
            ->first();

        $payment = null;
        if ($loan) {
            $payment = Repayment::query()
                ->where('loan_id', $loan->id)
                ->latest('paid_at')
                ->first();
        }

        $openCases = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->limit(5)
            ->get();

        $previousCases = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['resolved', 'closed'])
            ->latest()
            ->limit(5)
            ->get();

        return [
            'kind' => 'member',
            'name' => trim(($customer->first_name.' '.$customer->last_name)) ?: 'Member',
            'phone' => $customer->phone,
            'member_number' => $customer->customer_number,
            'member_url' => route('admin.customers.show', $customer),
            'application' => $application ? [
                'id' => $application->id,
                'number' => $application->application_number ?? ('APP-'.$application->id),
                'stage' => $application->current_stage,
                'status' => $application->status,
                'amount' => $application->requested_amount ?? $application->amount_requested ?? null,
                'url' => route('admin.loan-applications.show', $application),
            ] : null,
            'loan' => $loan ? [
                'id' => $loan->id,
                'number' => $loan->loan_number ?? ('LN-'.$loan->id),
                'outstanding' => $loan->outstanding_balance,
                'status' => $loan->status,
                'url' => route('admin.loans.show', $loan),
            ] : null,
            'payment' => $payment ? [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at?->format('d M Y H:i'),
                'url' => route('admin.loans.show', $loan),
            ] : null,
            'open_cases' => $openCases->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'url' => route('admin.support-tickets.show', $t),
            ])->all(),
            'previous_cases' => $previousCases->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'url' => route('admin.support-tickets.show', $t),
            ])->all(),
        ];
    }
}
