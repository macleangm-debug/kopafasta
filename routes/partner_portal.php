<?php

use App\Http\Controllers\Site\CardVerificationController;
use App\Http\Controllers\Site\PartnerAccountController;
use App\Http\Controllers\Site\PartnerMembershipPaymentController;
use App\Http\Controllers\Site\VendorController;
use Illuminate\Support\Facades\Route;

/** @param 'vendor'|'partner' $prefix */
return function (string $prefix, string $namePrefix, bool $registerDashboard = true): void {
    Route::prefix($prefix)->name($namePrefix)->group(function () use ($registerDashboard): void {
        if ($registerDashboard) {
            Route::get('/', [VendorController::class, 'dashboard'])->name('dashboard');
        }
        Route::get('/tasks', [VendorController::class, 'tasks'])->name('tasks');
        Route::get('/tasks/active', [VendorController::class, 'activeJobs'])->name('tasks.active');
        Route::get('/tasks/completed', [VendorController::class, 'completedJobs'])->name('tasks.completed');
        Route::get('/recovery-cases', [VendorController::class, 'recoveryCases'])->name('recovery-cases');
        Route::get('/recovery-wallet', [VendorController::class, 'recoveryWallet'])->name('recovery-wallet');
        Route::post('/recovery-wallet/{payment}/dispute', [VendorController::class, 'disputeRecoveryPayment'])->name('recovery-wallet.dispute');
        Route::post('/recovery-wallet/payout-request', [VendorController::class, 'requestRecoveryPayout'])->name('recovery-wallet.payout-request');
        Route::get('/recovery-cases/{recoveryAssignment}', [VendorController::class, 'recoveryCase'])->name('recovery-case');
        Route::get('/recovery-cases/{recoveryAssignment}/letters/{agreement}', [VendorController::class, 'downloadRecoveryLetter'])->name('recovery-case.letter');
        Route::post('/recovery-cases/{recoveryAssignment}/start', [VendorController::class, 'startRecoveryCase'])->name('recovery-case.start');
        Route::post('/recovery-cases/{recoveryAssignment}/actions', [VendorController::class, 'recoveryCaseAction'])->name('recovery-case.action');
        Route::post('/recovery-cases/{recoveryAssignment}/remind', [VendorController::class, 'remindRecoveryCase'])->name('recovery-case.remind');
        Route::get('/tasks/{task}', [VendorController::class, 'task'])->name('task');
        Route::post('/tasks/{task}/accept', [VendorController::class, 'acceptTask'])->name('task.accept');
        Route::post('/tasks/{task}/start', [VendorController::class, 'startTask'])->name('task.start');
        Route::post('/tasks/{task}/decline', [VendorController::class, 'declineTask'])->name('task.decline');
        Route::post('/tasks/{task}/inspect/photo', [VendorController::class, 'inspectValuationPhoto'])->name('task.inspect.photo');
        Route::post('/tasks/{task}/inspect/checks', [VendorController::class, 'inspectValuationChecks'])->name('task.inspect.checks');
        Route::post('/tasks/{task}/complete', [VendorController::class, 'completeTask'])->name('task.complete');
        Route::post('/tasks/{task}/proof', [VendorController::class, 'uploadProof'])->name('task.proof');
        Route::get('/documents', [VendorController::class, 'documents'])->name('documents');
        Route::post('/documents', [VendorController::class, 'uploadDocument'])->name('documents.store');
        Route::get('/payments', [VendorController::class, 'payments'])->name('payments');
        Route::post('/payments/payout-request', [VendorController::class, 'requestPayout'])->name('payments.payout-request');
        Route::get('/payments/{payment}/invoice', [VendorController::class, 'invoice'])->name('invoice');
        Route::get('/calendar', [VendorController::class, 'calendar'])->name('calendar');
        Route::get('/notifications', [VendorController::class, 'notifications'])->name('notifications');
        Route::get('/profile/{section?}', [VendorController::class, 'profile'])->name('profile')->where('section', 'hub|personal|company|face|residence|activity|payment');
        Route::put('/profile/{section}', [VendorController::class, 'updateProfile'])->name('profile.update')->where('section', 'personal|company|face|residence|activity|payment');
        Route::get('/membership/pay', [VendorController::class, 'membershipPayForm'])->name('membership.pay');
        Route::post('/membership/pay', [VendorController::class, 'membershipPay'])->name('membership.pay.post');
        Route::post('/membership/checkout/{payment}/pay', [PartnerMembershipPaymentController::class, 'pay'])->name('membership.checkout.pay');
        Route::get('/membership/checkout/{payment}/status', [PartnerMembershipPaymentController::class, 'status'])->name('membership.checkout.status');
        Route::post('/membership/checkout/{payment}/retry', [PartnerMembershipPaymentController::class, 'retry'])->name('membership.checkout.retry');
        Route::post('/membership/checkout/{payment}/gate', [PartnerMembershipPaymentController::class, 'returnToGate'])->name('membership.checkout.gate');
        Route::get('/settings', [VendorController::class, 'settings'])->name('settings');
        Route::put('/settings/pin', [PartnerAccountController::class, 'updatePin'])->name('settings.pin');
        Route::put('/settings/preferences', [PartnerAccountController::class, 'updatePreferences'])->name('settings.preferences');
        Route::get('/support', [VendorController::class, 'support'])->name('support');
        Route::get('/terms', [VendorController::class, 'terms'])->name('terms');
        Route::post('/terms', [VendorController::class, 'acceptTerms'])->name('terms.accept');
        Route::get('/verify', [CardVerificationController::class, 'partnerIndex'])->name('verify');
        Route::post('/verify', [CardVerificationController::class, 'partnerLookup'])->name('verify.lookup');
        Route::get('/verify/member/{memberNo}', [CardVerificationController::class, 'partnerShowMember'])->name('verify.member');
        Route::get('/verify/p/{partnerNo}', [CardVerificationController::class, 'partnerShowPartner'])->name('verify.partner');
    });
};
