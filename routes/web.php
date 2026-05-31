<?php

use App\Http\Controllers\Admin\AmlRuleController;
use App\Http\Controllers\Admin\ApprovalLimitController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\BlacklistEntryController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ChargesFeeController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerKycController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DisbursementMethodController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinanceReportsController;
use App\Http\Controllers\Admin\FundingPoolController;
use App\Http\Controllers\Admin\GuarantorController;
use App\Http\Controllers\Admin\LenderController;
use App\Http\Controllers\Admin\LenderInvestmentController;
use App\Http\Controllers\Admin\LoanApplicationController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\MembershipPaymentController;
use App\Http\Controllers\Admin\MobileMoneyAccountController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\PepFlagController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\RepaymentController;
use App\Http\Controllers\Admin\RepaymentMethodController;
use App\Http\Controllers\Admin\RiskScoringRuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SuspiciousActivityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\WriteOffRuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site (kopafasta.com style)
|--------------------------------------------------------------------------
*/
Route::name('site.')->group(function () {
    Route::get('/',                 [\App\Http\Controllers\Site\PageController::class, 'home'])->name('home');
    Route::get('/loans',            [\App\Http\Controllers\Site\PageController::class, 'products'])->name('products');
    Route::get('/loans/product/{code}', [\App\Http\Controllers\Site\PageController::class, 'product'])->name('product');
    Route::get('/how-it-works',     [\App\Http\Controllers\Site\PageController::class, 'howItWorks'])->name('how-it-works');
    Route::get('/about',            [\App\Http\Controllers\Site\PageController::class, 'about'])->name('about');
    Route::get('/faq',              [\App\Http\Controllers\Site\PageController::class, 'faq'])->name('faq');
    Route::get('/invest',           [\App\Http\Controllers\Site\PageController::class, 'invest'])->name('invest');
    Route::get('/capital-partners', [\App\Http\Controllers\Site\PageController::class, 'capitalPartners'])->name('capital-partners');

    // Guest auth (explicit web guard)
    Route::middleware('guest:web')->group(function () {
        Route::get('/login',  [\App\Http\Controllers\Site\AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [\App\Http\Controllers\Site\AuthController::class, 'login'])->name('login.post');

        Route::get('/register',          fn () => view('site.auth.register-choose'))->name('register');
        Route::get('/register/borrower', [\App\Http\Controllers\Site\AuthController::class, 'showRegisterBorrower'])->name('register.borrower');
        Route::post('/register/borrower',[\App\Http\Controllers\Site\AuthController::class, 'registerBorrower'])->name('register.borrower.post');
        Route::post('/waitlist',          [\App\Http\Controllers\Site\AuthController::class, 'storeWaitlistRequest'])->name('waitlist.store');
        Route::get('/register/vendor',   [\App\Http\Controllers\Site\AuthController::class, 'showRegisterVendor'])->name('register.vendor');
        Route::post('/register/vendor',  [\App\Http\Controllers\Site\AuthController::class, 'registerVendor'])->name('register.vendor.post');
        Route::get('/register/investor', [\App\Http\Controllers\Site\AuthController::class, 'showRegisterInvestor'])->name('register.investor');
        Route::post('/register/investor',[\App\Http\Controllers\Site\AuthController::class, 'registerInvestor'])->name('register.investor.post');
        Route::get('/register/capital-partner', [\App\Http\Controllers\Site\AuthController::class, 'showRegisterCapital'])->name('register.capital');
        Route::post('/register/capital-partner',[\App\Http\Controllers\Site\AuthController::class, 'registerCapital'])->name('register.capital.post');
    });

    // Authenticated public area (explicit web guard)
    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Site\AuthController::class, 'logout'])->name('logout');

        // Borrower wizard + dashboard
        Route::middleware('membership.active')->group(function () {
            Route::get('/borrower/apply',  [\App\Http\Controllers\Site\ApplyController::class, 'show'])->name('borrower.apply');
            Route::post('/borrower/apply', [\App\Http\Controllers\Site\ApplyController::class, 'submit'])->name('borrower.apply.submit');
            Route::get('/apply', fn () => redirect()->route('site.borrower.apply'))->name('apply.show');
            Route::post('/apply', [\App\Http\Controllers\Site\ApplyController::class, 'submit'])->name('apply.submit');
        });
        Route::get('/borrower/apply/{application}/success', [\App\Http\Controllers\Site\ApplyController::class, 'success'])->name('borrower.apply.success');
        Route::get('/apply/{application}/success', [\App\Http\Controllers\Site\ApplyController::class, 'success'])->name('apply.success');

        // Membership (always accessible, even when expired — needed for renewals)
        Route::get('/borrower/membership',         [\App\Http\Controllers\Site\MembershipController::class, 'show'])      ->name('membership.show');
        Route::get('/borrower/membership/renew',   [\App\Http\Controllers\Site\MembershipController::class, 'renewForm']) ->name('membership.renew');
        Route::post('/borrower/membership/renew',  [\App\Http\Controllers\Site\MembershipController::class, 'renew'])     ->name('membership.renew.post');

        Route::get('/borrower',                                [\App\Http\Controllers\Site\BorrowerController::class, 'dashboard'])    ->name('borrower.dashboard');
        Route::get('/borrower/applications',                   [\App\Http\Controllers\Site\BorrowerController::class, 'applications']) ->name('borrower.applications');
        Route::get('/borrower/applications/{application}',     [\App\Http\Controllers\Site\BorrowerController::class, 'application'])  ->name('borrower.application');
        Route::post('/borrower/applications/{application}/documents', [\App\Http\Controllers\Site\BorrowerController::class, 'uploadApplicationDocument'])->name('borrower.application.documents.store');
        Route::get('/borrower/applications/{application}/agreement',                [\App\Http\Controllers\Site\LoanAgreementController::class, 'show'])      ->name('borrower.application.agreement');
        Route::post('/borrower/applications/{application}/agreement/otp',           [\App\Http\Controllers\Site\LoanAgreementController::class, 'requestOtp'])->name('borrower.application.agreement.otp');
        Route::post('/borrower/applications/{application}/agreement/sign',          [\App\Http\Controllers\Site\LoanAgreementController::class, 'sign'])      ->name('borrower.application.agreement.sign');
        Route::get('/borrower/agreements/{agreement}/download',                     [\App\Http\Controllers\Site\LoanAgreementController::class, 'download']) ->name('borrower.agreement.download');
        Route::get('/borrower/loans',                          [\App\Http\Controllers\Site\BorrowerController::class, 'loans'])        ->name('borrower.loans');
        Route::get('/borrower/schedule/{loan?}',               [\App\Http\Controllers\Site\BorrowerController::class, 'schedule'])     ->name('borrower.schedule');
        Route::get('/borrower/payments',                       [\App\Http\Controllers\Site\BorrowerController::class, 'payments'])     ->name('borrower.payments');
        Route::post('/borrower/payments',                      [\App\Http\Controllers\Site\BorrowerController::class, 'submitPayment'])->name('borrower.payments.store');
        Route::get('/borrower/documents',                      [\App\Http\Controllers\Site\BorrowerController::class, 'documents'])    ->name('borrower.documents');
        Route::post('/borrower/documents',                     [\App\Http\Controllers\Site\BorrowerController::class, 'uploadDocument'])->name('borrower.documents.store');
        Route::get('/borrower/kyc',                            [\App\Http\Controllers\Site\BorrowerController::class, 'kyc'])          ->name('borrower.kyc');
        Route::post('/borrower/kyc',                           [\App\Http\Controllers\Site\BorrowerController::class, 'uploadKyc'])    ->name('borrower.kyc.store');
        Route::get('/borrower/guarantors',                     [\App\Http\Controllers\Site\BorrowerController::class, 'guarantors'])   ->name('borrower.guarantors');
        Route::post('/borrower/guarantors',                    [\App\Http\Controllers\Site\BorrowerController::class, 'addGuarantor']) ->name('borrower.guarantors.store');
        Route::get('/borrower/notifications',                  [\App\Http\Controllers\Site\BorrowerController::class, 'notifications'])->name('borrower.notifications');
        Route::get('/borrower/profile/{section?}',             [\App\Http\Controllers\Site\BorrowerController::class, 'profile'])->name('borrower.profile')->where('section', 'personal|activity|residence|kyc');
        Route::put('/borrower/profile/{section}',              [\App\Http\Controllers\Site\BorrowerController::class, 'updateProfile'])->name('borrower.profile.update')->where('section', 'personal|activity|residence');
        Route::get('/borrower/support',                        [\App\Http\Controllers\Site\BorrowerController::class, 'support'])      ->name('borrower.support');

        // ---- Vendor portal ----
        Route::get('/vendor',                                   [\App\Http\Controllers\Site\VendorController::class, 'dashboard'])     ->name('vendor.dashboard');
        Route::get('/vendor/tasks',                             [\App\Http\Controllers\Site\VendorController::class, 'tasks'])         ->name('vendor.tasks');
        Route::get('/vendor/tasks/active',                      [\App\Http\Controllers\Site\VendorController::class, 'activeJobs'])    ->name('vendor.tasks.active');
        Route::get('/vendor/tasks/completed',                   [\App\Http\Controllers\Site\VendorController::class, 'completedJobs']) ->name('vendor.tasks.completed');
        Route::get('/vendor/tasks/{task}',                      [\App\Http\Controllers\Site\VendorController::class, 'task'])          ->name('vendor.task');
        Route::post('/vendor/tasks/{task}/accept',              [\App\Http\Controllers\Site\VendorController::class, 'acceptTask'])    ->name('vendor.task.accept');
        Route::post('/vendor/tasks/{task}/start',               [\App\Http\Controllers\Site\VendorController::class, 'startTask'])     ->name('vendor.task.start');
        Route::post('/vendor/tasks/{task}/complete',            [\App\Http\Controllers\Site\VendorController::class, 'completeTask'])  ->name('vendor.task.complete');
        Route::post('/vendor/tasks/{task}/proof',               [\App\Http\Controllers\Site\VendorController::class, 'uploadProof'])   ->name('vendor.task.proof');
        Route::get('/vendor/documents',                         [\App\Http\Controllers\Site\VendorController::class, 'documents'])     ->name('vendor.documents');
        Route::post('/vendor/documents',                        [\App\Http\Controllers\Site\VendorController::class, 'uploadDocument'])->name('vendor.documents.store');
        Route::get('/vendor/payments',                          [\App\Http\Controllers\Site\VendorController::class, 'payments'])      ->name('vendor.payments');
        Route::get('/vendor/payments/{payment}/invoice',        [\App\Http\Controllers\Site\VendorController::class, 'invoice'])       ->name('vendor.invoice');
        Route::get('/vendor/calendar',                          [\App\Http\Controllers\Site\VendorController::class, 'calendar'])      ->name('vendor.calendar');
        Route::get('/vendor/notifications',                     [\App\Http\Controllers\Site\VendorController::class, 'notifications']) ->name('vendor.notifications');
        Route::get('/vendor/profile',                           [\App\Http\Controllers\Site\VendorController::class, 'profile'])       ->name('vendor.profile');
        Route::put('/vendor/profile',                           [\App\Http\Controllers\Site\VendorController::class, 'updateProfile']) ->name('vendor.profile.update');
        Route::get('/vendor/support',                           [\App\Http\Controllers\Site\VendorController::class, 'support'])       ->name('vendor.support');

        // Legacy redirect
        Route::get('/vendor-portal', fn () => redirect()->route('site.vendor.dashboard'));

        // ---- Investor / Capital Lender portal ----
        Route::get('/investor',                                 [\App\Http\Controllers\Site\InvestorController::class, 'dashboard'])    ->name('investor.dashboard');
        Route::get('/investor/pools',                           [\App\Http\Controllers\Site\InvestorController::class, 'pools'])        ->name('investor.pools');
        Route::get('/investor/pools/{pool}',                    [\App\Http\Controllers\Site\InvestorController::class, 'pool'])         ->name('investor.pool');
        Route::post('/investor/pools/{pool}/invest',            [\App\Http\Controllers\Site\InvestorController::class, 'invest'])       ->name('investor.pool.invest');
        Route::get('/investor/investments',                     [\App\Http\Controllers\Site\InvestorController::class, 'investments'])  ->name('investor.investments');
        Route::get('/investor/investments/{investment}',        [\App\Http\Controllers\Site\InvestorController::class, 'investment'])   ->name('investor.investment');
        Route::get('/investor/returns',                         [\App\Http\Controllers\Site\InvestorController::class, 'returns'])      ->name('investor.returns');
        Route::get('/investor/analytics',                       [\App\Http\Controllers\Site\InvestorController::class, 'analytics'])    ->name('investor.analytics');
        Route::get('/investor/transactions',                    [\App\Http\Controllers\Site\InvestorController::class, 'transactions']) ->name('investor.transactions');
        Route::get('/investor/wallet',                          [\App\Http\Controllers\Site\InvestorController::class, 'wallet'])       ->name('investor.wallet');
        Route::post('/investor/wallet/deposit',                 [\App\Http\Controllers\Site\InvestorController::class, 'deposit'])      ->name('investor.wallet.deposit');
        Route::post('/investor/wallet/withdraw',                [\App\Http\Controllers\Site\InvestorController::class, 'withdraw'])     ->name('investor.wallet.withdraw');
        Route::get('/investor/documents',                       [\App\Http\Controllers\Site\InvestorController::class, 'documents'])    ->name('investor.documents');
        Route::get('/investor/notifications',                   [\App\Http\Controllers\Site\InvestorController::class, 'notifications'])->name('investor.notifications');
        Route::get('/investor/profile',                         [\App\Http\Controllers\Site\InvestorController::class, 'profile'])      ->name('investor.profile');
        Route::put('/investor/profile',                         [\App\Http\Controllers\Site\InvestorController::class, 'updateProfile'])->name('investor.profile.update');
        Route::get('/investor/support',                         [\App\Http\Controllers\Site\InvestorController::class, 'support'])      ->name('investor.support');
        Route::get('/investor-portal', fn () => redirect()->route('site.investor.dashboard'));
    });
});

