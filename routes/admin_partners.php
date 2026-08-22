<?php

use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

return function (): void {
    Route::view('partners/all', 'admin.partners.all')->name('partners.all');
    Route::get('partners/applications', fn () => redirect()->route('admin.partner-applications.index'))
        ->name('partners.applications');
    Route::view('partners/gps-installers', 'admin.partners.gps-installers')->name('partners.gps-installers');
    Route::view('partners/insurance-providers', 'admin.partners.insurance-providers')->name('partners.insurance-providers');
    Route::view('partners/valuers', 'admin.partners.valuers')->name('partners.valuers');
    Route::get('partners/origination-auto-assign', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'autoAssign'])->name('partners.origination-auto-assign');
    Route::post('partners/origination-auto-assign', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'saveAutoAssign'])->name('partners.origination-auto-assign.save');
    Route::get('partners/efficiency', [\App\Http\Controllers\Admin\PartnerEfficiencyController::class, 'index'])->name('partners.efficiency');
    Route::get('partners/coverage-requests/{loan_application}', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'coverageRequest'])->name('partners.coverage-request');
    Route::post('partners/coverage-requests/{loan_application}/partners/{vendor}/add-region', [\App\Http\Controllers\Admin\OriginationPartnerController::class, 'addCoverageRegion'])->name('partners.coverage-request.add-region');
    Route::view('partners/suppliers', 'admin.partners.suppliers')->name('partners.suppliers');
    Route::view('partners/affiliates', 'admin.partners.affiliates')->name('partners.affiliates');
    Route::view('partners/tasks', 'admin.partners.tasks')->name('partners.tasks');

    Route::get('partners/create', [VendorController::class, 'create'])->name('partners.create');
    Route::post('partners', [VendorController::class, 'store'])->name('partners.store');
    Route::get('partners/{vendor}', [VendorController::class, 'show'])->name('partners.show');
    Route::get('partners/{vendor}/edit', [VendorController::class, 'edit'])->name('partners.edit');
    Route::put('partners/{vendor}', [VendorController::class, 'update'])->name('partners.update');
    Route::delete('partners/{vendor}', [VendorController::class, 'destroy'])->name('partners.destroy');
    Route::post('partners/{vendor}/halt-open-work', [VendorController::class, 'haltOpenWork'])->name('partners.halt-open-work');
    Route::post('partners/{vendor}/reset-pin', [VendorController::class, 'resetPin'])->name('partners.reset-pin');
    Route::post('partners/{vendor}/reissue-activation', [VendorController::class, 'reissueActivation'])->name('partners.reissue-activation');
    Route::post('partners/{vendor}/deactivate', [VendorController::class, 'deactivate'])->name('partners.deactivate');
    Route::post('partners/{vendor}/affiliate-kyc/approve', [VendorController::class, 'approveAffiliateKyc'])->name('partners.affiliate-kyc.approve');
    Route::post('partners/{vendor}/affiliate-kyc/reject', [VendorController::class, 'rejectAffiliateKyc'])->name('partners.affiliate-kyc.reject');
    Route::post('partners/{vendor}/affiliate-lifecycle', [VendorController::class, 'updateAffiliateLifecycle'])->name('partners.affiliate-lifecycle.update');
    Route::post('partners/{vendor}/affiliate-fraud/scan', [VendorController::class, 'scanAffiliateFraud'])->name('partners.affiliate-fraud.scan');
    Route::post('partners/{vendor}/affiliate-risk-flag', [VendorController::class, 'updateAffiliateRiskFlag'])->name('partners.affiliate-risk-flag.update');
    Route::post('partners/{vendor}/membership/approve', [VendorController::class, 'approveMembershipPayment'])->name('partners.membership.approve');
    Route::post('partners/{vendor}/membership/reject', [VendorController::class, 'rejectMembershipPayment'])->name('partners.membership.reject');
};
