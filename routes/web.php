<?php

use App\Http\Controllers\Admin\AmlRuleController;
use App\Http\Controllers\Admin\ApprovalLimitController;
use App\Http\Controllers\Admin\ArrearCaseController;
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
use App\Http\Controllers\Admin\FaceVerificationController;
use App\Http\Controllers\Admin\FinanceReportsController;
use App\Http\Controllers\Admin\FundingPoolController;
use App\Http\Controllers\Admin\GuarantorController;
use App\Http\Controllers\Admin\LenderController;
use App\Http\Controllers\Admin\LenderInvestmentController;
use App\Http\Controllers\Admin\LoanApplicationController;
use App\Http\Controllers\Admin\LoanApplicationDocumentRequestController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\LoanTopUpRequestController;
use App\Http\Controllers\Admin\MembershipPaymentController;
use App\Http\Controllers\Admin\PaymentAccountSettingsController;
use App\Http\Controllers\Admin\AffiliateReportsController;
use App\Http\Controllers\Admin\LoanReportsController;
use App\Http\Controllers\Admin\PaymentVerificationController;
use App\Http\Controllers\Admin\RestructureRequestController;
use App\Http\Controllers\Admin\MobileMoneyAccountController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\PepFlagController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\RecoveryAssignmentController;
use App\Http\Controllers\Admin\RecoveryPartnerController;
use App\Http\Controllers\Admin\RepaymentController;
use App\Http\Controllers\Admin\RepaymentMethodController;
use App\Http\Controllers\Admin\RiskScoringRuleController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\LocationMasterController;
use App\Http\Controllers\Admin\SignatoryController;
use App\Http\Controllers\Admin\EngagementSettingsController;
use App\Http\Controllers\Admin\ProfileSectionDefinitionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\SupportChatController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SuspiciousActivityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PartnerSettlementController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\VendorPaymentController;
use App\Http\Controllers\Admin\WriteOffRequestController;
use App\Http\Controllers\Admin\WriteOffRuleController;
use App\Http\Controllers\Auth\WebTwoFactorController;
use App\Http\Controllers\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Staff\StaffPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PSP webhooks (CSRF excluded in bootstrap/app.php)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/payin', \App\Http\Controllers\PayInWebhookController::class)->name('webhooks.payin');