/*
|--------------------------------------------------------------------------
| Console (Tailwind + Livewire admin)
|--------------------------------------------------------------------------
*/

/**
 * Register Index + create/store/show/edit/update/destroy for an admin resource.
 *
 * @param string $slug         URL slug, e.g. 'customers'
 * @param string $param        Route param name, e.g. 'customer'
 * @param string $controller   Controller FQCN
 */
$registerResource = function (string $slug, string $param, string $controller): void {
    Route::view($slug, "admin.{$slug}.index")->name("{$slug}.index");
    Route::get("{$slug}/create",                    [$controller, 'create']) ->name("{$slug}.create");
    Route::post($slug,                              [$controller, 'store'])  ->name("{$slug}.store");
    Route::get("{$slug}/{{$param}}",                [$controller, 'show'])   ->name("{$slug}.show");
    Route::get("{$slug}/{{$param}}/edit",           [$controller, 'edit'])   ->name("{$slug}.edit");
    Route::put("{$slug}/{{$param}}",                [$controller, 'update']) ->name("{$slug}.update");
    Route::delete("{$slug}/{{$param}}",             [$controller, 'destroy'])->name("{$slug}.destroy");
};

Route::prefix('admin')->name('admin.')->group(function () use ($registerResource) {

    // Guest
    Route::middleware('guest:admin')->group(function () {
        Route::get('login',  [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login']);
    });

    // Authenticated
    Route::middleware('auth:admin')->group(function () use ($registerResource) {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        // Applications
        Route::view('loan-applications/new',              'admin.loan-applications.new')              ->name('loan-applications.new');
        Route::view('loan-applications/pending-documents','admin.loan-applications.pending-documents')->name('loan-applications.pending-documents');
        Route::view('loan-applications/under-review',     'admin.loan-applications.under-review')     ->name('loan-applications.under-review');
        Route::view('loan-applications/pre-approvals',    'admin.loan-applications.pre-approvals')    ->name('loan-applications.pre-approvals');
        Route::view('loan-applications/final-approvals',  'admin.loan-applications.final-approvals')  ->name('loan-applications.final-approvals');
        Route::view('loan-applications/rejected',         'admin.loan-applications.rejected')         ->name('loan-applications.rejected');
        $registerResource('loan-applications', 'loan_application', LoanApplicationController::class);
        Route::post('loan-applications/{loan_application}/agreement', [\App\Http\Controllers\Admin\LoanAgreementController::class, 'generate'])
            ->name('loan-applications.agreement.generate');

        // Customers
        $registerResource('customers',     'customer',      CustomerController::class);
        $registerResource('customer-kycs', 'customer_kyc',  CustomerKycController::class);
        $registerResource('guarantors',    'guarantor',     GuarantorController::class);

        // Loans
        Route::view('loans/active',        'admin.loans.active')       ->name('loans.active');
        Route::view('loans/disbursement',  'admin.loans.disbursement') ->name('loans.disbursement');
        Route::view('loans/arrears',       'admin.loans.arrears')      ->name('loans.arrears');
        Route::view('loans/restructuring', 'admin.loans.restructuring')->name('loans.restructuring');
        Route::view('loans/closed',        'admin.loans.closed')       ->name('loans.closed');
        $registerResource('loans',      'loan',      LoanController::class);
        Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
        Route::get('loans/{loan}/write-off',  [LoanController::class, 'writeOffForm'])->name('loans.write-off-form');
        Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');
        $registerResource('repayments', 'repayment', RepaymentController::class);

        // Loan Products
        Route::view('loan-products/interest-fees',  'admin.loan-products.interest-fees') ->name('loan-products.interest-fees');
        Route::view('loan-products/documents',      'admin.loan-products.documents')     ->name('loan-products.documents');
        Route::view('loan-products/approval-rules', 'admin.loan-products.approval-rules')->name('loan-products.approval-rules');
        $registerResource('loan-products', 'loan_product', LoanProductController::class);

        // Vendors
        Route::view('vendors/applications',        'admin.vendors.applications')        ->name('vendors.applications');
        Route::view('vendors/gps-installers',      'admin.vendors.gps-installers')      ->name('vendors.gps-installers');
        Route::view('vendors/insurance-providers', 'admin.vendors.insurance-providers') ->name('vendors.insurance-providers');
        Route::view('vendors/valuers',             'admin.vendors.valuers')             ->name('vendors.valuers');
        Route::view('vendors/tasks',               'admin.vendors.tasks')               ->name('vendors.tasks');
        $registerResource('vendors', 'vendor', VendorController::class);

        // Capital
        $registerResource('lenders',            'lender',             LenderController::class);
        $registerResource('funding-pools',      'funding_pool',       FundingPoolController::class);
        $registerResource('lender-investments', 'lender_investment',  LenderInvestmentController::class);

        // Finance
        $registerResource('expenses',        'expense',        ExpenseController::class);
        Route::post('expenses/{expense}/post', [ExpenseController::class, 'post'])->name('expenses.post');
        $registerResource('settlements',     'settlement',     SettlementController::class);
        $registerResource('reconciliations', 'reconciliation', ReconciliationController::class);
        Route::get('journal-entries',                [JournalEntryController::class, 'index'])->name('journal-entries.index');
        Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show'])->name('journal-entries.show');

        // Reports
        Route::view('reports/portfolio',          'admin.reports.portfolio')          ->name('reports.portfolio');
        Route::view('reports/disbursements',      'admin.reports.disbursements')      ->name('reports.disbursements');
        Route::view('reports/repayments',         'admin.reports.repayments')         ->name('reports.repayments');
        Route::view('reports/arrears',            'admin.reports.arrears')            ->name('reports.arrears');
        Route::view('reports/par',                'admin.reports.par')                ->name('reports.par');
        Route::view('reports/vendor-performance', 'admin.reports.vendor-performance') ->name('reports.vendor-performance');

        // Support
        $registerResource('support-tickets', 'support_ticket', SupportTicketController::class);
        $registerResource('complaints',      'complaint',      ComplaintController::class);

        // Membership bank payments
        Route::get('membership-payments', [MembershipPaymentController::class, 'index'])->name('membership-payments.index');
        Route::post('membership-payments/{membershipHistory}/approve', [MembershipPaymentController::class, 'approve'])->name('membership-payments.approve');
        Route::post('membership-payments/{membershipHistory}/reject', [MembershipPaymentController::class, 'reject'])->name('membership-payments.reject');

        // System
        $registerResource('branches', 'branch', BranchController::class);
        $registerResource('users',    'user',   UserController::class);

        // ========== FINANCE (extended) ==========
        $registerResource('chart-of-accounts',      'chart_of_account',      ChartOfAccountController::class);
        $registerResource('bank-accounts',          'bank_account',          BankAccountController::class);
        $registerResource('mobile-money-accounts',  'mobile_money_account',  MobileMoneyAccountController::class);
        $registerResource('disbursement-methods',   'disbursement_method',   DisbursementMethodController::class);
        $registerResource('repayment-methods',      'repayment_method',      RepaymentMethodController::class);
        $registerResource('charges-fees',           'charges_fee',           ChargesFeeController::class);
        $registerResource('write-off-rules',        'write_off_rule',        WriteOffRuleController::class);

        // Finance reports
        Route::get('reports/trial-balance',    [FinanceReportsController::class, 'trialBalance'])   ->name('reports.trial-balance');
        Route::get('reports/income-statement', [FinanceReportsController::class, 'incomeStatement'])->name('reports.income-statement');
        Route::get('reports/balance-sheet',    [FinanceReportsController::class, 'balanceSheet'])   ->name('reports.balance-sheet');
        Route::get('reports/cash-flow',        [FinanceReportsController::class, 'cashFlow'])       ->name('reports.cash-flow');
        Route::get('reports/npl',              [FinanceReportsController::class, 'npl'])            ->name('reports.npl');
        Route::get('reports/customers',        [FinanceReportsController::class, 'customers'])      ->name('reports.customers');
        Route::get('reports/financial-overview',[FinanceReportsController::class, 'financialOverview'])->name('reports.financial-overview');

        // ========== KYC / RISK ==========
        $registerResource('risk-scoring-rules',   'risk_scoring_rule',   RiskScoringRuleController::class);
        $registerResource('blacklist-entries',    'blacklist_entry',     BlacklistEntryController::class);
        $registerResource('pep-flags',            'pep_flag',            PepFlagController::class);
        $registerResource('aml-rules',            'aml_rule',            AmlRuleController::class);
        $registerResource('suspicious-activities','suspicious_activity', SuspiciousActivityController::class);

        // ========== COMPLIANCE ==========
        Route::get('compliance/bot-reports', [ComplianceController::class, 'botReports'])->name('compliance.bot-reports');
        Route::get('compliance/aml-reports', [ComplianceController::class, 'amlReports'])->name('compliance.aml-reports');
        Route::get('compliance/kyc-reports', [ComplianceController::class, 'kycReports'])->name('compliance.kyc-reports');
        Route::get('compliance/exports',     [ComplianceController::class, 'exports'])   ->name('compliance.exports');
        Route::get('compliance/large-transactions', [ComplianceController::class, 'largeTransactions'])->name('compliance.large-transactions');
        Route::get('compliance/bot-portfolio-export', [ComplianceController::class, 'botPortfolioExport'])->name('compliance.bot-portfolio-export');
        Route::get('compliance/suspicious-activities/{activity}', [ComplianceController::class, 'suspiciousShow'])->name('compliance.suspicious.show');
        Route::patch('compliance/suspicious-activities/{activity}', [ComplianceController::class, 'suspiciousUpdate'])->name('compliance.suspicious.update');
        Route::post('compliance/suspicious-activities/{activity}/sar', [ComplianceController::class, 'fileSar'])->name('compliance.suspicious.sar');
        Route::get('audit-logs',             [AuditLogController::class, 'index'])       ->name('audit-logs.index');
        Route::get('audit-logs/{id}',        [AuditLogController::class, 'show'])        ->name('audit-logs.show');

        // ========== SETTINGS ==========
        Route::get('settings',                  [SettingsController::class, 'index'])         ->name('settings.index');
        Route::get('settings/company',          [SettingsController::class, 'company'])       ->name('settings.company');
        Route::put('settings/company',          [SettingsController::class, 'saveCompany'])   ->name('settings.company.save');
        Route::get('settings/gateways',         [SettingsController::class, 'gateways'])      ->name('settings.gateways');
        Route::put('settings/gateways',         [SettingsController::class, 'saveGateways'])  ->name('settings.gateways.save');
        Route::get('settings/kyc',              [SettingsController::class, 'kyc'])           ->name('settings.kyc');
        Route::put('settings/kyc',              [SettingsController::class, 'saveKyc'])       ->name('settings.kyc.save');
        Route::get('settings/loan-rules',       [SettingsController::class, 'loanRules'])     ->name('settings.loan-rules');
        Route::put('settings/loan-rules',       [SettingsController::class, 'saveLoanRules']) ->name('settings.loan-rules.save');
        Route::get('settings/membership',       [SettingsController::class, 'membership'])    ->name('settings.membership');
        Route::put('settings/membership',       [SettingsController::class, 'saveMembership'])->name('settings.membership.save');
        Route::get('settings/aml',              [SettingsController::class, 'amlSettings'])   ->name('settings.aml');
        Route::put('settings/aml',              [SettingsController::class, 'saveAmlSettings'])->name('settings.aml.save');
        Route::get('settings/finance',          [SettingsController::class, 'finance'])       ->name('settings.finance');
        Route::put('settings/finance',          [SettingsController::class, 'saveFinance'])   ->name('settings.finance.save');

        $registerResource('departments',           'department',            DepartmentController::class);
        $registerResource('roles',                 'role',                  RoleController::class);
        $registerResource('approval-limits',       'approval_limit',        ApprovalLimitController::class);
        $registerResource('document-templates',    'document_template',     DocumentTemplateController::class);
        $registerResource('notification-templates','notification_template', NotificationTemplateController::class);
    });
});
