<?php

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
        Route::post('/recovery-cases/{recoveryAssignment}/start', [VendorController::class, 'startRecoveryCase'])->name('recovery-case.start');
        Route::post('/recovery-cases/{recoveryAssignment}/actions', [VendorController::class, 'recoveryCaseAction'])->name('recovery-case.action');
        Route::get('/tasks/{task}', [VendorController::class, 'task'])->name('task');
        Route::post('/tasks/{task}/accept', [VendorController::class, 'acceptTask'])->name('task.accept');
        Route::post('/tasks/{task}/start', [VendorController::class, 'startTask'])->name('task.start');
        Route::post('/tasks/{task}/complete', [VendorController::class, 'completeTask'])->name('task.complete');
        Route::post('/tasks/{task}/proof', [VendorController::class, 'uploadProof'])->name('task.proof');
        Route::get('/documents', [VendorController::class, 'documents'])->name('documents');
        Route::post('/documents', [VendorController::class, 'uploadDocument'])->name('documents.store');
        Route::get('/payments', [VendorController::class, 'payments'])->name('payments');
        Route::get('/payments/{payment}/invoice', [VendorController::class, 'invoice'])->name('invoice');
        Route::get('/calendar', [VendorController::class, 'calendar'])->name('calendar');
        Route::get('/notifications', [VendorController::class, 'notifications'])->name('notifications');
        Route::get('/profile', [VendorController::class, 'profile'])->name('profile');
        Route::put('/profile', [VendorController::class, 'updateProfile'])->name('profile.update');
        Route::get('/settings', [VendorController::class, 'settings'])->name('settings');
        Route::put('/settings/pin', [\App\Http\Controllers\Site\PartnerAccountController::class, 'updatePin'])->name('settings.pin');
        Route::get('/support', [VendorController::class, 'support'])->name('support');
    });
};
