# Finance posting rules

Internal reference for how KopaFasta posts transactions to the general ledger (GL).

## Configuration

Configure default GL accounts under **Admin → Settings → Finance defaults**:

| Setting key | Purpose |
|-------------|---------|
| `finance.cash_gl_account_id` | Cash/bank offset for disbursements, repayments, expenses |
| `finance.loan_receivable_gl_account_id` | Loan principal receivable |
| `finance.interest_income_gl_account_id` | Interest income on repayments |
| `finance.penalty_income_gl_account_id` | Penalty/late fee income |
| `finance.default_expense_gl_account_id` | Fallback expense account |

Individual **Charges & fees** rows can specify `gl_account_id` for fee income at disbursement.

Chart of accounts: **Admin → Finance → Chart of accounts**. Each account should have a clear type (asset, liability, income, expense).

## Automatic posting flows

### Loan disbursement

**Service:** `LoanDisbursementService::postDisbursementJournal`

| Debit | Credit |
|-------|--------|
| Loan receivable (approved principal) | Cash/bank (net disbursed) |
| | Fee income accounts (per deducted fees with GL link) |

Fees marked `deducted_from_principal` reduce the cash credit. Posting is skipped if cash or receivable GL is not configured.

### Loan repayment allocation

**Service:** `RepaymentPostingService::postJournal`

| Debit | Credit |
|-------|--------|
| Cash/bank (full repayment amount) | Loan receivable (principal component) |
| | Interest income (interest component) |
| | Penalty income (penalty component) |

If interest/penalty GL is missing, the balancing amount posts to loan receivable so the entry remains balanced.

### Expenses

**Service:** `ExpensePostingService::post`

| Debit | Credit |
|-------|--------|
| Expense GL (line or default) | Cash/bank |

### Late fees

**Service:** `LateFeeAccrualService` — accrues late fees and posts to configured income/receivable accounts when GL is available.

### Loan write-off

**Service:** `LoanWriteOffService` — posts write-off from receivable to expense/allowance per write-off rules.

## Idempotency

Journal entries store `source_type` and `source_id` (e.g. `Repayment`, `Loan`, `Expense`). Services check for an existing entry before posting again.

## Manual review

**Admin → Finance → Journal entries** lists all posted entries with debit/credit totals.

Trial balance, income statement, and balance sheet reports read from posted journal lines.

## Troubleshooting

- **No journal on disbursement:** Verify cash and loan receivable GL IDs in finance settings.
- **Repayment posts all to receivable:** Set interest and penalty income GL accounts.
- **Fee not credited at disbursement:** Ensure the charge/fee row has `gl_account_id` and is active.

See also: `resources/views/admin/settings/finance.blade.php` for the admin configuration UI.