/*
|--------------------------------------------------------------------------
| Public site (kopafasta.com style)
|--------------------------------------------------------------------------
*/
Route::name('site.')->middleware(\App\Http\Middleware\SetLocale::class)->group(function () {
    Route::post('/locale', [\App\Http\Controllers\Site\LocaleController::class, 'update'])->name('locale.update');
    Route::post('/country', [\App\Http\Controllers\Site\CountryController::class, 'update'])->name('country.update');

    Route::get('/',                 [\App\Http\Controllers\Site\PageController::class, 'home'])->name('home');
    Route::get('/loans',            [\App\Http\Controllers\Site\PageController::class, 'products'])->name('products');
    Route::get('/loans/product/{code}', [\App\Http\Controllers\Site\PageController::class, 'product'])->name('product');
    Route::get('/how-it-works',     [\App\Http\Controllers\Site\PageController::class, 'howItWorks'])->name('how-it-works');
    Route::get('/about',            [\App\Http\Controllers\Site\PageController::class, 'about'])->name('about');
    Route::get('/faq',              [\App\Http\Controllers\Site\PageController::class, 'faq'])->name('faq');
    Route::get('/legal',            [\App\Http\Controllers\Site\PageController::class, 'legalIndex'])->name('legal');
    Route::get('/legal/terms',      [\App\Http\Controllers\Site\PageController::class, 'terms'])->name('legal.terms');
    Route::get('/legal/privacy',    [\App\Http\Controllers\Site\PageController::class, 'privacy'])->name('legal.privacy');
    Route::get('/legal/aml-kyc',    [\App\Http\Controllers\Site\PageController::class, 'aml'])->name('legal.aml');
    Route::get('/legal/complaints', [\App\Http\Controllers\Site\PageController::class, 'complaints'])->name('legal.complaints');
    Route::get('/legal/cookies',    [\App\Http\Controllers\Site\PageController::class, 'cookies'])->name('legal.cookies');
    Route::get('/support',         [\App\Http\Controllers\Site\SupportCenterController::class, 'index'])->name('support');
    Route::get('/feedback',        [\App\Http\Controllers\Site\FeedbackController::class, 'index'])->name('feedback');
    Route::post('/feedback',       [\App\Http\Controllers\Site\FeedbackController::class, 'store'])->name('feedback.post');
    Route::get('/contact', fn () => redirect()->route('site.support'))->name('contact');
    Route::get('/invest',           [\App\Http\Controllers\Site\PageController::class, 'invest'])->name('invest');
    Route::get('/capital-partners', [\App\Http\Controllers\Site\PageController::class, 'capitalPartners'])->name('capital-partners');
    Route::get('/affiliate-program', [\App\Http\Controllers\Site\PageController::class, 'affiliate'])->name('affiliate');
    Route::get('/affiliate', fn () => redirect()->route('site.affiliate'));
    Route::get('/service-partners', [\App\Http\Controllers\Site\PageController::class, 'partners'])->name('partners');
    Route::get('/partners', fn () => redirect()->route('site.partners'));
    Route::get('/country/{code}',   [\App\Http\Controllers\Site\PageController::class, 'country'])->name('country');
    Route::get('/become-affiliate', [\App\Http\Controllers\Site\PartnerApplicationController::class, 'create'])->name('affiliate.apply');
    Route::post('/become-affiliate', [\App\Http\Controllers\Site\PartnerApplicationController::class, 'store'])->name('affiliate.apply.post');
    Route::get('/partners/apply/{category?}', [\App\Http\Controllers\Site\PartnerApplicationController::class, 'createService'])->name('partners.apply');
    Route::post('/partners/apply', [\App\Http\Controllers\Site\PartnerApplicationController::class, 'storeService'])->name('partners.apply.post');
    Route::get('/partners/track', [\App\Http\Controllers\Site\PartnerApplicationController::class, 'tracking'])->name('partners.apply.tracking');
    Route::post('/partners/track', [\App\Http\Controllers\Site\PartnerApplicationController::class, 'tracking'])->name('partners.apply.tracking.post');

    Route::get('/marketplace', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'publicIndex'])->name('marketplace');
    Route::post('/marketplace/request', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'storePublicRequest'])->name('marketplace.request');
    Route::get('/marketplace/{assetId}', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'publicShow'])->name('marketplace.show');
    Route::get('/verify/member/{memberNo}', [\App\Http\Controllers\Site\MemberVerificationController::class, 'show'])->name('member.verify');
    Route::get('/v/{memberNo}', [\App\Http\Controllers\Site\MemberVerificationController::class, 'show'])->name('short.member');
    Route::get('/verify/affiliate', [\App\Http\Controllers\Site\AffiliateVerificationController::class, 'index'])->name('affiliate.verify.index');
    Route::post('/verify/affiliate', [\App\Http\Controllers\Site\AffiliateVerificationController::class, 'lookup'])->name('affiliate.verify.lookup');
    Route::get('/verify/affiliate/{code}', [\App\Http\Controllers\Site\AffiliateVerificationController::class, 'show'])->name('affiliate.verify');

    // Public guarantor invitation (guest + logged-in users must both reach this page)
    Route::get('/guarantor-request/{token}', [\App\Http\Controllers\Site\PublicGuarantorController::class, 'show'])->name('guarantor.show');
    Route::get('/guarantor-request/{token}/declined', [\App\Http\Controllers\Site\PublicGuarantorController::class, 'declined'])->name('guarantor.declined');
    Route::post('/guarantor-request/{token}/accept', [\App\Http\Controllers\Site\PublicGuarantorController::class, 'accept'])->name('guarantor.accept');
    Route::post('/guarantor-request/{token}/reject', [\App\Http\Controllers\Site\PublicGuarantorController::class, 'reject'])->name('guarantor.reject');
    Route::get('/group-member-invite/{token}', [\App\Http\Controllers\Site\GroupMemberInviteController::class, 'show'])->name('group-member.invite');
    Route::get('/group-member-invite/{token}/declined', [\App\Http\Controllers\Site\GroupMemberInviteController::class, 'declined'])->name('group-member.declined');
    Route::post('/group-member-invite/{token}/accept', [\App\Http\Controllers\Site\GroupMemberInviteController::class, 'accept'])->name('group-member.accept');
    Route::post('/group-member-invite/{token}/reject', [\App\Http\Controllers\Site\GroupMemberInviteController::class, 'reject'])->name('group-member.reject');
    Route::get('/guarantor/{token}', fn (string $token) => redirect("/guarantor-request/{$token}", 301));
    Route::get('/g/{code}', [\App\Http\Controllers\Site\ShortLinkController::class, 'resolve'])->name('short.guarantor');

    // Guest auth (explicit web guard)
    Route::middleware('guest:web')->group(function () {
        Route::get('/login',  [\App\Http\Controllers\Site\AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [\App\Http\Controllers\Site\AuthController::class, 'login'])->name('login.post');
        Route::get('/forgot-pin', [\App\Http\Controllers\Site\AuthController::class, 'showForgotPin'])->name('forgot-pin');
        Route::post('/forgot-pin/start', [\App\Http\Controllers\Site\AuthController::class, 'startPinRecovery'])->name('forgot-pin.start');
        Route::post('/forgot-pin/verify-challenge', [\App\Http\Controllers\Site\AuthController::class, 'verifyPinRecoveryAnswers'])->name('forgot-pin.verify-challenge');
        Route::post('/forgot-pin/reset-challenge', [\App\Http\Controllers\Site\AuthController::class, 'resetPinWithChallenge'])->name('forgot-pin.reset-challenge');

        Route::get('/aff/{code}', \App\Http\Controllers\Site\AffiliateRedirectController::class)->name('affiliate.redirect');

        Route::get('/register',          fn () => redirect()->route('site.register.borrower'))->name('register');
        Route::get('/register/options',  fn () => view('site.auth.register-choose'))->name('register.options');
        Route::get('/register/borrower', [\App\Http\Controllers\Site\AuthController::class, 'showRegisterBorrower'])->name('register.borrower');
        Route::post('/register/borrower',[\App\Http\Controllers\Site\AuthController::class, 'registerBorrower'])->name('register.borrower.post');
        Route::post('/register/check-phone', [\App\Http\Controllers\Site\AuthController::class, 'checkBorrowerPhone'])->name('register.check-phone');

        Route::post('/waitlist',          [\App\Http\Controllers\Site\AuthController::class, 'storeWaitlistRequest'])->name('waitlist.store');
        Route::get('/register/vendor',   [\App\Http\Controllers\Site\AuthController::class, 'showRegisterVendor'])->name('register.vendor');
        Route::post('/register/vendor',  [\App\Http\Controllers\Site\AuthController::class, 'registerVendor'])->name('register.vendor.post');
        Route::get('/register/investor', [\App\Http\Controllers\Site\AuthController::class, 'showRegisterInvestor'])->name('register.investor');
        Route::post('/register/investor',[\App\Http\Controllers\Site\AuthController::class, 'registerInvestor'])->name('register.investor.post');
        Route::get('/register/capital-partner', [\App\Http\Controllers\Site\AuthController::class, 'showRegisterCapital'])->name('register.capital');
        Route::post('/register/capital-partner',[\App\Http\Controllers\Site\AuthController::class, 'registerCapital'])->name('register.capital.post');

        Route::get('/partner/activate/{vendor}', [\App\Http\Controllers\Site\PartnerActivationController::class, 'show'])->name('partner.activate');
        Route::post('/partner/activate/{vendor}', [\App\Http\Controllers\Site\PartnerActivationController::class, 'store'])->name('partner.activate.post');
        Route::get('/partner/start', [\App\Http\Controllers\Site\PartnerPortalController::class, 'start'])->name('partner.start');
        Route::post('/partner/start', [\App\Http\Controllers\Site\PartnerPortalController::class, 'lookup'])->name('partner.start.lookup');

        Route::redirect('/partner/login', '/login/partner');
        Route::redirect('/partners/login', '/login/partner');
        Route::get('/staff-login', [\App\Http\Controllers\Site\AuthController::class, 'staffHint'])->name('staff-login');
        Route::get('/register/partner', fn () => redirect()->route('site.register.vendor'))->name('register.partner');
        Route::redirect('/partner/register', '/register/partner');
    });

    // Partner login switch — works even if a borrower session is still open.
    Route::get('/login/partner', [\App\Http\Controllers\Site\AuthController::class, 'switchToPartnerLogin'])
        ->name('login.partner');

    Route::get('/partner', [\App\Http\Controllers\Site\PartnerHomeController::class, '__invoke'])
        ->name('partner.dashboard');

    // Authenticated public area (explicit web guard)
    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Site\AuthController::class, 'logout'])->name('logout');

        Route::get('/borrower/setup-pin', [\App\Http\Controllers\Site\AuthController::class, 'showSetupPin'])->name('borrower.setup-pin');
        Route::post('/borrower/setup-pin', [\App\Http\Controllers\Site\AuthController::class, 'storeSetupPin'])->name('borrower.setup-pin.post');
        Route::post('/borrower/setup-pin/swap-question', [\App\Http\Controllers\Site\AuthController::class, 'swapSetupPinQuestion'])->name('borrower.setup-pin.swap');

        Route::get('/partner/setup-pin', [\App\Http\Controllers\Site\PartnerPortalController::class, 'showSetupPin'])->name('partner.setup-pin');
        Route::post('/partner/setup-pin', [\App\Http\Controllers\Site\PartnerPortalController::class, 'storeSetupPin'])->name('partner.setup-pin.post');

        Route::middleware('borrower.pin')->group(function () {
            // Browse products without membership; pay / renew when starting apply.
            Route::get('/borrower/loan-products', [\App\Http\Controllers\Site\BorrowerController::class, 'loanProducts'])->name('borrower.loan-products');

            // Browse and draft freely; membership is enforced at fee payment / submit.
            Route::get('/borrower/apply',  [\App\Http\Controllers\Site\ApplyController::class, 'show'])->name('borrower.apply');
            Route::get('/borrower/apply/product/{product}/readiness', [\App\Http\Controllers\Site\ApplyController::class, 'productReadiness'])->name('borrower.apply.product-readiness');
            Route::post('/borrower/apply/guarantor-lookup', [\App\Http\Controllers\Site\ApplyController::class, 'lookupGuarantor'])->name('borrower.apply.guarantor-lookup');
            Route::post('/borrower/apply/group-member-lookup', [\App\Http\Controllers\Site\ApplyController::class, 'lookupGroupMember'])->name('borrower.apply.group-member-lookup');
            Route::get('/borrower/apply/previous-group-members', [\App\Http\Controllers\Site\ApplyController::class, 'previousGroupMembers'])->name('borrower.apply.previous-group-members');
            Route::post('/borrower/apply/previous-group-member', [\App\Http\Controllers\Site\ApplyController::class, 'selectPreviousGroupMember'])->name('borrower.apply.previous-group-member');
            Route::post('/borrower/apply/group-member-invite', [\App\Http\Controllers\Site\ApplyController::class, 'prepareGroupMemberInvite'])->name('borrower.apply.group-member-invite');
            Route::post('/borrower/apply/group-member-expire', [\App\Http\Controllers\Site\ApplyController::class, 'expireGroupMemberInvitation'])->name('borrower.apply.group-member-expire');
            Route::post('/borrower/apply/group-member-statuses', [\App\Http\Controllers\Site\ApplyController::class, 'refreshGroupMemberStatuses'])->name('borrower.apply.group-member-statuses');
            Route::get('/borrower/apply/previous-guarantors', [\App\Http\Controllers\Site\ApplyController::class, 'previousGuarantors'])->name('borrower.apply.previous-guarantors');
            Route::post('/borrower/apply/previous-guarantor', [\App\Http\Controllers\Site\ApplyController::class, 'selectPreviousGuarantor'])->name('borrower.apply.previous-guarantor');
            Route::post('/borrower/apply/guarantor-invite', [\App\Http\Controllers\Site\ApplyController::class, 'prepareExternalGuarantor'])->name('borrower.apply.guarantor-invite');
            Route::get('/borrower/apply/guarantor-status', [\App\Http\Controllers\Site\ApplyController::class, 'guarantorInvitationStatus'])->name('borrower.apply.guarantor-status');
            Route::post('/borrower/apply/guarantor-expire', [\App\Http\Controllers\Site\ApplyController::class, 'expireGuarantorInvitation'])->name('borrower.apply.guarantor-expire');
            Route::get('/borrower/apply/draft', [\App\Http\Controllers\Site\ApplyController::class, 'loadDraft'])->name('borrower.apply.draft');
            Route::put('/borrower/apply/draft', [\App\Http\Controllers\Site\ApplyController::class, 'saveDraft'])->name('borrower.apply.draft.save');
            Route::get('/borrower/apply/application-fee/quote', [\App\Http\Controllers\Site\ApplyController::class, 'applicationFeeQuote'])->name('borrower.apply.application-fee.quote');
            Route::get('/borrower/apply/valuation-fee/quote', [\App\Http\Controllers\Site\ApplyController::class, 'valuationFeeQuote'])->name('borrower.apply.valuation-fee.quote');
            Route::post('/borrower/apply/asset-document', [\App\Http\Controllers\Site\ApplyController::class, 'uploadAssetDocument'])->name('borrower.apply.asset-document');
            Route::get('/borrower/apply/repayment-preview', [\App\Http\Controllers\Site\ApplyController::class, 'repaymentPreview'])->name('borrower.apply.repayment-preview');
            Route::get('/apply', fn () => redirect()->route('site.borrower.loan-products'))->name('apply.show');

            Route::middleware('membership.active')->group(function () {
                Route::post('/borrower/apply/application-fee', [\App\Http\Controllers\Site\ApplyController::class, 'payApplicationFee'])->name('borrower.apply.application-fee.pay');
                Route::post('/borrower/apply/valuation-fee', [\App\Http\Controllers\Site\ApplyController::class, 'payValuationFee'])->name('borrower.apply.valuation-fee.pay');
                Route::post('/borrower/apply', [\App\Http\Controllers\Site\ApplyController::class, 'submit'])->name('borrower.apply.submit');
                Route::post('/apply', [\App\Http\Controllers\Site\ApplyController::class, 'submit'])->name('apply.submit');
                Route::post('/borrower/marketplace/{assetId}/reserve', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'reserve'])->name('borrower.marketplace.reserve.post');
                Route::post('/borrower/marketplace/{assetId}/reservation', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'advanceReservation'])->name('borrower.marketplace.reservation.advance');
                Route::post('/borrower/marketplace/{assetId}/reservation/pay', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'payReservation'])->name('borrower.marketplace.reservation.pay');
                Route::post('/borrower/marketplace/{assetId}/apply', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'startApply'])->name('borrower.marketplace.apply');
                Route::post('/borrower/applications/{application}/post-approval-fees', [\App\Http\Controllers\Site\BorrowerController::class, 'payPostApprovalFees'])->name('borrower.application.post-approval-fees.pay');
            });
            Route::get('/borrower/apply/{application}/success', [\App\Http\Controllers\Site\ApplyController::class, 'success'])->name('borrower.apply.success');
            Route::get('/apply/{application}/success', [\App\Http\Controllers\Site\ApplyController::class, 'success'])->name('apply.success');

            Route::get('/borrower/engagement',         [\App\Http\Controllers\Site\EngagementHubController::class, 'show'])   ->name('borrower.engagement');
            Route::post('/borrower/engagement/redeem', [\App\Http\Controllers\Site\EngagementHubController::class, 'redeem'])->name('borrower.engagement.redeem');
            Route::get('/borrower/referrals', fn () => redirect()->route('site.borrower.engagement', ['tab' => 'referrals']))->name('borrower.referrals');
            Route::get('/borrower/rewards', fn () => redirect()->route('site.borrower.engagement', ['tab' => 'rewards']))->name('borrower.rewards');
            Route::post('/borrower/rewards/redeem', [\App\Http\Controllers\Site\EngagementHubController::class, 'redeem'])->name('borrower.rewards.redeem');
            Route::get('/borrower/membership', fn () => redirect()->route('site.borrower.profile', ['section' => 'membership']))->name('membership.show');
            Route::get('/borrower/membership/renew',   [\App\Http\Controllers\Site\MembershipController::class, 'renewForm']) ->name('membership.renew');
            Route::post('/borrower/membership/renew',  [\App\Http\Controllers\Site\MembershipController::class, 'renew'])     ->name('membership.renew.post');

            Route::get('/borrower',                                [\App\Http\Controllers\Site\BorrowerController::class, 'dashboard'])    ->name('borrower.dashboard');
            Route::get('/borrower/applications',                   [\App\Http\Controllers\Site\BorrowerController::class, 'applications']) ->name('borrower.applications');
            Route::get('/borrower/marketplace',                    [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'index'])->name('borrower.marketplace');
            Route::get('/borrower/marketplace/{assetId}',          [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'show'])->name('borrower.marketplace.show');
            Route::get('/borrower/marketplace/{assetId}/reserve', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'reserveFlow'])->name('borrower.marketplace.reserve');
            Route::get('/borrower/applications/{application}',     [\App\Http\Controllers\Site\BorrowerController::class, 'application'])  ->name('borrower.application');
            Route::post('/borrower/applications/{application}/withdraw', [\App\Http\Controllers\Site\BorrowerController::class, 'withdrawApplication'])->name('borrower.application.withdraw');
            Route::post('/borrower/applications/{application}/change-guarantor', [\App\Http\Controllers\Site\BorrowerController::class, 'changeGuarantorWhileHeld'])->name('borrower.application.change-guarantor');
            Route::post('/borrower/loan-profile/draft/{draft}/discard', [\App\Http\Controllers\Site\BorrowerController::class, 'discardDraft'])->name('borrower.draft.discard');
            Route::get('/borrower/applications/{application}/offer', [\App\Http\Controllers\Site\BorrowerController::class, 'applicationOffer'])->name('borrower.application.offer');
            Route::post('/borrower/applications/{application}/offer', [\App\Http\Controllers\Site\BorrowerController::class, 'respondToOffer'])->name('borrower.application.offer.respond');
            Route::get('/borrower/applications/{application}/asset-conversion', [\App\Http\Controllers\Site\BorrowerController::class, 'assetConversion'])->name('borrower.application.asset-conversion');
            Route::post('/borrower/applications/{application}/asset-conversion', [\App\Http\Controllers\Site\BorrowerController::class, 'respondToAssetConversion'])->name('borrower.application.asset-conversion.respond');
            Route::post('/borrower/applications/{application}/asset-conversion/pay', [\App\Http\Controllers\Site\BorrowerController::class, 'payAssetConversionFee'])->name('borrower.application.asset-conversion.pay');
            Route::post('/borrower/applications/{application}/collateral-secure/has-collateral', [\App\Http\Controllers\Site\CollateralSecureController::class, 'borrowerHasCollateral'])->name('borrower.collateral-secure.has');
            Route::post('/borrower/applications/{application}/collateral-secure/ask-guarantor', [\App\Http\Controllers\Site\CollateralSecureController::class, 'borrowerAskGuarantor'])->name('borrower.collateral-secure.ask-guarantor');
            Route::post('/borrower/applications/{application}/collateral-secure/link-asset', [\App\Http\Controllers\Site\CollateralSecureController::class, 'linkAsset'])->name('borrower.collateral-secure.link');
            Route::post('/borrower/applications/{application}/collateral-secure/pay', [\App\Http\Controllers\Site\CollateralSecureController::class, 'payFee'])->name('borrower.collateral-secure.pay');
            Route::post('/borrower/applications/{application}/collateral-secure/pay-valuation', [\App\Http\Controllers\Site\CollateralSecureController::class, 'payValuationFee'])->name('borrower.collateral-secure.pay-valuation');
            Route::post('/borrower/applications/{application}/collateral-secure/buy-insurance', [\App\Http\Controllers\Site\CollateralSecureController::class, 'buyInsurance'])->name('borrower.collateral-secure.buy-insurance');
            Route::post('/borrower/applications/{application}/post-approval-fees/fast-track', [\App\Http\Controllers\Site\BorrowerController::class, 'toggleFastTrack'])->name('borrower.application.post-approval-fees.fast-track');
            Route::post('/borrower/guaranteed/{customerGuarantor}/collateral-secure/respond', [\App\Http\Controllers\Site\CollateralSecureController::class, 'guarantorRespond'])->name('borrower.collateral-secure.guarantor-respond');
            Route::post('/borrower/guaranteed/{customerGuarantor}/collateral-secure/link-asset', [\App\Http\Controllers\Site\CollateralSecureController::class, 'guarantorLinkAsset'])->name('borrower.collateral-secure.guarantor-link');
            Route::post('/borrower/guaranteed/{customerGuarantor}/collateral-secure/buy-insurance', [\App\Http\Controllers\Site\CollateralSecureController::class, 'guarantorBuyInsurance'])->name('borrower.collateral-secure.guarantor-buy-insurance');
            Route::get('/borrower/loan-profile/draft/{draft}',     [\App\Http\Controllers\Site\BorrowerController::class, 'loanProfileDraft'])->name('borrower.loan-profile.draft');
            Route::post('/borrower/loan-profile/draft/{draft}/amount', [\App\Http\Controllers\Site\BorrowerController::class, 'updateDraftAmount'])->name('borrower.draft.amount');
            Route::post('/borrower/applications/{application}/documents', [\App\Http\Controllers\Site\BorrowerController::class, 'uploadApplicationDocument'])->name('borrower.application.documents.store');
            Route::post('/borrower/applications/{application}/document-requests/{documentRequest}', [\App\Http\Controllers\Site\BorrowerController::class, 'uploadDocumentRequest'])->name('borrower.application.document-requests.store');
            Route::get('/borrower/applications/{application}/agreement',                [\App\Http\Controllers\Site\LoanAgreementController::class, 'show'])      ->name('borrower.application.agreement');
            Route::get('/borrower/applications/{application}/rejection-letter',         [\App\Http\Controllers\Site\LoanAgreementController::class, 'showRejectionLetter'])->name('borrower.application.rejection-letter');
            Route::get('/borrower/applications/{application}/rejection-letter/download',[\App\Http\Controllers\Site\LoanAgreementController::class, 'downloadRejectionLetter'])->name('borrower.application.rejection-letter.download');
            Route::post('/borrower/applications/{application}/agreement/otp',           [\App\Http\Controllers\Site\LoanAgreementController::class, 'requestOtp'])->name('borrower.application.agreement.otp');
            Route::post('/borrower/applications/{application}/agreement/sign',          [\App\Http\Controllers\Site\LoanAgreementController::class, 'sign'])      ->name('borrower.application.agreement.sign');
            Route::post('/borrower/applications/{application}/agreement/accept',        [\App\Http\Controllers\Site\LoanAgreementController::class, 'acceptOffer'])->name('borrower.application.agreement.accept');
            Route::post('/borrower/applications/{application}/agreement/decline',       [\App\Http\Controllers\Site\LoanAgreementController::class, 'declineOffer'])->name('borrower.application.agreement.decline');
            Route::get('/borrower/applications/{application}/contract',                [\App\Http\Controllers\Site\LoanAgreementController::class, 'showContract'])->name('borrower.application.contract');
            Route::post('/borrower/applications/{application}/contract/otp',            [\App\Http\Controllers\Site\LoanAgreementController::class, 'requestContractOtp'])->name('borrower.application.contract.otp');
            Route::post('/borrower/applications/{application}/contract/sign',           [\App\Http\Controllers\Site\LoanAgreementController::class, 'signContract'])->name('borrower.application.contract.sign');
            Route::post('/borrower/applications/{application}/contract/accept',       [\App\Http\Controllers\Site\LoanAgreementController::class, 'acceptContract'])->name('borrower.application.contract.accept');
            Route::post('/borrower/applications/{application}/contract/decline',        [\App\Http\Controllers\Site\LoanAgreementController::class, 'declineContract'])->name('borrower.application.contract.decline');
            Route::get('/borrower/agreements/{agreement}/download',                     [\App\Http\Controllers\Site\LoanAgreementController::class, 'download']) ->name('borrower.agreement.download');
            Route::get('/borrower/loans',                          [\App\Http\Controllers\Site\BorrowerController::class, 'loans'])        ->name('borrower.loans');
            Route::get('/borrower/loans/{loan}',                   [\App\Http\Controllers\Site\BorrowerController::class, 'showLoan'])   ->name('borrower.loans.show');
            Route::get('/borrower/loans/{loan}/final-contract',    [\App\Http\Controllers\Site\BorrowerController::class, 'finalContract'])->name('borrower.loans.final-contract');
            Route::get('/borrower/schedule/{loan?}',               [\App\Http\Controllers\Site\BorrowerController::class, 'schedule'])     ->name('borrower.schedule');
            Route::get('/borrower/loans/{loan}/restructure',       [\App\Http\Controllers\Site\BorrowerController::class, 'restructureLoan'])->name('borrower.loans.restructure');
            Route::post('/borrower/loans/{loan}/restructure',      [\App\Http\Controllers\Site\BorrowerController::class, 'submitRestructure'])->name('borrower.loans.restructure.submit');
            Route::get('/borrower/loans/{loan}/top-up',            [\App\Http\Controllers\Site\BorrowerController::class, 'topUpLoan'])->name('borrower.loans.top-up');
            Route::post('/borrower/loans/{loan}/top-up',           [\App\Http\Controllers\Site\BorrowerController::class, 'submitTopUp'])->name('borrower.loans.top-up.submit');
            Route::get('/borrower/payments',                       [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'index'])       ->name('borrower.payments');
            Route::get('/borrower/payments/new',                   [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'create'])      ->name('borrower.payments.create');
            Route::post('/borrower/payments',                      [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'store'])       ->name('borrower.payments.store');
            Route::get('/borrower/payments/refund/{borrowerRefund}', [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'showRefund'])->name('borrower.payments.refund');
            Route::get('/borrower/payments/{payment}',             [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'show'])        ->name('borrower.payments.show');
            Route::get('/borrower/payments/{payment}/status',      [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'status'])      ->name('borrower.payments.status');
            Route::post('/borrower/payments/{payment}/pay',        [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'pay'])         ->name('borrower.payments.pay');
            Route::post('/borrower/payments/{payment}/retry',      [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'retry'])       ->name('borrower.payments.retry');
            Route::post('/borrower/payments/{payment}/gate',       [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'returnToGate'])->name('borrower.payments.gate');
            Route::post('/borrower/payments/{payment}/phone',      [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'updatePhone'])->name('borrower.payments.phone');
            Route::post('/borrower/payments/{payment}/switch-bank',[\App\Http\Controllers\Site\BorrowerPaymentController::class, 'switchBank'])->name('borrower.payments.switch-bank');
            Route::post('/borrower/payments/{payment}/proof',      [\App\Http\Controllers\Site\BorrowerPaymentController::class, 'uploadProof']) ->name('borrower.payments.proof');
            Route::get('/borrower/documents',                      [\App\Http\Controllers\Site\BorrowerController::class, 'documents'])    ->name('borrower.documents');
            Route::post('/borrower/documents',                     [\App\Http\Controllers\Site\BorrowerController::class, 'uploadDocument'])->name('borrower.documents.store');
            Route::get('/borrower/kyc',                            [\App\Http\Controllers\Site\BorrowerController::class, 'kyc'])          ->name('borrower.kyc');
            Route::post('/borrower/kyc',                           [\App\Http\Controllers\Site\BorrowerController::class, 'uploadKyc'])    ->name('borrower.kyc.store');
            Route::get('/borrower/face-verification',              [\App\Http\Controllers\Site\BorrowerController::class, 'faceVerification'])->name('borrower.face-verification');
            Route::post('/borrower/face-verification/retake',      [\App\Http\Controllers\Site\BorrowerController::class, 'retakeFaceVerification'])->name('borrower.face-verification.retake');
            Route::post('/borrower/face-verification/submit',      [\App\Http\Controllers\Site\BorrowerController::class, 'submitFaceVerification'])->name('borrower.face-verification.submit');
            Route::post('/borrower/face-verification/{angle}',     [\App\Http\Controllers\Site\BorrowerController::class, 'uploadFaceVerification'])->name('borrower.face-verification.store')->where('angle', 'front|left|right|holding_nida');
            Route::delete('/borrower/face-verification/{angle}',   [\App\Http\Controllers\Site\BorrowerController::class, 'removeFaceVerification'])->name('borrower.face-verification.destroy')->where('angle', 'front|left|right|holding_nida');
            Route::get('/borrower/guarantors',                     [\App\Http\Controllers\Site\BorrowerController::class, 'guarantors'])   ->name('borrower.guarantors');
            Route::post('/borrower/guarantors',                    [\App\Http\Controllers\Site\BorrowerController::class, 'addGuarantor']) ->name('borrower.guarantors.store');
            Route::get('/borrower/guarantor-requests', fn () => redirect()->route('site.borrower.loans', ['tab' => 'guarantor']))->name('borrower.guarantor-requests');
            Route::get('/borrower/guarantor-requests/{customerGuarantor}', [\App\Http\Controllers\Site\BorrowerController::class, 'showGuarantorRequest'])->name('borrower.guarantor-requests.show');
            Route::post('/borrower/guarantor-requests/{customerGuarantor}', [\App\Http\Controllers\Site\BorrowerController::class, 'respondGuarantorRequest'])->name('borrower.guarantor-requests.respond');
            Route::get('/borrower/guaranteed/{customerGuarantor}', [\App\Http\Controllers\Site\BorrowerController::class, 'showGuaranteedLoan'])->name('borrower.guaranteed.show');
            Route::get('/borrower/guarantor/onboarding', [\App\Http\Controllers\Site\GuarantorOnboardingController::class, 'show'])->name('guarantor.onboarding');
            Route::post('/borrower/guarantor/onboarding', [\App\Http\Controllers\Site\GuarantorOnboardingController::class, 'complete'])->name('guarantor.onboarding.complete');
            Route::get('/borrower/group-member/application', [\App\Http\Controllers\Site\GroupMemberApplicationController::class, 'show'])->name('group-member.application');
            Route::get('/borrower/group-member/onboarding', [\App\Http\Controllers\Site\GroupMemberOnboardingController::class, 'show'])->name('group-member.onboarding');
            Route::post('/borrower/group-member/onboarding', [\App\Http\Controllers\Site\GroupMemberOnboardingController::class, 'complete'])->name('group-member.onboarding.complete');
            Route::get('/borrower/applications/{application}/group-contract', [\App\Http\Controllers\Site\GroupContractController::class, 'show'])->name('borrower.group-contract.show');
            Route::get('/borrower/applications/{application}/group-contract-progress', [\App\Http\Controllers\Site\GroupContractProgressController::class, 'borrower'])->name('borrower.group-contract.progress');
            Route::post('/borrower/applications/{application}/group-contract/sign', [\App\Http\Controllers\Site\GroupContractController::class, 'sign'])->name('borrower.group-contract.sign');
            Route::post('/borrower/applications/{application}/group-contract/decline', [\App\Http\Controllers\Site\GroupContractController::class, 'decline'])->name('borrower.group-contract.decline');
            Route::post('/borrower/applications/{application}/group-members/{loan_group_member}/replace-internal', [\App\Http\Controllers\Site\GroupMemberReplacementController::class, 'replaceInternal'])->name('borrower.group-member.replace-internal');
            Route::post('/borrower/applications/{application}/group-members/{loan_group_member}/replace-external', [\App\Http\Controllers\Site\GroupMemberReplacementController::class, 'replaceExternal'])->name('borrower.group-member.replace-external');
            Route::get('/borrower/applications/{application}/post-approval-fees', [\App\Http\Controllers\Site\BorrowerController::class, 'postApprovalFees'])->name('borrower.application.post-approval-fees');
            Route::get('/borrower/applications/{application}/disbursement-details', [\App\Http\Controllers\Site\BorrowerController::class, 'disbursementDetails'])->name('borrower.application.disbursement-details');
            Route::post('/borrower/applications/{application}/disbursement-details/confirm', [\App\Http\Controllers\Site\BorrowerController::class, 'confirmDisbursementDetails'])->name('borrower.application.disbursement-details.confirm');
            Route::post('/borrower/marketplace/request', [\App\Http\Controllers\Site\AssetMarketplaceController::class, 'storeRequest'])->name('borrower.marketplace.request');
            Route::get('/borrower/kyc-reconfirm',                  [\App\Http\Controllers\Site\BorrowerController::class, 'kycReconfirm'])->name('borrower.kyc-reconfirm');
            Route::put('/borrower/kyc-reconfirm',                  [\App\Http\Controllers\Site\BorrowerController::class, 'updateKycReconfirm'])->name('borrower.kyc-reconfirm.update');
            Route::get('/borrower/guarantor-notifications',        [\App\Http\Controllers\Site\BorrowerController::class, 'guarantorNotifications'])->name('borrower.guarantor-notifications');
            Route::post('/borrower/guarantor-notifications/read',  [\App\Http\Controllers\Site\BorrowerController::class, 'guarantorMarkNotificationsRead'])->name('borrower.guarantor-notifications.read');
            Route::post('/borrower/guarantor-notifications/clear-all', [\App\Http\Controllers\Site\BorrowerController::class, 'guarantorClearAllNotifications'])->name('borrower.guarantor-notifications.clear-all');
            Route::get('/borrower/notifications',                  [\App\Http\Controllers\Site\BorrowerController::class, 'notifications'])->name('borrower.notifications');
            Route::get('/borrower/notifications/preview',          [\App\Http\Controllers\Site\BorrowerController::class, 'notificationPreview'])->name('borrower.notifications.preview');
            Route::get('/borrower/notifications/{notification}/go', [\App\Http\Controllers\Site\BorrowerController::class, 'followNotificationCta'])->name('borrower.notifications.go');
            Route::post('/borrower/notifications/read',            [\App\Http\Controllers\Site\BorrowerController::class, 'markNotificationsRead'])->name('borrower.notifications.read');
            Route::post('/borrower/notifications/{notification}/read', [\App\Http\Controllers\Site\BorrowerController::class, 'markNotificationRead'])->name('borrower.notifications.item.read');
            Route::delete('/borrower/notifications/{notification}', [\App\Http\Controllers\Site\BorrowerController::class, 'clearNotification'])->name('borrower.notifications.item.clear');
            Route::post('/borrower/notifications/clear-all',       [\App\Http\Controllers\Site\BorrowerController::class, 'clearAllNotifications'])->name('borrower.notifications.clear-all');
            Route::get('/borrower/profile/wizard',              [\App\Http\Controllers\Site\BorrowerController::class, 'profileWizard'])->name('borrower.profile.wizard');
            Route::get('/borrower/settings',                       [\App\Http\Controllers\Site\BorrowerController::class, 'settings'])->name('borrower.settings');
            Route::put('/borrower/settings/preferences',           [\App\Http\Controllers\Site\BorrowerController::class, 'updateSettingsPreferences'])->name('borrower.settings.preferences');
            Route::get('/borrower/profile/{section?}',             [\App\Http\Controllers\Site\BorrowerController::class, 'profile'])->name('borrower.profile')->where('section', 'hub|personal|activity|residence|kin|kyc|security|payment|assets|membership');
            Route::put('/borrower/profile/{section}',              [\App\Http\Controllers\Site\BorrowerController::class, 'updateProfile'])->name('borrower.profile.update')->where('section', 'personal|activity|residence|kin|kyc|payment');
            Route::post('/borrower/profile/assets',                  [\App\Http\Controllers\Site\BorrowerController::class, 'storeAsset'])->name('borrower.profile.assets.store');
            Route::put('/borrower/profile/assets/{asset}',           [\App\Http\Controllers\Site\BorrowerController::class, 'updateAsset'])->name('borrower.profile.assets.update');
            Route::delete('/borrower/profile/assets/{asset}',        [\App\Http\Controllers\Site\BorrowerController::class, 'destroyAsset'])->name('borrower.profile.assets.destroy');
            Route::post('/borrower/profile/assets/{asset}/documents', [\App\Http\Controllers\Site\BorrowerController::class, 'replaceAssetDocument'])->name('borrower.profile.assets.documents.replace');
            Route::post('/borrower/profile/assets/{asset}/photos',   [\App\Http\Controllers\Site\BorrowerController::class, 'addAssetPhotos'])->name('borrower.profile.assets.photos.add');
            Route::post('/borrower/profile/assets/{asset}/photos/replace', [\App\Http\Controllers\Site\BorrowerController::class, 'replaceAssetPhoto'])->name('borrower.profile.assets.photos.replace');
            Route::delete('/borrower/profile/assets/{asset}/photos', [\App\Http\Controllers\Site\BorrowerController::class, 'deleteAssetPhoto'])->name('borrower.profile.assets.photos.delete');
            Route::delete('/borrower/profile/documents/{code}',     [\App\Http\Controllers\Site\BorrowerController::class, 'destroyProfileDocument'])->name('borrower.profile.documents.destroy');
            Route::delete('/borrower/profile/payment-accounts/{account}', [\App\Http\Controllers\Site\BorrowerController::class, 'destroyPaymentAccount'])->name('borrower.profile.payment-accounts.destroy');
            Route::post('/borrower/profile/payment-accounts/{account}/default', [\App\Http\Controllers\Site\BorrowerController::class, 'setDefaultPaymentAccount'])->name('borrower.profile.payment-accounts.default');
            Route::post('/borrower/profile/nida/verify',           [\App\Http\Controllers\Site\BorrowerController::class, 'verifyNida'])->name('borrower.profile.nida.verify');
            Route::post('/borrower/profile/nida/accept-names',    [\App\Http\Controllers\Site\BorrowerController::class, 'acceptNidaNames'])->name('borrower.profile.nida.accept-names');
            Route::post('/borrower/profile/nida/confirm',          [\App\Http\Controllers\Site\BorrowerController::class, 'confirmNidaCandidate'])->name('borrower.profile.nida.confirm');
            Route::put('/borrower/profile/security/pin',           [\App\Http\Controllers\Site\BorrowerController::class, 'updatePin'])->name('borrower.profile.pin.update');
            Route::put('/borrower/profile/notifications',          [\App\Http\Controllers\Site\BorrowerController::class, 'updateNotificationPreferences'])->name('borrower.profile.notifications.update');
            Route::delete('/borrower/profile/security/devices/{trustedDevice}', [\App\Http\Controllers\Site\BorrowerController::class, 'revokeTrustedDevice'])->name('borrower.profile.devices.revoke');
            Route::get('/borrower/support',                        [\App\Http\Controllers\Site\BorrowerController::class, 'support'])      ->name('borrower.support');
            Route::get('/borrower/refunds',                        [\App\Http\Controllers\Site\BorrowerRefundController::class, 'index'])->name('borrower.refunds');
            Route::post('/borrower/refunds/{borrowerRefund}/details', [\App\Http\Controllers\Site\BorrowerRefundController::class, 'submitDetails'])->name('borrower.refunds.details');
        });

        $registerPartnerPortal = require base_path('routes/partner_portal.php');

        // ---- Partner portal (/partner primary, /vendor legacy) ----
        Route::middleware(['two_factor:partner', 'partner.pin'])->group(function () use ($registerPartnerPortal) {
            $registerPartnerPortal('partner', 'partner.', registerDashboard: false);
            $registerPartnerPortal('vendor', 'vendor.');

            Route::get('/vendor-portal', fn () => redirect()->route('site.partner.dashboard'));
            Route::get('/partner-portal', fn () => redirect()->route('site.partner.dashboard'));

            Route::prefix('partner/supplier')->name('partner.supplier.')->middleware('supplier.portal')->group(function () {
                Route::get('/', [\App\Http\Controllers\Site\SupplierController::class, 'dashboard'])->name('dashboard');
                Route::get('/assets', [\App\Http\Controllers\Site\SupplierController::class, 'assets'])->name('assets');
                Route::get('/assets/create', [\App\Http\Controllers\Site\SupplierController::class, 'createAsset'])->name('assets.create');
                Route::post('/assets', [\App\Http\Controllers\Site\SupplierController::class, 'storeAsset'])->name('assets.store');
                Route::get('/assets/{asset}/edit', [\App\Http\Controllers\Site\SupplierController::class, 'editAsset'])->name('assets.edit');
                Route::put('/assets/{asset}', [\App\Http\Controllers\Site\SupplierController::class, 'updateAsset'])->name('assets.update');
                Route::get('/requests', [\App\Http\Controllers\Site\SupplierController::class, 'requests'])->name('requests');
                Route::get('/reservations', [\App\Http\Controllers\Site\SupplierController::class, 'reservations'])->name('reservations');
                Route::get('/applications', [\App\Http\Controllers\Site\SupplierController::class, 'applications'])->name('applications');
                Route::get('/delivered', [\App\Http\Controllers\Site\SupplierController::class, 'delivered'])->name('delivered');
                Route::post('/reservations/{reservation}', [\App\Http\Controllers\Site\SupplierController::class, 'updateReservation'])->name('reservations.update');
                Route::post('/requests/{assetRequest}', [\App\Http\Controllers\Site\SupplierController::class, 'updateRequest'])->name('requests.update');
                Route::get('/settlements', [\App\Http\Controllers\Site\SupplierController::class, 'settlements'])->name('settlements');
                Route::get('/profile/{section?}', [\App\Http\Controllers\Site\SupplierController::class, 'profile'])->name('profile')->where('section', 'hub|personal|face|residence|activity|payment');
                Route::put('/profile/{section}', [\App\Http\Controllers\Site\SupplierController::class, 'updateProfile'])->name('profile.update')->where('section', 'personal|face|residence|activity|payment');
                Route::get('/documents', [\App\Http\Controllers\Site\SupplierController::class, 'documents'])->name('documents');
                Route::post('/documents', [\App\Http\Controllers\Site\SupplierController::class, 'uploadDocument'])->name('documents.store');
                Route::get('/settings', [\App\Http\Controllers\Site\SupplierController::class, 'settings'])->name('settings');
                Route::put('/settings/pin', [\App\Http\Controllers\Site\PartnerAccountController::class, 'updatePin'])->name('settings.pin');
            });

            Route::redirect('/supplier', '/partner/supplier');

            Route::prefix('partner/affiliate')->name('partner.affiliate.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Site\AffiliateController::class, 'dashboard'])->name('dashboard');
                Route::get('/referrals', [\App\Http\Controllers\Site\AffiliateController::class, 'referrals'])->name('referrals');
                Route::get('/wallet', [\App\Http\Controllers\Site\AffiliateController::class, 'wallet'])->name('wallet');
                Route::post('/wallet/{payment}/dispute', [\App\Http\Controllers\Site\AffiliateController::class, 'disputePayment'])->name('wallet.dispute');
            Route::post('/wallet/payout-request', [\App\Http\Controllers\Site\AffiliateController::class, 'requestPayout'])->name('wallet.payout-request');
                Route::get('/profile/{section?}', [\App\Http\Controllers\Site\AffiliateController::class, 'profile'])->name('profile')->where('section', 'hub|personal|face|residence|activity|payment');
                Route::put('/profile/{section}', [\App\Http\Controllers\Site\AffiliateController::class, 'updateProfile'])->name('profile.update')->where('section', 'personal|face|residence|activity|payment');
                Route::get('/documents', [\App\Http\Controllers\Site\AffiliateController::class, 'documents'])->name('documents');
                Route::put('/documents', [\App\Http\Controllers\Site\AffiliateController::class, 'updateDocuments'])->name('documents.update');
                Route::get('/settings', [\App\Http\Controllers\Site\AffiliateController::class, 'settings'])->name('settings');
                Route::put('/settings/pin', [\App\Http\Controllers\Site\PartnerAccountController::class, 'updatePin'])->name('settings.pin');
                Route::get('/membership/pay', [\App\Http\Controllers\Site\AffiliateController::class, 'membershipPayForm'])->name('membership.pay');
                Route::post('/membership/pay', [\App\Http\Controllers\Site\AffiliateController::class, 'membershipPay'])->name('membership.pay.post');
            });
        });

        Route::prefix('affiliate-portal')->name('affiliate.')->middleware(['two_factor:partner', 'partner.pin'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Site\AffiliateController::class, 'dashboard'])->name('dashboard');
            Route::get('/referrals', [\App\Http\Controllers\Site\AffiliateController::class, 'referrals'])->name('referrals');
            Route::get('/wallet', [\App\Http\Controllers\Site\AffiliateController::class, 'wallet'])->name('wallet');
            Route::post('/wallet/{payment}/dispute', [\App\Http\Controllers\Site\AffiliateController::class, 'disputePayment'])->name('wallet.dispute');
            Route::post('/wallet/payout-request', [\App\Http\Controllers\Site\AffiliateController::class, 'requestPayout'])->name('wallet.payout-request');
            Route::get('/notifications', [\App\Http\Controllers\Site\AffiliateController::class, 'notifications'])->name('notifications');
            Route::get('/profile/{section?}', [\App\Http\Controllers\Site\AffiliateController::class, 'profile'])->name('profile')->where('section', 'hub|personal|face|residence|activity|payment');
            Route::put('/profile/{section}', [\App\Http\Controllers\Site\AffiliateController::class, 'updateProfile'])->name('profile.update')->where('section', 'personal|face|residence|activity|payment');
            Route::get('/documents', [\App\Http\Controllers\Site\AffiliateController::class, 'documents'])->name('documents');
            Route::put('/documents', [\App\Http\Controllers\Site\AffiliateController::class, 'updateDocuments'])->name('documents.update');
            Route::get('/settings', [\App\Http\Controllers\Site\AffiliateController::class, 'settings'])->name('settings');
            Route::put('/settings/pin', [\App\Http\Controllers\Site\PartnerAccountController::class, 'updatePin'])->name('settings.pin');
            Route::get('/membership/pay', [\App\Http\Controllers\Site\AffiliateController::class, 'membershipPayForm'])->name('membership.pay');
            Route::post('/membership/pay', [\App\Http\Controllers\Site\AffiliateController::class, 'membershipPay'])->name('membership.pay.post');
        });
        Route::redirect('/partner/affiliate-portal', '/partner/affiliate');

        Route::prefix('supplier')->name('supplier.')->middleware(['partner.pin', 'supplier.portal'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Site\SupplierController::class, 'dashboard'])->name('dashboard');
            Route::get('/assets', [\App\Http\Controllers\Site\SupplierController::class, 'assets'])->name('assets');
            Route::get('/assets/create', [\App\Http\Controllers\Site\SupplierController::class, 'createAsset'])->name('assets.create');
            Route::post('/assets', [\App\Http\Controllers\Site\SupplierController::class, 'storeAsset'])->name('assets.store');
            Route::get('/assets/{asset}/edit', [\App\Http\Controllers\Site\SupplierController::class, 'editAsset'])->name('assets.edit');
            Route::put('/assets/{asset}', [\App\Http\Controllers\Site\SupplierController::class, 'updateAsset'])->name('assets.update');
            Route::get('/requests', [\App\Http\Controllers\Site\SupplierController::class, 'requests'])->name('requests');
            Route::get('/reservations', [\App\Http\Controllers\Site\SupplierController::class, 'reservations'])->name('reservations');
            Route::get('/applications', [\App\Http\Controllers\Site\SupplierController::class, 'applications'])->name('applications');
            Route::get('/delivered', [\App\Http\Controllers\Site\SupplierController::class, 'delivered'])->name('delivered');
            Route::post('/reservations/{reservation}', [\App\Http\Controllers\Site\SupplierController::class, 'updateReservation'])->name('reservations.update');
            Route::post('/requests/{assetRequest}', [\App\Http\Controllers\Site\SupplierController::class, 'updateRequest'])->name('requests.update');
            Route::get('/settlements', [\App\Http\Controllers\Site\SupplierController::class, 'settlements'])->name('settlements');
            Route::get('/notifications', [\App\Http\Controllers\Site\SupplierController::class, 'notifications'])->name('notifications');
            Route::get('/profile/{section?}', [\App\Http\Controllers\Site\SupplierController::class, 'profile'])->name('profile')->where('section', 'hub|personal|face|residence|activity|payment');
            Route::put('/profile/{section}', [\App\Http\Controllers\Site\SupplierController::class, 'updateProfile'])->name('profile.update')->where('section', 'personal|face|residence|activity|payment');
            Route::get('/documents', [\App\Http\Controllers\Site\SupplierController::class, 'documents'])->name('documents');
            Route::post('/documents', [\App\Http\Controllers\Site\SupplierController::class, 'uploadDocument'])->name('documents.store');
            Route::get('/settings', [\App\Http\Controllers\Site\SupplierController::class, 'settings'])->name('settings');
            Route::put('/settings/pin', [\App\Http\Controllers\Site\PartnerAccountController::class, 'updatePin'])->name('settings.pin');
        });

        // ---- Investor / Capital Lender portal ----
        Route::middleware('partner.pin')->group(function () {
        Route::get('/investor',                                 [\App\Http\Controllers\Site\InvestorController::class, 'dashboard'])    ->name('investor.dashboard');
        Route::get('/investor/pools',                           [\App\Http\Controllers\Site\InvestorController::class, 'pools'])        ->name('investor.pools');
        Route::get('/investor/pools/{pool}',                    [\App\Http\Controllers\Site\InvestorController::class, 'pool'])         ->name('investor.pool');
        Route::post('/investor/pools/{pool}/invest',            [\App\Http\Controllers\Site\InvestorController::class, 'invest'])       ->name('investor.pool.invest');
        Route::get('/investor/investments',                     [\App\Http\Controllers\Site\InvestorController::class, 'investments'])  ->name('investor.investments');
        Route::get('/investor/funded-loans',                    [\App\Http\Controllers\Site\InvestorController::class, 'fundedLoans'])->name('investor.funded-loans');
        Route::get('/investor/investments/{investment}',        [\App\Http\Controllers\Site\InvestorController::class, 'investment'])   ->name('investor.investment');
        Route::get('/investor/returns',                         [\App\Http\Controllers\Site\InvestorController::class, 'returns'])      ->name('investor.returns');
        Route::get('/investor/analytics',                       [\App\Http\Controllers\Site\InvestorController::class, 'analytics'])    ->name('investor.analytics');
        Route::get('/investor/transactions',                    [\App\Http\Controllers\Site\InvestorController::class, 'transactions']) ->name('investor.transactions');
        Route::get('/investor/wallet',                          [\App\Http\Controllers\Site\InvestorController::class, 'wallet'])       ->name('investor.wallet');
        Route::post('/investor/wallet/deposit',                 [\App\Http\Controllers\Site\InvestorController::class, 'deposit'])      ->name('investor.wallet.deposit');
        Route::post('/investor/wallet/withdraw',                [\App\Http\Controllers\Site\InvestorController::class, 'withdraw'])     ->name('investor.wallet.withdraw');
        Route::get('/investor/documents',                       [\App\Http\Controllers\Site\InvestorController::class, 'documents'])    ->name('investor.documents');
        Route::get('/investor/documents/download/{kind}',       [\App\Http\Controllers\Site\InvestorController::class, 'downloadReport'])->name('investor.documents.download');
        Route::get('/investor/notifications',                   [\App\Http\Controllers\Site\InvestorController::class, 'notifications'])->name('investor.notifications');
        Route::get('/investor/profile/{section?}',               [\App\Http\Controllers\Site\InvestorController::class, 'profile'])      ->name('investor.profile')->where('section', 'hub|personal|face|residence|activity|payment');
        Route::put('/investor/profile/{section}',                [\App\Http\Controllers\Site\InvestorController::class, 'updateProfile'])->name('investor.profile.update')->where('section', 'personal|face|residence|activity|payment');
        Route::get('/investor/settings',                        [\App\Http\Controllers\Site\InvestorController::class, 'settings'])     ->name('investor.settings');
        Route::put('/investor/settings/pin',                    [\App\Http\Controllers\Site\PartnerAccountController::class, 'updatePin'])->name('investor.settings.pin');
        Route::get('/investor/support',                         [\App\Http\Controllers\Site\InvestorController::class, 'support'])      ->name('investor.support');
        Route::get('/investor-portal', fn () => redirect()->route('site.investor.dashboard'));
        });
    });
});

