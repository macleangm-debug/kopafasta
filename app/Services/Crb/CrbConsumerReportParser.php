<?php

namespace App\Services\Crb;

/**
 * Parses D&B / BOT consumer CIR XML into structured personal + credit sections
 * for admin underwriting (borrower & guarantor CRB tabs).
 */
class CrbConsumerReportParser
{
    /**
     * @return array{
     *   personal: array<string, mixed>,
     *   credit: array<string, mixed>,
     *   report_meta: array<string, mixed>
     * }
     */
    public function parse(?string $xml): array
    {
        $empty = $this->emptyPayload();
        if (! filled($xml)) {
            return $empty;
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if (! $doc) {
            return $empty;
        }

        $personal = $this->parsePersonal($doc);
        $credit = $this->parseCredit($doc);
        $meta = $this->parseReportMeta($doc);

        return [
            'personal' => $personal,
            'credit' => $credit,
            'report_meta' => $meta,
        ];
    }

    /** @return array{personal: array<string, mixed>, credit: array<string, mixed>, report_meta: array<string, mixed>} */
    public function emptyPayload(): array
    {
        return [
            'personal' => [
                'full_name' => null,
                'surname' => null,
                'first_name' => null,
                'middle_names' => null,
                'gender' => null,
                'date_of_birth' => null,
                'nationality' => null,
                'country_of_birth' => null,
                'district_of_birth' => null,
                'marital_status' => null,
                'number_of_spouses' => null,
                'spouses' => [],
                'number_of_children' => null,
                'education' => null,
                'profession' => null,
                'employer' => null,
                'mobile' => null,
                'address' => null,
                'ids' => [],
                'address_history' => [],
                'contact_history' => [],
                'employment_history' => [],
                'related_persons' => [],
            ],
            'credit' => [
                'score' => null,
                'risk_grade' => null,
                'recommendation' => 'refer',
                'existing_loans' => 0,
                'outstanding_balance' => 0.0,
                'delinquencies' => 0,
                'loan_history' => [],
                'overview' => [],
                'balances_by_currency' => [],
                'overdue_buckets' => [],
                'exposure_by_product' => [],
                'exposure_by_credit' => [],
                'open_accounts' => [],
                'closed_accounts' => [],
                'guaranteed_loans' => [],
                'insurance_accounts' => [],
                'inquiries_summary' => [],
                'inquiries' => [],
                'overdue_graph' => [],
                'disputes' => [],
                'most_negative_status' => null,
            ],
            'report_meta' => [
                'cir_number' => null,
                'ruid' => null,
                'ordered_at' => null,
                'institution_name' => null,
                'search_score' => null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function parseReportMeta(\SimpleXMLElement $doc): array
    {
        $report = $doc->ReportDetails->ReportDetails ?? null;
        $search = $doc->SearchDetails->SearchDetails ?? null;

        return [
            'cir_number' => $this->text($report?->CIR_NUMBER),
            'ruid' => $this->text($report?->RUID),
            'ordered_at' => $this->text($report?->REPORT_ORDER_DATE),
            'institution_name' => $this->text($report?->INSTITUTION_NAME),
            'search_score' => $this->text($search?->SEARCH_SCORE),
        ];
    }

    /** @return array<string, mixed> */
    private function parsePersonal(\SimpleXMLElement $doc): array
    {
        $details = $doc->Cons_CommDetails->Cons_CommDetails
            ?? $doc->SearchDetails->SearchDetails
            ?? null;

        $fullName = $this->text($details?->ENTITY_NAME_EN)
            ?? $this->text($details?->NAME);
        $surname = $this->text($details?->SURNAME);
        $first = $this->text($details?->FIRST_NAME) ?? $this->text($details?->FIRSTNAME);
        $middle = $this->text($details?->MIDDLE_NAMES) ?? $this->text($details?->MIDDLENAME);

        if ((! $first || ! $surname) && filled($fullName)) {
            $parts = preg_split('/\s+/', trim($fullName)) ?: [];
            if (count($parts) >= 2) {
                $surname = $surname ?: array_pop($parts);
                $first = $first ?: array_shift($parts);
                $middle = $middle ?: (count($parts) ? implode(' ', $parts) : null);
            }
        }

        $spouses = $this->collectSpouses($doc, $details);
        $related = $this->mapChildren($doc->RelatedPersonsDetails ?? null, 'RelatedPersonsDetails', function (\SimpleXMLElement $row) {
            return array_filter([
                'name' => $this->text($row->NAME ?? $row->FULL_NAME ?? $row->ENTITY_NAME_EN),
                'relation' => $this->text($row->RELATION ?? $row->RELATION_TYPE ?? $row->RELATIONSHIP),
                'national_id' => $this->text($row->ID_NUMBER ?? $row->NATIONAL_ID),
            ], fn ($v) => $v !== null && $v !== '');
        });

        return [
            'full_name' => $fullName,
            'surname' => $surname,
            'first_name' => $first,
            'middle_names' => $middle,
            'gender' => $this->text($details?->GENDER),
            'date_of_birth' => $this->text($details?->DATE_OF_BIRTH ?? $details?->DATEOFBIRTH),
            'nationality' => $this->text($details?->NATIONALITY),
            'country_of_birth' => $this->text($details?->COUNTRY_OF_BIRTH),
            'district_of_birth' => $this->text($details?->DISOFBIRTH ?? $details?->DISTRICT_OF_BIRTH),
            'marital_status' => $this->text($details?->MARITAL_STATUS),
            'number_of_spouses' => $this->intOrNull($details?->NUMBER_OF_SPOUSES ?? $details?->NO_OF_SPOUSES),
            'spouses' => $spouses,
            'number_of_children' => $this->intOrNull($details?->NUMBER_OF_CHILDREN ?? $details?->NO_OF_CHILDREN),
            'education' => $this->text($details?->EDUCATION),
            'profession' => $this->text($details?->PROFESSION ?? $details?->OCCUPATION),
            'employer' => $this->text($details?->EMPLOYER ?? $details?->EMPLOYER_NAME),
            'mobile' => $this->text($details?->MOBILE ?? $details?->MOBILE_NO),
            'address' => $this->text($details?->ADDRESS),
            'ids' => $this->mapChildren($doc->GetIdDetails ?? null, 'GetIdDetails', function (\SimpleXMLElement $row) {
                return array_filter([
                    'id_number' => $this->text($row->ID_NUMBER),
                    'id_type' => $this->text($row->ID_TYPE),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'address_history' => $this->mapChildren($doc->AddressHistory ?? null, 'AddressHistory', function (\SimpleXMLElement $row) {
                return array_filter([
                    'type' => $this->text($row->ADDRESS_TYPE),
                    'address' => $this->text($row->ADDRESS),
                    'date_reported' => $this->text($row->DATE_REPORTED),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'contact_history' => $this->mapChildren($doc->ContactHistory ?? null, 'ContactHistory', function (\SimpleXMLElement $row) {
                return array_filter([
                    'type' => $this->text($row->CONTACT_TYPE),
                    'detail' => $this->text($row->CONTACT_DETAIL_EN ?? $row->CONTACT_DETAIL),
                    'date_reported' => $this->text($row->DATEREPORTED ?? $row->DATE_REPORTED),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'employment_history' => $this->mapChildren($doc->EmploymentHistory ?? null, 'EmploymentHistory', function (\SimpleXMLElement $row) {
                return array_filter([
                    'employer' => $this->text($row->EMPLOYER ?? $row->EMPLOYER_NAME),
                    'profession' => $this->text($row->PROFESSION ?? $row->OCCUPATION),
                    'date_reported' => $this->text($row->DATE_REPORTED ?? $row->DATEREPORTED),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'related_persons' => $related,
        ];
    }

    /**
     * @param  \SimpleXMLElement|null  $details
     * @return list<array{name?: string}>
     */
    private function collectSpouses(\SimpleXMLElement $doc, ?\SimpleXMLElement $details): array
    {
        $spouses = [];

        foreach (['SPOUSE_FULL_NAME', 'SPOUSE_NAME', 'SPOUSE'] as $tag) {
            $val = $this->text($details?->{$tag} ?? null);
            if ($val) {
                $spouses[] = ['name' => $val];
            }
        }

        if (isset($details->SpouseFullNameList)) {
            foreach ($details->SpouseFullNameList as $row) {
                $name = $this->text($row->SpouseFullName ?? $row->SPOUSE_FULL_NAME ?? $row);
                if ($name) {
                    $spouses[] = ['name' => $name];
                }
            }
        }

        return array_values(array_unique($spouses, SORT_REGULAR));
    }

    /** @return array<string, mixed> */
    private function parseCredit(\SimpleXMLElement $doc): array
    {
        $overviewNode = $doc->CreditProfileOverview->CreditProfileOverview ?? null;
        $balancesNode = $doc->CreditProfileOverview_Curr->CreditProfileOverview_Curr ?? null;

        $openAccounts = $this->mapChildren($doc->OpenAccounts ?? null, 'OpenAccounts', function (\SimpleXMLElement $row) {
            return [
                'lender' => $this->text($row->BANK),
                'product' => $this->text($row->PRODUCTDESC ?? $row->PRODUCTDESCLOCAL),
                'purpose' => $this->text($row->PURPOSE),
                'currency' => $this->text($row->CURRENCY),
                'approval_amount' => $this->money($row->APPROVALAMT),
                'outstanding' => $this->money($row->OUTSTANDING_RESIDUAL),
                'overdue' => $this->money($row->OVERDUEAMT),
                'used_amount' => $this->money($row->USED_AMOUNT),
                'installment_amount' => $this->money($row->INSTALLMENTAMT),
                'installments_total' => $this->intOrNull($row->NUM_OF_INSTALLMENTS),
                'installments_left' => $this->intOrNull($row->NUM_OF_INSTALLMENTS_LEFT),
                'overdue_installments' => $this->intOrNull($row->NO_OF_OVERDUE_INSTALLMENTS),
                'activated_date' => $this->text($row->ACTIVATEDDATE),
                'maturity_date' => $this->text($row->MATURITYDATE),
                'last_payment_date' => $this->text($row->LASTPAYMENTDATE),
                'reported_date' => $this->text($row->REPORTEDDT),
                'negative_status' => $this->text($row->NEGATIVESTATUS),
                'client_negative_status' => $this->text($row->NEGATIVESTATUS_CLIENT),
                'periodic_payment' => $this->text($row->PERIODIC_PAYMENT),
                'installment_type' => $this->text($row->INSTALLMENTTYPE),
                'rescheduled' => $this->text($row->RESCHEDULED_LOAN),
                'economy_sector' => $this->text($row->ECONOMYSECTOR),
                'status' => 'open',
                'balance' => $this->money($row->OUTSTANDING_RESIDUAL),
            ];
        });

        $closedAccounts = $this->mapChildren($doc->ClosedAccounts ?? null, 'ClosedAccounts', function (\SimpleXMLElement $row) {
            return [
                'lender' => $this->text($row->BANK),
                'product' => $this->text($row->CFTYPE),
                'sanction_amount' => $this->money($row->SANCTION_AMT),
                'overdue' => $this->money($row->OVER_DUE_AMT ?? $row->OVER_DUE_AMOUNT),
                'days_in_arrears' => $this->intOrNull($row->NUM_OF_DAYS_IN_ARREARS),
                'activated_date' => $this->text($row->ACTIVATED_DT),
                'closure_date' => $this->text($row->CLOSURE_DT),
                'phase' => $this->text($row->LOANPHASE_CD),
                'case_status' => $this->text($row->CASETYPE_DESC_EN),
                'loan_status' => $this->text($row->LOAN_STATUS_DESC_EN),
                'reported_date' => $this->text($row->LASTREPORTEDDT),
                'status' => 'closed',
                'balance' => 0.0,
            ];
        });

        $loanHistory = [];
        foreach ($openAccounts as $acc) {
            $loanHistory[] = [
                'lender' => $acc['lender'] ?? 'Other lender',
                'status' => 'open',
                'product' => $acc['product'] ?? null,
                'balance' => (float) ($acc['outstanding'] ?? 0),
                'overdue' => (float) ($acc['overdue'] ?? 0),
            ];
        }
        foreach ($closedAccounts as $acc) {
            $loanHistory[] = [
                'lender' => $acc['lender'] ?? 'Other lender',
                'status' => 'closed',
                'product' => $acc['product'] ?? null,
                'balance' => 0.0,
                'overdue' => (float) ($acc['overdue'] ?? 0),
            ];
        }

        $outstanding = $this->money($balancesNode?->TZS_TOTAL_BALANCE);
        if ($outstanding <= 0) {
            $outstanding = array_sum(array_map(fn ($a) => (float) ($a['outstanding'] ?? 0), $openAccounts));
        }

        $pastDue = $this->money($balancesNode?->TZS_TOTAL_AMT_PASTDUE);
        $delinquencies = (int) ($this->intOrNull($overviewNode?->UNPAIDINSTAL30DAYSOD) ?? 0)
            + (int) ($this->intOrNull($overviewNode?->UNPAIDINSTAL60DAYSOD) ?? 0)
            + (int) ($this->intOrNull($overviewNode?->UNPAIDINSTAL360DAYSOD) ?? 0);
        if ($delinquencies === 0 && $pastDue > 0) {
            $delinquencies = 1;
        }

        $existingLoans = (int) ($this->intOrNull($overviewNode?->ACCCOUNT) ?? count($openAccounts));

        return [
            'score' => null,
            'risk_grade' => null,
            'recommendation' => 'refer',
            'existing_loans' => $existingLoans,
            'outstanding_balance' => $outstanding,
            'delinquencies' => $delinquencies,
            'loan_history' => $loanHistory,
            'overview' => array_filter([
                'accounts' => $this->intOrNull($overviewNode?->ACCCOUNT),
                'creditors' => $this->intOrNull($overviewNode?->NOOFCRSTOWHOMSUBJECTISINDEBTED),
                'collateral_count' => $this->intOrNull($overviewNode?->COLLATERALCOUNT),
                'negative_status_reported_date' => $this->text($overviewNode?->NEGSTATUSREPORTEDDATE),
                'paid_off_last_30_days' => $this->intOrNull($overviewNode?->NOOFPAIDOFFACSINPAST30DAYS),
                'unpaid_instal_30' => $this->intOrNull($overviewNode?->UNPAIDINSTAL30DAYSOD),
                'unpaid_instal_60' => $this->intOrNull($overviewNode?->UNPAIDINSTAL60DAYSOD),
                'unpaid_instal_360' => $this->intOrNull($overviewNode?->UNPAIDINSTAL360DAYSOD),
                'inquiries_by_fa' => $this->intOrNull($overviewNode?->NUM_OF_INQUIRIES_BY_FA),
                'legal_dispute_accounts' => $this->intOrNull($overviewNode?->NOOFACCSUNDERLEGALDISPUTE),
                'loans_guaranteed' => $this->intOrNull($overviewNode?->NUMOFLOANSGUARANTEED),
                'most_negative_status' => $this->text($overviewNode?->MOSTNEGSTATUS),
            ], fn ($v) => $v !== null && $v !== ''),
            'balances_by_currency' => array_filter([
                'TZS' => [
                    'balance' => $this->money($balancesNode?->TZS_TOTAL_BALANCE),
                    'past_due' => $this->money($balancesNode?->TZS_TOTAL_AMT_PASTDUE),
                ],
                'USD' => [
                    'balance' => $this->money($balancesNode?->USD_TOTAL_BALANCE),
                    'past_due' => $this->money($balancesNode?->USD_TOTAL_AMT_PASTDUE),
                ],
                'EUR' => [
                    'balance' => $this->money($balancesNode?->EUR_TOTAL_BALANCE),
                    'past_due' => $this->money($balancesNode?->EUR_TOTAL_AMT_PASTDUE),
                ],
                'GBP' => [
                    'balance' => $this->money($balancesNode?->GBP_TOTAL_BALANCE),
                    'past_due' => $this->money($balancesNode?->GBP_TOTAL_AMT_PASTDUE),
                ],
                'AED' => [
                    'balance' => $this->money($balancesNode?->AED_TOTAL_BALANCE),
                    'past_due' => $this->money($balancesNode?->AED_TOTAL_AMT_PASTDUE),
                ],
                'OTHER' => [
                    'balance' => $this->money($balancesNode?->OTHER_TOTAL_BALANCE),
                    'past_due' => $this->money($balancesNode?->OTHER_TOTAL_AMT_PASTDUE),
                ],
            ], fn ($row) => ((float) ($row['balance'] ?? 0)) > 0 || ((float) ($row['past_due'] ?? 0)) > 0),
            'overdue_buckets' => $this->mapChildren($doc->Amount_OD_Bucket ?? null, 'Amount_OD_Bucket', function (\SimpleXMLElement $row) {
                return [
                    'bucket' => $this->text($row->BUCKET),
                    'amount' => $this->money($row->OVERDUE_HISTORY_AMOUNT),
                ];
            }),
            'exposure_by_product' => $this->mapChildren($doc->CreditExposurebyProduct ?? null, 'CreditExposurebyProduct', function (\SimpleXMLElement $row) {
                return [
                    'product' => $this->text($row->PRODUCT),
                    'currency' => $this->text($row->CURRENCY),
                    'amount_overdue' => $this->money($row->AMOUNTOVERDUE),
                    'not_overdue' => $this->money($row->NOTOVERDUE),
                    'active_facilities' => $this->intOrNull($row->NOOFACTIVECFS),
                ];
            }),
            'exposure_by_credit' => $this->mapChildren($doc->CreditExposurebyCredit ?? null, 'CreditExposurebyCredit', function (\SimpleXMLElement $row) {
                return [
                    'product' => $this->text($row->PRODUCT),
                    'currency' => $this->text($row->CURRENCY),
                    'liability' => $this->text($row->LIABILITY),
                    'total_balance' => $this->money($row->TOTAL_BALANCE_AMT),
                ];
            }),
            'open_accounts' => $openAccounts,
            'closed_accounts' => $closedAccounts,
            'guaranteed_loans' => $this->mapChildren($doc->GuaranteedLoanDetails ?? null, 'GuaranteedLoanDetails', function (\SimpleXMLElement $row) {
                return array_filter([
                    'lender' => $this->text($row->BANK),
                    'product' => $this->text($row->PRODUCTDESC ?? $row->CFTYPE),
                    'amount' => $this->money($row->APPROVALAMT ?? $row->SANCTION_AMT),
                    'outstanding' => $this->money($row->OUTSTANDING_RESIDUAL ?? $row->TOTAL_BALANCE_AMT),
                    'status' => $this->text($row->NEGATIVESTATUS ?? $row->LOAN_STATUS_DESC_EN),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'insurance_accounts' => $this->mapChildren($doc->InsuranceAccounts ?? null, 'InsuranceAccounts', function (\SimpleXMLElement $row) {
                return array_filter([
                    'provider' => $this->text($row->BANK ?? $row->INSURER),
                    'product' => $this->text($row->PRODUCTDESC ?? $row->PRODUCT),
                    'status' => $this->text($row->STATUS ?? $row->NEGATIVESTATUS),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'inquiries_summary' => $this->mapChildren($doc->InquiryHistorySummary ?? null, 'InquiryHistorySummary', function (\SimpleXMLElement $row) {
                return array_filter([
                    'institution_type' => $this->text($row->INSTITUTION_TYPE),
                    'count' => $this->intOrNull($row->NUM_OF_INQUIRIES),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'inquiries' => $this->mapChildren($doc->InquiryHistoryDetails ?? null, 'InquiryHistoryDetails', function (\SimpleXMLElement $row) {
                return array_filter([
                    'date' => $this->text($row->INQRYDATE),
                    'purpose' => $this->text($row->INQPURPOSE),
                    'institution_type' => $this->text($row->INSTITUTION_TYPE),
                    'amount' => $this->money($row->AMOUNT),
                    'currency' => $this->text($row->CURRENCY),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'overdue_graph' => $this->mapChildren($doc->OverdueGraph ?? null, 'OverdueGraph', function (\SimpleXMLElement $row) {
                return [
                    'month' => $this->text($row->MONTHYEAR),
                    'no_negative' => $this->intOrNull($row->NONEGATIVESTATUS),
                    'increased_risk' => $this->intOrNull($row->INCREASEDRISK),
                    'fraud' => $this->intOrNull($row->FRAUDTOWARDSBANK),
                    'blocked' => $this->intOrNull($row->BLOCKED),
                    'other' => $this->intOrNull($row->OTHERSTATUS),
                ];
            }),
            'disputes' => $this->mapChildren($doc->CreditDisputeDetails ?? null, 'CreditDisputeDetails', function (\SimpleXMLElement $row) {
                return array_filter([
                    'detail' => $this->text($row->DETAIL ?? $row->DESCRIPTION),
                    'date' => $this->text($row->DATE ?? $row->DATE_REPORTED),
                    'status' => $this->text($row->STATUS),
                ], fn ($v) => $v !== null && $v !== '');
            }),
            'most_negative_status' => $this->text($overviewNode?->MOSTNEGSTATUS),
        ];
    }

    /**
     * @param  callable(\SimpleXMLElement): array<string, mixed>  $mapper
     * @return list<array<string, mixed>>
     */
    private function mapChildren(?\SimpleXMLElement $parent, string $childName, callable $mapper): array
    {
        if (! $parent) {
            return [];
        }

        $out = [];
        foreach ($parent->{$childName} as $child) {
            $row = $mapper($child);
            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function text(mixed $node): ?string
    {
        if ($node === null) {
            return null;
        }
        $value = trim((string) $node);
        if ($value === '' || strcasecmp($value, 'NA') === 0 || strcasecmp($value, 'Not Available') === 0 || $value === '-') {
            return null;
        }

        return $value;
    }

    private function money(mixed $node): float
    {
        $raw = $this->text($node);
        if ($raw === null) {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $raw) ?? '0';

        return (float) $clean;
    }

    private function intOrNull(mixed $node): ?int
    {
        $raw = $this->text($node);
        if ($raw === null || ! is_numeric(preg_replace('/[^0-9.\-]/', '', $raw))) {
            return null;
        }

        return (int) round((float) preg_replace('/[^0-9.\-]/', '', $raw));
    }
}
