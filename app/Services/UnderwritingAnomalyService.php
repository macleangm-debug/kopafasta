<?php

namespace App\Services;

use App\Models\LoanApplication;

/**
 * Surface underwriting anomalies early so screening/committee decide faster.
 */
class UnderwritingAnomalyService
{
    /**
     * @return list<array{code: string, severity: string, title: string, detail: string}>
     */
    public function forApplication(LoanApplication $application, array $review = []): array
    {
        $application->loadMissing(['customer', 'product', 'documentRequests']);
        $customer = $application->customer;
        $crb = $review['crb'] ?? app(CrbCreditCheckService::class)->summaryForCustomer($customer, $application);
        $afford = $review['affordability'] ?? app(AffordabilityService::class)->evaluate($application);
        $risk = $review['risk'] ?? [];
        $profile = $review['profile'] ?? ['percent' => 0];
        $anomalies = [];

        $capacityAuto = app(CapacityAutoRejectService::class);
        if ($capacityAuto->isPending($application)) {
            $hours = $capacityAuto->hoursRemaining($application);
            $state = $capacityAuto->state($application) ?? [];
            $detail = ($hours === 0)
                ? __('borrower.loan_profile.capacity_auto_reject_pending_admin_due')
                : __('borrower.loan_profile.capacity_auto_reject_pending_admin', ['hours' => $hours ?? '—']);
            $detail .= ' Ask '.format_money((float) ($state['requested_amount'] ?? $application->requested_amount ?? 0))
                .' · installment '.format_money((float) ($state['proposed_installment'] ?? 0))
                .' · capacity '.format_money((float) ($state['available_capacity'] ?? 0)).'.';
            $anomalies[] = $this->item('capacity_auto_reject_pending', 'info', 'System sorted — awaiting auto-reject', $detail);
        }

        $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));
        if ($crbRec === 'reject') {
            $anomalies[] = $this->item('crb_reject', 'critical', 'CRB suggests reject', 'Bureau recommendation is reject — committee should treat as high risk unless screening explains otherwise.');
        } elseif ($crbRec === 'refer') {
            $anomalies[] = $this->item('crb_refer', 'warning', 'CRB suggests refer', 'Bureau recommends manual referral — verify income, debt, and documents carefully.');
        }

        if ((int) ($crb['delinquencies'] ?? 0) > 0) {
            $anomalies[] = $this->item('crb_delinquency', 'critical', 'Active delinquencies on CRB', (int) $crb['delinquencies'].' delinquency record(s) found.');
        }

        if (($afford['verdict'] ?? '') === 'fail' || ! ($afford['pass'] ?? true)) {
            $anomalies[] = $this->item('affordability_fail', 'critical', 'Affordability failed', $afford['reason'] ?? 'Proposed installment exceeds repayment capacity.');
        } elseif (($afford['verdict'] ?? '') === 'warn') {
            $anomalies[] = $this->item('affordability_warn', 'warning', 'Affordability near limit', $afford['reason'] ?? 'Repayment is close to the capacity ceiling.');
        }

        if (($risk['band'] ?? '') === 'high' || (int) ($risk['score'] ?? 0) >= 70) {
            $anomalies[] = $this->item('risk_high', 'warning', 'Elevated application risk', 'Risk score '.($risk['score'] ?? '—').' / 100'.(! empty($risk['label']) ? ' · '.$risk['label'] : '').'.');
        }

        if ($customer) {
            // Identity is reviewed on the screening desk (photo compare + re-upload requests).
            // Do not flag NIDA/face "verification procedure" status here.
            if (($customer->nida_verification_status ?? '') === 'name_mismatch') {
                $anomalies[] = $this->item('nida_mismatch', 'critical', 'NIDA name mismatch', 'Registered name does not match NIDA — confirm identity before approving.');
            }

            if ((int) ($profile['percent'] ?? 0) < 100) {
                $anomalies[] = $this->item('profile_incomplete', 'warning', 'Profile incomplete', 'Borrower profile is at '.(int) ($profile['percent'] ?? 0).'%.');
            }
        }

        $required = (int) ($review['required_docs'] ?? 0);
        $satisfied = (int) ($review['satisfied_docs'] ?? 0);
        if ($required > 0 && $satisfied < $required) {
            $anomalies[] = $this->item('documents_gap', 'warning', 'Document gaps', ($required - $satisfied).' required document(s) still missing or unverified.');
        }

        $openDocRequests = $application->documentRequests
            ->whereIn('status', ['pending', 'rejected'])
            ->count();
        if ($openDocRequests > 0) {
            $anomalies[] = $this->item('open_doc_requests', 'info', 'Open underwriting requests', $openDocRequests.' document / information request(s) still open with the borrower.');
        }

        if ($application->product?->requires_guarantor) {
            $guarantors = $review['guarantors'] ?? [];
            $accepted = collect($guarantors)->filter(fn ($g) => str_contains(strtolower((string) ($g['status'] ?? $g['label'] ?? '')), 'accept')
                || str_contains(strtolower((string) ($g['status'] ?? '')), 'approv'))->count();
            if ($accepted < 1) {
                $anomalies[] = $this->item('guarantor_pending', 'warning', 'Guarantor not confirmed', 'Product requires a guarantor and none is fully accepted yet.');
            }
        }

        if ((bool) data_get($application->screening_payload, 'recommendation_meta.differs_from_crb')) {
            $anomalies[] = $this->item('rec_differs_crb', 'info', 'Screening differs from CRB', 'Analyst recommendation does not match the bureau suggestion — read screening notes.');
        }

        $crossCheck = data_get($application->credit_appraisal_payload, 'crb_cross_check');
        if (is_array($crossCheck)) {
            foreach (array_merge($crossCheck['identity_flags'] ?? [], $crossCheck['credit_flags'] ?? []) as $flag) {
                if (! is_array($flag)) {
                    continue;
                }
                $code = (string) ($flag['code'] ?? 'crb_cross');
                // Avoid duplicating the high-level CRB reject/delinquency anomalies already added above.
                if (in_array($code, ['crb_reject', 'crb_refer', 'delinquencies'], true)) {
                    continue;
                }
                $anomalies[] = $this->item(
                    'crb_x_'.$code,
                    (string) ($flag['severity'] ?? 'warning'),
                    (string) ($flag['title'] ?? 'CRB cross-check'),
                    (string) ($flag['detail'] ?? '')
                );
            }
        }

        $severity = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($anomalies, fn ($a, $b) => ($severity[$a['severity']] ?? 9) <=> ($severity[$b['severity']] ?? 9));

        return $anomalies;
    }

    /** @return array{code: string, severity: string, title: string, detail: string} */
    private function item(string $code, string $severity, string $title, string $detail): array
    {
        return compact('code', 'severity', 'title', 'detail');
    }
}
