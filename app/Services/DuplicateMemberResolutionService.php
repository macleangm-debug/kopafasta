<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerDocument;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\User;
use App\Support\NationalIdValidator;
use App\Support\NidaNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DuplicateMemberResolutionService
{
    /**
     * @return array{
     *     empty: bool,
     *     payments: int,
     *     settled_payments: int,
     *     applications: int,
     *     drafts: int,
     *     loans: int,
     *     documents: int,
     *     assets: int,
     *     action: 'delete_empty'|'merge'
     * }
     */
    public function impact(Customer $duplicate): array
    {
        $payments = $duplicate->payments()->count();
        $settled = $duplicate->payments()->whereIn('status', ['paid', 'verified'])->count();
        $applications = $duplicate->applications()->count();
        $drafts = LoanApplicationDraft::query()->where('customer_id', $duplicate->id)->count();
        $loans = $duplicate->loans()->count();
        $documents = $duplicate->documents()->count();
        $assets = $duplicate->assets()->count();

        $empty = $payments === 0 && $applications === 0 && $drafts === 0
            && $loans === 0 && $documents === 0 && $assets === 0
            && $duplicate->guarantorInvitations()->count() === 0;

        return [
            'empty' => $empty,
            'payments' => $payments,
            'settled_payments' => $settled,
            'applications' => $applications,
            'drafts' => $drafts,
            'loans' => $loans,
            'documents' => $documents,
            'assets' => $assets,
            'action' => $empty ? 'delete_empty' : 'merge',
        ];
    }

    public function resolve(Customer $duplicate, Customer $canonical, User $actor, string $reason): Customer
    {
        if ((int) $duplicate->id === (int) $canonical->id) {
            throw ValidationException::withMessages([
                'canonical_customer_id' => 'Choose a different member as the canonical account.',
            ]);
        }

        if (filled($duplicate->merged_into_customer_id)) {
            throw ValidationException::withMessages([
                'duplicate' => 'This account is already merged.',
            ]);
        }

        $impact = $this->impact($duplicate);

        return DB::transaction(function () use ($duplicate, $canonical, $actor, $reason, $impact) {
            $snapshot = [
                'duplicate_id' => $duplicate->id,
                'canonical_id' => $canonical->id,
                'impact' => $impact,
                'phone' => $duplicate->phone,
                'member_no' => $duplicate->member_no,
                'customer_number' => $duplicate->customer_number,
                'national_id' => $duplicate->national_id,
            ];

            if ($impact['action'] === 'merge') {
                $this->reassignHistory($duplicate, $canonical);
            }

            $duplicate->update([
                'status' => 'inactive',
                'merged_into_customer_id' => $canonical->id,
                'merged_at' => now(),
                'merged_by_user_id' => $actor->id,
                'merge_reason' => $reason !== '' ? $reason : ($impact['action'] === 'delete_empty' ? 'empty_duplicate_deleted' : 'merged_into_canonical'),
                'merge_snapshot' => $snapshot,
            ]);

            if ($duplicate->user && (int) $duplicate->user_id !== (int) $canonical->user_id) {
                $duplicate->user->update(['is_active' => false]);
            }

            return $duplicate->fresh();
        });
    }

    public function otherCustomerWithNationalId(string $nationalId, ?int $exceptCustomerId = null): ?Customer
    {
        $formatted = NationalIdValidator::format($nationalId)
            ?? NidaNumber::format($nationalId)
            ?? trim($nationalId);
        if ($formatted === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $formatted);

        return Customer::query()
            ->where(function ($q) use ($formatted, $digits) {
                $q->where('national_id', $formatted);
                if (strlen((string) $digits) >= 10) {
                    $q->orWhereRaw("REPLACE(REPLACE(national_id, '-', ''), ' ', '') = ?", [$digits]);
                }
            })
            ->when($exceptCustomerId, fn ($q) => $q->where('id', '!=', $exceptCustomerId))
            ->whereNull('merged_into_customer_id')
            ->first();
    }

    private function reassignHistory(Customer $from, Customer $to): void
    {
        $this->reassignDraftsWithoutColliding($from, $to);

        foreach ([
            CustomerPayment::class,
            LoanApplication::class,
            Loan::class,
            CustomerDocument::class,
            CustomerAsset::class,
            CustomerDisbursementAccount::class,
        ] as $model) {
            $model::query()->where('customer_id', $from->id)->update(['customer_id' => $to->id]);
        }
    }

    /**
     * One live draft per customer+product. Do not let an unpaid duplicate draft
     * become the canonical apply spine when the paid application already lives there.
     */
    private function reassignDraftsWithoutColliding(Customer $from, Customer $to): void
    {
        $drafts = LoanApplicationDraft::query()->where('customer_id', $from->id)->get();
        $fees = app(ApplicationFeePaymentService::class);

        foreach ($drafts as $draft) {
            $productId = (int) ($draft->loan_product_id ?? 0);
            $canonicalDraft = $productId > 0
                ? LoanApplicationDraft::query()
                    ->where('customer_id', $to->id)
                    ->where('loan_product_id', $productId)
                    ->first()
                : null;
            $canonicalHasLivePaidApp = $productId > 0 && LoanApplication::query()
                ->where('customer_id', $to->id)
                ->where('loan_product_id', $productId)
                ->whereNotIn('status', LoanApplication::CLOSED_STATUSES)
                ->whereIn('application_fee_status', ['paid', 'waived', 'charged'])
                ->exists();
            $unpaid = ! in_array((string) data_get($draft->payload, 'application_fee.status'), ['paid', 'waived'], true);

            if ($canonicalDraft || ($canonicalHasLivePaidApp && $unpaid)) {
                $fees->abandonOpenFeePaymentsForDraft(
                    $from,
                    $productId ?: null,
                    (string) $draft->draft_reference,
                );
                $draft->delete();

                continue;
            }

            $draft->update(['customer_id' => $to->id]);
        }
    }
}