Route::prefix('auth/two-factor')->name('auth.two-factor.')->group(function () {
    Route::get('challenge', [WebTwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('verify', [WebTwoFactorController::class, 'verifyChallenge'])->name('verify');
    Route::get('setup', [WebTwoFactorController::class, 'setup'])->name('setup');
    Route::post('confirm-setup', [WebTwoFactorController::class, 'confirmSetup'])->name('confirm-setup');
});

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [StaffAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [StaffAuthController::class, 'login']);
    });

    Route::middleware(['auth:admin', 'staff', 'two_factor:staff'])->group(function () {
        Route::post('logout', [StaffAuthController::class, 'logout'])->name('logout');
        Route::get('/', [StaffPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('security', [StaffPortalController::class, 'security'])->name('security');
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
    Route::middleware(['auth:admin', 'two_factor:admin'])->group(function () use ($registerResource) {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', DashboardController::class)->middleware('console')->name('dashboard');

        // Applications
        Route::view('loan-applications/pipeline/under-review',  'admin.loan-applications.pipeline-under-review') ->name('loan-applications.pipeline.under-review');
        Route::view('loan-applications/pipeline/system-sorted', 'admin.loan-applications.pipeline-system-sorted')->name('loan-applications.pipeline.system-sorted');
        Route::view('loan-applications/pipeline/approved',      'admin.loan-applications.pipeline-approved')     ->name('loan-applications.pipeline.approved');
        Route::view('loan-applications/pipeline/disbursement',  'admin.loan-applications.pipeline-disbursement') ->name('loan-applications.pipeline.disbursement');
        Route::view('loan-applications/new',              'admin.loan-applications.new')              ->name('loan-applications.new');
        Route::view('loan-applications/pending-documents','admin.loan-applications.pending-documents')->name('loan-applications.pending-documents');
        Route::view('loan-applications/under-review',     'admin.loan-applications.under-review')     ->name('loan-applications.under-review');
        Route::view('loan-applications/pre-approvals',    'admin.loan-applications.pre-approvals')    ->name('loan-applications.pre-approvals');
        Route::get('credit-team', [\App\Http\Controllers\Admin\CreditTeamController::class, 'index'])
            ->middleware('permission:applications.view')
            ->name('credit-team.index');
        Route::post('credit-team', [\App\Http\Controllers\Admin\CreditTeamController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('credit-team.store');
        Route::get('teams/screening', [\App\Http\Controllers\Admin\CreditTeamWorkspaceController::class, 'screening'])
            ->middleware('permission:applications.view')
            ->name('teams.screening');
        Route::get('teams/committee', [\App\Http\Controllers\Admin\CreditTeamWorkspaceController::class, 'committee'])
            ->middleware('permission:applications.view')
            ->name('teams.committee');
        Route::get('teams/management', [\App\Http\Controllers\Admin\CreditTeamWorkspaceController::class, 'management'])
            ->middleware('permission:applications.view')
            ->name('teams.management');
        Route::redirect('loan-applications/final-approvals', '/admin/loan-applications/pipeline/approved')->name('loan-applications.final-approvals');
        Route::view('loan-applications/rejected',         'admin.loan-applications.rejected')         ->name('loan-applications.rejected');
        Route::view('loan-applications/incomplete',      'admin.loan-applications.incomplete')      ->name('loan-applications.incomplete');
        Route::get('loan-applications/incomplete/{draft}', [\App\Http\Controllers\Admin\LoanApplicationDraftController::class, 'show'])
            ->name('loan-applications.incomplete.show');
        Route::get('loan-applications/wizard-data/{customer}', [LoanApplicationController::class, 'wizardCustomerData'])
            ->name('loan-applications.wizard-data');
        $registerResource('loan-applications', 'loan_application', LoanApplicationController::class);
        Route::post('loan-applications/{loan_application}/agreement', [\App\Http\Controllers\Admin\LoanAgreementController::class, 'generate'])
            ->name('loan-applications.agreement.generate');
        Route::post('loan-applications/{loan_application}/offer/resend', [\App\Http\Controllers\Admin\LoanAgreementController::class, 'resendOffer'])
            ->name('loan-applications.offer.resend');
        Route::post('loan-applications/{loan_application}/offer/reissue', [\App\Http\Controllers\Admin\LoanAgreementController::class, 'reissueOffer'])
            ->name('loan-applications.offer.reissue');
        Route::post('loan-applications/{loan_application}/contract', [\App\Http\Controllers\Admin\LoanAgreementController::class, 'generateContract'])
            ->name('loan-applications.contract.generate');
        Route::get('loan-agreements/{agreement}/download', [\App\Http\Controllers\Site\LoanAgreementController::class, 'download'])
            ->name('loan-agreements.download');
        Route::post('loan-applications/{loan_application}/document-requests', [LoanApplicationDocumentRequestController::class, 'store'])
            ->name('loan-applications.document-requests.store');
        Route::post('loan-applications/{loan_application}/request-guarantor-supplement', [LoanApplicationController::class, 'requestGuarantorSupplement'])
            ->name('loan-applications.request-guarantor-supplement');
        Route::post('loan-applications/{loan_application}/request-collateral-secure', [LoanApplicationController::class, 'requestCollateralSecure'])
            ->name('loan-applications.request-collateral-secure');
        Route::post('loan-applications/{loan_application}/screening-checklist', [LoanApplicationController::class, 'saveScreeningChecklist'])
            ->name('loan-applications.screening-checklist');
        Route::post('loan-applications/{loan_application}/guarantors/{customerGuarantor}/change', [LoanApplicationController::class, 'requestGuarantorChange'])
            ->name('loan-applications.guarantor-change');
        Route::post('loan-applications/{loan_application}/workflow', [LoanApplicationController::class, 'runWorkflow'])
            ->name('loan-applications.workflow');
        Route::post('loan-applications/{loan_application}/capacity-auto-reject/fire', [LoanApplicationController::class, 'fireCapacityAutoReject'])
            ->name('loan-applications.capacity-auto-reject.fire');
        Route::post('loan-applications/{loan_application}/capacity-auto-reject/cancel', [LoanApplicationController::class, 'cancelCapacityAutoReject'])
            ->name('loan-applications.capacity-auto-reject.cancel');
        Route::post('loan-applications/{loan_application}/assign-analyst', [LoanApplicationController::class, 'assignAnalyst'])
            ->name('loan-applications.assign-analyst');
        Route::post('loan-applications/{loan_application}/documents/{document}/verify', [LoanApplicationController::class, 'verifyDocument'])
            ->name('loan-applications.documents.verify');
        Route::post('loan-applications/{loan_application}/documents/{document}/reject', [LoanApplicationController::class, 'rejectDocument'])
            ->name('loan-applications.documents.reject');
        Route::post('loan-applications/{loan_application}/group-members/{loan_group_member}/review', [LoanApplicationController::class, 'reviewGroupMember'])
            ->name('loan-applications.review-group-member');
        Route::post('loan-applications/{loan_application}/group-feedback', [LoanApplicationController::class, 'updateGroupLeaderFeedback'])
            ->name('loan-applications.group-feedback');
        Route::get('loan-applications/{loan_application}/group-contract-progress', [LoanApplicationController::class, 'groupContractProgress'])
            ->name('loan-applications.group-contract-progress');
        Route::post('loan-applications/{loan_application}/group-members/{loan_group_member}/request-replacement', [LoanApplicationController::class, 'requestGroupMemberReplacement'])
            ->name('loan-applications.request-group-member-replacement');
        Route::post('loan-applications/{loan_application}/create-loan', [LoanApplicationController::class, 'createLoan'])
            ->name('loan-applications.create-loan');
        Route::post('loan-application-document-requests/{documentRequest}/satisfy', [LoanApplicationDocumentRequestController::class, 'satisfy'])
            ->name('loan-application-document-requests.satisfy');
        Route::post('loan-application-document-requests/{documentRequest}/reject', [LoanApplicationDocumentRequestController::class, 'reject'])
            ->name('loan-application-document-requests.reject');

        // Customers
        Route::put('customers/{customer}/section/{section}', [CustomerController::class, 'updateSection'])
            ->name('customers.section.update')
            ->where('section', 'personal|residence|activity|kin|account');
        Route::post('customers/{customer}/documents', [CustomerController::class, 'uploadDocument'])
            ->name('customers.documents.store');
        Route::post('customers/{customer}/documents/{document}/verify', [CustomerController::class, 'verifyDocument'])
            ->name('customers.documents.verify');
        Route::post('customers/{customer}/documents/{document}/reject', [CustomerController::class, 'rejectDocument'])
            ->name('customers.documents.reject');
        Route::post('customers/{customer}/nida-unlock', [CustomerController::class, 'unlockNidaIdentity'])
            ->name('customers.nida.unlock');
        $registerResource('customers',     'customer',      CustomerController::class);
        $registerResource('customer-kycs', 'customer_kyc',  CustomerKycController::class);
        Route::get('face-verifications', [FaceVerificationController::class, 'index'])->name('face-verifications.index');
        Route::get('face-verifications/{customer}', [FaceVerificationController::class, 'show'])->name('face-verifications.show');
        Route::post('face-verifications/{customer}/approve', [FaceVerificationController::class, 'approve'])->name('face-verifications.approve');
        Route::post('face-verifications/{customer}/reject', [FaceVerificationController::class, 'reject'])->name('face-verifications.reject');
        Route::post('face-verifications/{customer}/request-retake', [FaceVerificationController::class, 'requestRetake'])->name('face-verifications.request-retake');
        $registerResource('guarantors',    'guarantor',     GuarantorController::class);

        // Loans
        Route::view('loans/active',        'admin.loans.active')       ->name('loans.active');
        Route::view('loans/disbursement',  'admin.loans.disbursement') ->name('loans.disbursement');
        Route::view('loans/arrears',       'admin.loans.arrears')      ->name('loans.arrears');
        Route::view('loans/restructuring', 'admin.loans.restructuring')->name('loans.restructuring');
        Route::view('loans/closed',        'admin.loans.closed')       ->name('loans.closed');
        Route::get('loans/wizard-data/{customer}', [LoanController::class, 'wizardCustomerData'])->name('loans.wizard-data');
        $registerResource('loans',      'loan',      LoanController::class);
        Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/allocate-capital', [LoanController::class, 'allocateCapital'])->name('loans.allocate-capital');
        Route::post('loans/{loan}/clear-capital-allocation', [LoanController::class, 'clearCapitalAllocation'])->name('loans.clear-capital-allocation');
        Route::post('loans/{loan}/reverse-disbursement', [LoanController::class, 'reverseDisbursement'])->name('loans.reverse-disbursement');
        Route::post('loans/{loan}/collection-actions', [LoanController::class, 'addCollectionAction'])->name('loans.collection-actions');
        Route::get('arrear-cases', [ArrearCaseController::class, 'index'])->name('arrear-cases.index');
        Route::get('arrear-cases/{arrearCase}', [ArrearCaseController::class, 'show'])->name('arrear-cases.show');
        Route::put('arrear-cases/{arrearCase}', [ArrearCaseController::class, 'update'])->name('arrear-cases.update');
        Route::post('arrear-cases/{arrearCase}/actions', [ArrearCaseController::class, 'addAction'])->name('arrear-cases.actions');
        Route::post('arrear-cases/{arrearCase}/recovery-assign', [ArrearCaseController::class, 'assignRecoveryPartner'])->name('arrear-cases.recovery-assign');
        Route::post('arrear-cases/{arrearCase}/auction-settle', [ArrearCaseController::class, 'recordAuctionSettlement'])->name('arrear-cases.auction-settle');
        Route::get('write-off-requests', [WriteOffRequestController::class, 'index'])->name('write-off-requests.index');
        Route::get('write-off-requests/{writeOffRequest}', [WriteOffRequestController::class, 'show'])->name('write-off-requests.show');
        Route::post('loans/{loan}/write-off-requests', [WriteOffRequestController::class, 'recommendFromLoan'])->name('loans.write-off-requests.store');
        Route::post('arrear-cases/{arrearCase}/write-off-requests', [WriteOffRequestController::class, 'recommendFromCase'])->name('arrear-cases.write-off-requests.store');

        Route::get('recovery/partners', [RecoveryPartnerController::class, 'index'])->name('recovery.partners.index');
        Route::get('recovery/partners/{type}', [RecoveryPartnerController::class, 'byType'])->name('recovery.partners.type');
        Route::get('origination/valuation-partners', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'valuationIndex'])->name('origination.valuation-partners');
        Route::post('loan-applications/{loan_application}/assign-valuer', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'assignValuer'])->name('loan-applications.assign-valuer');
        Route::post('loan-applications/{loan_application}/collateral/{asset}/uw-status', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'updateCollateralUwStatus'])->name('loan-applications.collateral.uw-status');
        Route::post('loan-applications/{loan_application}/assign-gps', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'assignGpsInstaller'])->name('loan-applications.assign-gps');
        Route::post('loan-applications/{loan_application}/manual-fee', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'addManualFee'])->name('loan-applications.manual-fee');
        Route::post('loan-applications/{loan_application}/post-approval-fees/{fee}', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'updatePostApprovalFee'])->name('loan-applications.post-approval-fees.update');
        Route::post('loan-applications/{loan_application}/reservation-advance', [LoanApplicationController::class, 'advanceReservation'])->name('loan-applications.reservation-advance');
        Route::put('loan-applications/{loan_application}/asset-identifiers', [LoanApplicationController::class, 'updateAssetIdentifiers'])->name('loan-applications.asset-identifiers');
        Route::get('recovery/assignments', [RecoveryAssignmentController::class, 'index'])->name('recovery.assignments.index');
        Route::get('recovery/assignments/{recoveryAssignment}', [RecoveryAssignmentController::class, 'show'])->name('recovery.assignments.show');
        Route::post('recovery/assignments/{recoveryAssignment}/start', [RecoveryAssignmentController::class, 'start'])->name('recovery.assignments.start');
        Route::post('recovery/assignments/{recoveryAssignment}/complete', [RecoveryAssignmentController::class, 'complete'])->name('recovery.assignments.complete');
        Route::post('recovery/assignments/{recoveryAssignment}/escalate', [RecoveryAssignmentController::class, 'escalate'])->name('recovery.assignments.escalate');
        Route::post('write-off-requests/{writeOffRequest}/manager-approve', [WriteOffRequestController::class, 'managerApprove'])->name('write-off-requests.manager-approve');
        Route::post('write-off-requests/{writeOffRequest}/finance-approve', [WriteOffRequestController::class, 'financeApprove'])->name('write-off-requests.finance-approve');
        Route::post('write-off-requests/{writeOffRequest}/reject', [WriteOffRequestController::class, 'reject'])->name('write-off-requests.reject');
        Route::get('loans/{loan}/write-off',  [LoanController::class, 'writeOffForm'])->name('loans.write-off-form');
        Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');
        $registerResource('repayments', 'repayment', RepaymentController::class);
        Route::post('repayments/{repayment}/approve', [RepaymentController::class, 'approve'])->name('repayments.approve');

        // Loan Products
        Route::view('loan-products/interest-fees',  'admin.loan-products.interest-fees') ->name('loan-products.interest-fees');
        Route::view('loan-products/documents',      'admin.loan-products.documents')     ->name('loan-products.documents');
        Route::view('loan-products/approval-rules', 'admin.loan-products.approval-rules')->name('loan-products.approval-rules');
        $registerResource('loan-products', 'loan_product', LoanProductController::class);
        Route::post('loan-products/{loan_product}/regenerate-rate-tiers', [LoanProductController::class, 'regenerateRateTiers'])
            ->name('loan-products.regenerate-rate-tiers');

        Route::get('partners', [\App\Http\Controllers\Admin\PartnersController::class, 'index'])->name('partners.index');
        // Vendors
        // Legacy vendor list URLs → partners hub
        Route::redirect('vendors/applications', '/admin/partners/applications')->name('vendors.applications');
        Route::redirect('vendors/gps-installers', '/admin/partners/gps-installers')->name('vendors.gps-installers');
        Route::redirect('vendors/insurance-providers', '/admin/partners/insurance-providers')->name('vendors.insurance-providers');
        Route::redirect('vendors/valuers', '/admin/partners/valuers')->name('vendors.valuers');
        Route::redirect('vendors/suppliers', '/admin/partners/suppliers')->name('vendors.suppliers');
        Route::redirect('vendors/affiliates', '/admin/partners/affiliates')->name('vendors.affiliates');
        Route::redirect('vendors/tasks', '/admin/partners/tasks')->name('vendors.tasks');
        Route::get('asset-requests', [\App\Http\Controllers\Admin\AssetRequestController::class, 'index'])->name('asset-requests.index')->middleware('permission:marketplace.view,marketplace.manage');
        Route::put('asset-requests/{assetRequest}', [\App\Http\Controllers\Admin\AssetRequestController::class, 'update'])->name('asset-requests.update')->middleware('permission:marketplace.manage');
        Route::get('partner-applications', [\App\Http\Controllers\Admin\PartnerApplicationController::class, 'index'])->name('partner-applications.index');
        Route::get('partner-applications/{partnerApplication}', [\App\Http\Controllers\Admin\PartnerApplicationController::class, 'show'])->name('partner-applications.show');
        Route::put('partner-applications/{partnerApplication}', [\App\Http\Controllers\Admin\PartnerApplicationController::class, 'update'])->name('partner-applications.update');
        Route::middleware('permission:marketplace.view,marketplace.manage')->group(function (): void {
            Route::view('marketplace-assets', 'admin.marketplace-assets.index')->name('marketplace-assets.index');
        });
        Route::middleware('permission:marketplace.manage')->group(function (): void {
            Route::get('marketplace-assets/create', [\App\Http\Controllers\Admin\MarketplaceAssetController::class, 'create'])->name('marketplace-assets.create');
            Route::post('marketplace-assets', [\App\Http\Controllers\Admin\MarketplaceAssetController::class, 'store'])->name('marketplace-assets.store');
        });
        Route::middleware('permission:marketplace.view,marketplace.manage')->group(function (): void {
            Route::get('marketplace-assets/{marketplace_asset}', [\App\Http\Controllers\Admin\MarketplaceAssetController::class, 'show'])->name('marketplace-assets.show');
        });
        Route::middleware('permission:marketplace.manage')->group(function (): void {
            Route::get('marketplace-assets/{marketplace_asset}/edit', [\App\Http\Controllers\Admin\MarketplaceAssetController::class, 'edit'])->name('marketplace-assets.edit');
            Route::put('marketplace-assets/{marketplace_asset}', [\App\Http\Controllers\Admin\MarketplaceAssetController::class, 'update'])->name('marketplace-assets.update');
            Route::delete('marketplace-assets/{marketplace_asset}', [\App\Http\Controllers\Admin\MarketplaceAssetController::class, 'destroy'])->name('marketplace-assets.destroy');
        });
        Route::redirect('vendors', '/admin/partners/all')->name('vendors.index');
        Route::redirect('vendors/create', '/admin/partners/create')->name('vendors.create');
        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
        Route::get('vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
        Route::post('vendors/{vendor}/affiliate-kyc/approve', [VendorController::class, 'approveAffiliateKyc'])->name('vendors.affiliate-kyc.approve');
        Route::post('vendors/{vendor}/affiliate-kyc/reject', [VendorController::class, 'rejectAffiliateKyc'])->name('vendors.affiliate-kyc.reject');
        Route::post('vendors/{vendor}/membership/approve', [VendorController::class, 'approveMembershipPayment'])->name('vendors.membership.approve');
        Route::post('vendors/{vendor}/membership/reject', [VendorController::class, 'rejectMembershipPayment'])->name('vendors.membership.reject');

        $registerAdminPartners = require base_path('routes/admin_partners.php');
        $registerAdminPartners();
        $registerResource('promotions', 'promotion', \App\Http\Controllers\Admin\PromotionController::class);
        $registerResource('profile-sections', 'profile_section', ProfileSectionDefinitionController::class);

        // Capital
        Route::get('capital-funding', [\App\Http\Controllers\Admin\CapitalPartnerFundingController::class, 'index'])->name('capital-funding.index');
        Route::get('capital-funding/funded-loans', [\App\Http\Controllers\Admin\CapitalPartnerFundingController::class, 'fundedLoans'])->name('capital-funding.funded-loans');
        Route::get('capital-funding/withdrawals', [\App\Http\Controllers\Admin\CapitalPartnerFundingController::class, 'withdrawals'])->name('capital-funding.withdrawals');
        Route::post('capital-withdrawal-requests/{capital_withdrawal_request}/approve', [\App\Http\Controllers\Admin\CapitalWithdrawalRequestController::class, 'approve'])->name('capital-withdrawal-requests.approve');
        Route::post('capital-withdrawal-requests/{capital_withdrawal_request}/reject', [\App\Http\Controllers\Admin\CapitalWithdrawalRequestController::class, 'reject'])->name('capital-withdrawal-requests.reject');
        $registerResource('lenders',            'lender',             LenderController::class);
        Route::get('lenders/{lender}/adjust-capital', [LenderController::class, 'adjustCapitalForm'])->name('lenders.adjust-capital');
        Route::post('lenders/{lender}/adjust-capital', [LenderController::class, 'adjustCapital'])->name('lenders.adjust-capital.store');
        Route::post('lenders/{lender}/withdrawal-request', [LenderController::class, 'requestWithdrawal'])->name('lenders.withdrawal-request');
        $registerResource('funding-pools',      'funding_pool',       FundingPoolController::class);
        $registerResource('lender-investments', 'lender_investment',  LenderInvestmentController::class);

        // Finance — operations
        Route::middleware('permission:finance.operations')->group(function () use ($registerResource): void {
            $registerResource('expenses',        'expense',        ExpenseController::class);
            Route::post('expenses/{expense}/post', [ExpenseController::class, 'post'])->name('expenses.post');
            $registerResource('settlements',     'settlement',     SettlementController::class);
            Route::get('vendor-payments', [VendorPaymentController::class, 'index'])->name('vendor-payments.index');
            Route::post('vendor-payments/{vendorPayment}/approve', [VendorPaymentController::class, 'approve'])->name('vendor-payments.approve');
            Route::post('vendor-payments/{vendorPayment}/cancel', [VendorPaymentController::class, 'cancel'])->name('vendor-payments.cancel');
            Route::get('partner-payments', [VendorPaymentController::class, 'index'])->name('partner-payments.index');
            Route::post('partner-payments/{vendorPayment}/approve', [VendorPaymentController::class, 'approve'])->name('partner-payments.approve');
            Route::post('partner-payments/{vendorPayment}/cancel', [VendorPaymentController::class, 'cancel'])->name('partner-payments.cancel');
            Route::get('partner-settlements', [PartnerSettlementController::class, 'index'])->name('partner-settlements.index');
            Route::get('partner-settlements/{partnerSettlement}', [PartnerSettlementController::class, 'show'])->name('partner-settlements.show');
            Route::post('partner-settlements/{partnerSettlement}/approve', [PartnerSettlementController::class, 'approve'])->name('partner-settlements.approve');
            Route::post('partner-settlements/{partnerSettlement}/mark-paid', [PartnerSettlementController::class, 'markPaid'])->name('partner-settlements.mark-paid');

            Route::get('partner-payout-requests', [\App\Http\Controllers\Admin\PartnerPayoutRequestController::class, 'index'])->name('partner-payout-requests.index');
            Route::post('partner-payout-requests/{partnerPayoutRequest}/approve', [\App\Http\Controllers\Admin\PartnerPayoutRequestController::class, 'approve'])->name('partner-payout-requests.approve');
            Route::post('partner-payout-requests/{partnerPayoutRequest}/reject', [\App\Http\Controllers\Admin\PartnerPayoutRequestController::class, 'reject'])->name('partner-payout-requests.reject');
            Route::post('partner-payout-requests/{partnerPayoutRequest}/mark-paid', [\App\Http\Controllers\Admin\PartnerPayoutRequestController::class, 'markPaid'])->name('partner-payout-requests.mark-paid');
            Route::get('borrower-refunds', [\App\Http\Controllers\Admin\BorrowerRefundController::class, 'index'])->name('borrower-refunds.index');
            Route::get('borrower-refunds/{borrowerRefund}', [\App\Http\Controllers\Admin\BorrowerRefundController::class, 'show'])->name('borrower-refunds.show');
            Route::post('borrower-refunds/{borrowerRefund}/pay', [\App\Http\Controllers\Admin\BorrowerRefundController::class, 'markPaid'])->name('borrower-refunds.pay');
            $registerResource('reconciliations', 'reconciliation', ReconciliationController::class);
            Route::get('journal-entries',                [JournalEntryController::class, 'index'])->name('journal-entries.index');
            Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show'])->name('journal-entries.show');
        });

        // Reports — operational
        Route::middleware('permission:reports.view')->group(function (): void {
            Route::get('reports/portfolio', [LoanReportsController::class, 'portfolio'])->name('reports.portfolio');
            Route::get('reports/disbursements', [LoanReportsController::class, 'disbursements'])->name('reports.disbursements');
            Route::get('reports/applications', [LoanReportsController::class, 'applications'])->name('reports.applications');
            Route::get('reports/arrears', [LoanReportsController::class, 'arrears'])->name('reports.arrears');
            Route::get('reports/repayments', [LoanReportsController::class, 'repayments'])->name('reports.repayments');
            Route::get('reports/collections-performance', [LoanReportsController::class, 'collectionsPerformance'])->name('reports.collections-performance');
            Route::get('reports/par', [LoanReportsController::class, 'par'])->name('reports.par');
            Route::get('reports/partner-performance', fn () => redirect()->route('admin.reports.vendor-performance'))->name('reports.partner-performance');
            Route::view('reports/vendor-performance', 'admin.reports.vendor-performance')->name('reports.vendor-performance');
            Route::get('reports/customers', [FinanceReportsController::class, 'customers'])->name('reports.customers');
            Route::get('reports/affiliate-marketing-attribution', [AffiliateReportsController::class, 'marketingAttribution'])->name('reports.affiliate-marketing-attribution');
            Route::get('reports/affiliate-capital-attribution', [AffiliateReportsController::class, 'capitalAttribution'])->name('reports.affiliate-capital-attribution');
            Route::get('reports/affiliate-fraud', [AffiliateReportsController::class, 'fraudOverview'])->name('reports.affiliate-fraud');
        });

        Route::middleware('permission:finance.reports')->group(function (): void {
            Route::get('reports/finance-summary', [LoanReportsController::class, 'financeSummary'])->name('reports.finance-summary');
        });

        // Support
        $registerResource('support-tickets', 'support_ticket', SupportTicketController::class);
        Route::get('support-chats', [SupportChatController::class, 'index'])->name('support-chats.index');
        Route::get('support-chats/{supportConversation}', [SupportChatController::class, 'show'])->name('support-chats.show');
        Route::post('support-chats/{supportConversation}/assign', [SupportChatController::class, 'assign'])->name('support-chats.assign');
        Route::post('support-chats/{supportConversation}/reply', [SupportChatController::class, 'reply'])->name('support-chats.reply');
        $registerResource('complaints',      'complaint',      ComplaintController::class);

        // Loan modification request queues
        Route::get('restructure-requests', [RestructureRequestController::class, 'index'])->name('restructure-requests.index');
        Route::get('restructure-requests/{restructureRequest}', [RestructureRequestController::class, 'show'])->name('restructure-requests.show');
        Route::post('restructure-requests/{restructureRequest}/approve', [RestructureRequestController::class, 'approve'])->name('restructure-requests.approve');
        Route::post('restructure-requests/{restructureRequest}/reject', [RestructureRequestController::class, 'reject'])->name('restructure-requests.reject');
        Route::get('top-up-requests', [LoanTopUpRequestController::class, 'index'])->name('top-up-requests.index');
        Route::get('top-up-requests/{topUpRequest}', [LoanTopUpRequestController::class, 'show'])->name('top-up-requests.show');
        Route::post('top-up-requests/{topUpRequest}/approve', [LoanTopUpRequestController::class, 'approve'])->name('top-up-requests.approve');
        Route::post('top-up-requests/{topUpRequest}/reject', [LoanTopUpRequestController::class, 'reject'])->name('top-up-requests.reject');
        Route::post('top-up-requests/{topUpRequest}/disburse', [LoanTopUpRequestController::class, 'disburse'])->name('top-up-requests.disburse');

        // Payment verification queue + ledgers
        Route::middleware('permission:finance.operations')->group(function (): void {
            Route::get('payments/ledger', [\App\Http\Controllers\Admin\LedgerController::class, 'payments'])->name('payments.ledger');
            Route::get('payouts/ledger', [\App\Http\Controllers\Admin\LedgerController::class, 'payouts'])->name('payouts.ledger');
            Route::get('payments', [PaymentVerificationController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [PaymentVerificationController::class, 'show'])->name('payments.show');
            Route::post('payments/{payment}/verify', [PaymentVerificationController::class, 'verify'])->name('payments.verify');
            Route::post('payments/{payment}/reject', [PaymentVerificationController::class, 'reject'])->name('payments.reject');
            Route::post('payments/{payment}/clarify', [PaymentVerificationController::class, 'requestClarification'])->name('payments.clarify');
            Route::get('payments/{payment}/proof', [PaymentVerificationController::class, 'proof'])->name('payments.proof');
        });

        // Membership bank payments
        Route::get('membership-payments', [MembershipPaymentController::class, 'index'])->name('membership-payments.index');
        Route::post('membership-payments/{membershipHistory}/approve', [MembershipPaymentController::class, 'approve'])->name('membership-payments.approve');
        Route::post('membership-payments/{membershipHistory}/reject', [MembershipPaymentController::class, 'reject'])->name('membership-payments.reject');

        // System
        $registerResource('branches', 'branch', BranchController::class);
        $registerResource('users',    'user',   UserController::class);
        Route::post('users/{user}/lock', [UserController::class, 'lock'])->name('users.lock');
        Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        // ========== FINANCE (extended) ==========
        Route::middleware('permission:finance.accounts')->group(function () use ($registerResource): void {
            $registerResource('chart-of-accounts',      'chart_of_account',      ChartOfAccountController::class);
            $registerResource('bank-accounts',          'bank_account',          BankAccountController::class);
            $registerResource('mobile-money-accounts',  'mobile_money_account',  MobileMoneyAccountController::class);
        });

        Route::middleware('permission:finance.methods')->group(function () use ($registerResource): void {
            $registerResource('disbursement-methods',   'disbursement_method',   DisbursementMethodController::class);
            $registerResource('repayment-methods',      'repayment_method',      RepaymentMethodController::class);
            $registerResource('charges-fees',           'charges_fee',           ChargesFeeController::class);
            $registerResource('write-off-rules',        'write_off_rule',        WriteOffRuleController::class);
        });

        // Finance reports
        Route::middleware('permission:finance.reports')->group(function (): void {
            Route::get('reports/trial-balance',    [FinanceReportsController::class, 'trialBalance'])   ->name('reports.trial-balance');
            Route::get('reports/income-statement', [FinanceReportsController::class, 'incomeStatement'])->name('reports.income-statement');
            Route::get('reports/balance-sheet',    [FinanceReportsController::class, 'balanceSheet'])   ->name('reports.balance-sheet');
            Route::get('reports/cash-flow',        [FinanceReportsController::class, 'cashFlow'])       ->name('reports.cash-flow');
            Route::get('reports/npl',              [FinanceReportsController::class, 'npl'])            ->name('reports.npl');
            Route::get('reports/financial-overview',[FinanceReportsController::class, 'financialOverview'])->name('reports.financial-overview');
        });

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
        Route::get('compliance/crb-audit', [ComplianceController::class, 'crbAudit'])->name('compliance.crb-audit');
        Route::get('compliance/crb-audit/export', [ComplianceController::class, 'crbAuditExport'])->name('compliance.crb-audit.export');
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
        Route::get('settings/account-security', [\App\Http\Controllers\Admin\AccountSecurityController::class, 'show'])
            ->name('settings.account-security');
        Route::post('settings/account-security/recovery-codes', [\App\Http\Controllers\Admin\AccountSecurityController::class, 'regenerateRecoveryCodes'])
            ->name('settings.account-security.regenerate');
        Route::get('settings/auth-portal',      [SettingsController::class, 'authPortal'])   ->name('settings.auth-portal');
        Route::put('settings/auth-portal',      [SettingsController::class, 'saveAuthPortal'])->name('settings.auth-portal.save');
        Route::get('settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
        Route::get('settings/integrations/partners/create', [SettingsController::class, 'createIntegrationPartner'])->name('settings.integrations.partners.create');
        Route::post('settings/integrations/partners', [SettingsController::class, 'storeIntegrationPartner'])->name('settings.integrations.partners.store');
        Route::put('settings/integrations/primary', [SettingsController::class, 'saveIntegrationsPrimary'])->name('settings.integrations.primary');
        Route::put('settings/integrations/channels', [SettingsController::class, 'saveIntegrationChannels'])->name('settings.integrations.channels');
        Route::post('settings/integrations/health', [SettingsController::class, 'checkIntegrationHealth'])->name('settings.integrations.health');
        Route::post('settings/integrations/live-test', [SettingsController::class, 'runIntegrationLiveTest'])->name('settings.integrations.live-test');
        Route::get('settings/integrations/live-test/payments/{payment}', [SettingsController::class, 'previewIntegrationPaymentGate'])->name('settings.integrations.live-test.payment');
        Route::put('settings/integrations/{partner}/billing', [SettingsController::class, 'saveIntegrationBilling'])->name('settings.integrations.billing');
        Route::get('settings/integrations/{partner}', [SettingsController::class, 'showIntegrationPartner'])->name('settings.integrations.partner');
        Route::get('settings/gateways',         [SettingsController::class, 'gateways'])      ->name('settings.gateways');
        Route::put('settings/gateways',         [SettingsController::class, 'saveGateways'])  ->name('settings.gateways.save');
        Route::get('settings/gateways/health',  [SettingsController::class, 'smsHealth'])     ->name('settings.gateways.health');
        Route::get('settings/payin',            [SettingsController::class, 'payin'])         ->name('settings.payin');
        Route::put('settings/payin',            [SettingsController::class, 'savePayin'])     ->name('settings.payin.save');
        Route::get('settings/payin/health',     [SettingsController::class, 'payinHealth'])   ->name('settings.payin.health');
        Route::get('settings/messaging',        [SettingsController::class, 'messaging'])     ->name('settings.messaging');
        Route::put('settings/messaging',        [SettingsController::class, 'saveMessaging'])->name('settings.messaging.save');
        Route::get('settings/kyc',              [SettingsController::class, 'kyc'])           ->name('settings.kyc');
        Route::put('settings/kyc',              [SettingsController::class, 'saveKyc'])       ->name('settings.kyc.save');
        Route::get('settings/crb',              [SettingsController::class, 'crb'])           ->name('settings.crb');
        Route::put('settings/crb',              [SettingsController::class, 'saveCrb'])         ->name('settings.crb.save');
        Route::post('settings/crb/test',        [SettingsController::class, 'testCrbConnection'])->name('settings.crb.test');
        Route::get('settings/identity-verification', [SettingsController::class, 'identityVerification'])->name('settings.identity');
        Route::put('settings/identity-verification', [SettingsController::class, 'saveIdentityVerification'])->name('settings.identity.save');
        Route::get('settings/loan-rules',       [SettingsController::class, 'loanRules'])     ->name('settings.loan-rules');
        Route::put('settings/loan-rules',       [SettingsController::class, 'saveLoanRules']) ->name('settings.loan-rules.save');
        Route::get('settings/offer',            [SettingsController::class, 'offer'])          ->name('settings.offer');
        Route::put('settings/offer',            [SettingsController::class, 'saveOffer'])      ->name('settings.offer.save');
        Route::get('settings/underwriting',    [SettingsController::class, 'underwriting'])  ->name('settings.underwriting');
        Route::put('settings/underwriting',    [SettingsController::class, 'saveUnderwriting'])->name('settings.underwriting.save');
        Route::get('settings/legal',           [SettingsController::class, 'legal'])         ->name('settings.legal');
        Route::put('settings/legal',           [SettingsController::class, 'saveLegal'])     ->name('settings.legal.save');
        Route::get('settings/signatories', [SignatoryController::class, 'index'])->name('settings.signatories.index');
        Route::get('settings/signatories/create', [SignatoryController::class, 'create'])->name('settings.signatories.create');
        Route::post('settings/signatories', [SignatoryController::class, 'store'])->name('settings.signatories.store');
        Route::get('settings/signatories/{signatory}/edit', [SignatoryController::class, 'edit'])->name('settings.signatories.edit');
        Route::put('settings/signatories/{signatory}', [SignatoryController::class, 'update'])->name('settings.signatories.update');
        Route::delete('settings/signatories/{signatory}', [SignatoryController::class, 'destroy'])->name('settings.signatories.destroy');
        Route::get('settings/locations', [LocationMasterController::class, 'index'])->name('settings.locations.index');
        Route::get('settings/locations/create', [LocationMasterController::class, 'create'])->name('settings.locations.create');
        Route::post('settings/locations', [LocationMasterController::class, 'store'])->name('settings.locations.store');
        Route::get('settings/locations/{location}/edit', [LocationMasterController::class, 'edit'])->name('settings.locations.edit');
        Route::put('settings/locations/{location}', [LocationMasterController::class, 'update'])->name('settings.locations.update');
        Route::delete('settings/locations/{location}', [LocationMasterController::class, 'destroy'])->name('settings.locations.destroy');
        Route::get('settings/credit-policy',    [SettingsController::class, 'creditPolicy'])  ->name('settings.credit-policy');
        Route::put('settings/credit-policy',    [SettingsController::class, 'saveCreditPolicy'])->name('settings.credit-policy.save');
        Route::get('settings/loan-products',    [SettingsController::class, 'loanProducts']) ->name('settings.loan-products');
        Route::get('settings/membership',       [SettingsController::class, 'membership'])    ->name('settings.membership');
        Route::put('settings/membership',       [SettingsController::class, 'saveMembership'])->name('settings.membership.save');
        Route::get('settings/referrals',        [SettingsController::class, 'referrals'])     ->name('settings.referrals');
        Route::put('settings/referrals',        [SettingsController::class, 'saveReferrals']) ->name('settings.referrals.save');
        Route::get('settings/engagement', [EngagementSettingsController::class, 'index'])->name('settings.engagement');
        Route::get('settings/engagement/referral-levels', [EngagementSettingsController::class, 'referralLevels'])->name('settings.engagement.referral-levels');
        Route::put('settings/engagement/referral-levels', [EngagementSettingsController::class, 'saveReferralLevels'])->name('settings.engagement.referral-levels.save');
        Route::get('settings/engagement/trust-score', [EngagementSettingsController::class, 'trustScore'])->name('settings.engagement.trust-score');
        Route::put('settings/engagement/trust-score', [EngagementSettingsController::class, 'saveTrustScore'])->name('settings.engagement.trust-score.save');
        Route::get('settings/engagement/milestones', [EngagementSettingsController::class, 'milestones'])->name('settings.engagement.milestones');
        Route::put('settings/engagement/milestones', [EngagementSettingsController::class, 'saveMilestones'])->name('settings.engagement.milestones.save');
        Route::get('settings/engagement/repayment-streak', [EngagementSettingsController::class, 'repaymentStreak'])->name('settings.engagement.repayment-streak');
        Route::put('settings/engagement/repayment-streak', [EngagementSettingsController::class, 'saveRepaymentStreak'])->name('settings.engagement.repayment-streak.save');
        Route::get('settings/engagement/profile-strength', [EngagementSettingsController::class, 'profileStrength'])->name('settings.engagement.profile-strength');
        Route::put('settings/engagement/profile-strength', [EngagementSettingsController::class, 'saveProfileStrength'])->name('settings.engagement.profile-strength.save');
        Route::get('settings/engagement/loyalty-points', [EngagementSettingsController::class, 'loyaltyPoints'])->name('settings.engagement.loyalty-points');
        Route::put('settings/engagement/loyalty-points', [EngagementSettingsController::class, 'saveLoyaltyPoints'])->name('settings.engagement.loyalty-points.save');
        Route::get('settings/engagement/underwriting', [EngagementSettingsController::class, 'underwriting'])->name('settings.engagement.underwriting');
        Route::put('settings/engagement/underwriting', [EngagementSettingsController::class, 'saveUnderwriting'])->name('settings.engagement.underwriting.save');
        Route::get('settings/engagement/notifications', [EngagementSettingsController::class, 'notifications'])->name('settings.engagement.notifications');
        Route::put('settings/engagement/notifications', [EngagementSettingsController::class, 'saveNotifications'])->name('settings.engagement.notifications.save');
        Route::get('settings/affiliates',      [SettingsController::class, 'affiliates'])    ->name('settings.affiliates');
        Route::put('settings/affiliates',       [SettingsController::class, 'saveAffiliates'])->name('settings.affiliates.save');
        Route::get('settings/partners',         [SettingsController::class, 'partners'])      ->name('settings.partners');
        Route::put('settings/partners',         [SettingsController::class, 'savePartners'])  ->name('settings.partners.save');
        Route::get('settings/chatbot',          [SettingsController::class, 'chatbot'])       ->name('settings.chatbot');
        Route::put('settings/chatbot',          [SettingsController::class, 'saveChatbot'])   ->name('settings.chatbot.save');
        Route::get('settings/countries',        [SettingsController::class, 'countries'])     ->name('settings.countries');
        Route::put('settings/countries/{country}', [SettingsController::class, 'saveCountry'])->name('settings.countries.save');
        Route::get('settings/aml',              [SettingsController::class, 'amlSettings'])   ->name('settings.aml');
        Route::put('settings/aml',              [SettingsController::class, 'saveAmlSettings'])->name('settings.aml.save');
        Route::get('settings/finance',          [SettingsController::class, 'finance'])       ->name('settings.finance');
        Route::put('settings/finance',          [SettingsController::class, 'saveFinance'])   ->name('settings.finance.save');
        Route::get('settings/asset-lending',    [SettingsController::class, 'assetLending'])  ->name('settings.asset-lending');
        Route::put('settings/asset-lending',    [SettingsController::class, 'saveAssetLending'])->name('settings.asset-lending.save');
        Route::get('settings/recovery',         [SettingsController::class, 'recovery'])      ->name('settings.recovery');
        Route::put('settings/recovery',         [SettingsController::class, 'saveRecovery'])  ->name('settings.recovery.save');
        Route::get('settings/payment-accounts',   [PaymentAccountSettingsController::class, 'index'])         ->name('settings.payment-accounts');
        Route::put('settings/payment-accounts',   [PaymentAccountSettingsController::class, 'saveDefaults'])  ->name('settings.payment-accounts.save');
        Route::put('settings/payment-accounts/default-collection', [PaymentAccountSettingsController::class, 'saveDefaultCollection'])->name('settings.payment-accounts.default-collection');
        Route::put('settings/payment-accounts/default-disbursement', [PaymentAccountSettingsController::class, 'saveDefaultDisbursement'])->name('settings.payment-accounts.default-disbursement');
        Route::post('settings/payment-accounts/overrides', [PaymentAccountSettingsController::class, 'saveOverride'])->name('settings.payment-accounts.overrides.save');
        Route::delete('settings/payment-accounts/overrides/{override}', [PaymentAccountSettingsController::class, 'deleteOverride'])->name('settings.payment-accounts.overrides.destroy');

        $registerResource('departments',           'department',            DepartmentController::class);
        $registerResource('roles',                 'role',                  RoleController::class);
        $registerResource('approval-limits',       'approval_limit',        ApprovalLimitController::class);
        $registerResource('document-templates',    'document_template',     DocumentTemplateController::class);
        $registerResource('notification-templates','notification_template', NotificationTemplateController::class);
    });
});
