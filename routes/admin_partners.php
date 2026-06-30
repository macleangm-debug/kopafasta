<?php

use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

return function (): void {
    Route::view('partners/all', 'admin.partners.all')->name('partners.all');
    Route::view('partners/applications', 'admin.partners.applications')->name('partners.applications');
    Route::view('partners/gps-installers', 'admin.partners.gps-installers')->name('partners.gps-installers');
    Route::view('partners/insurance-providers', 'admin.partners.insurance-providers')->name('partners.insurance-providers');
    Route::view('partners/valuers', 'admin.partners.valuers')->name('partners.valuers');
    Route::view('partners/suppliers', 'admin.partners.suppliers')->name('partners.suppliers');
    Route::view('partners/affiliates', 'admin.partners.affiliates')->name('partners.affiliates');
    Route::view('partners/tasks', 'admin.partners.tasks')->name('partners.tasks');

    Route::get('partners/create', [VendorController::class, 'create'])->name('partners.create');
    Route::post('partners', [VendorController::class, 'store'])->name('partners.store');
    Route::get('partners/{vendor}', [VendorController::class, 'show'])->name('partners.show');
    Route::get('partners/{vendor}/edit', [VendorController::class, 'edit'])->name('partners.edit');
    Route::put('partners/{vendor}', [VendorController::class, 'update'])->name('partners.update');
    Route::delete('partners/{vendor}', [VendorController::class, 'destroy'])->name('partners.destroy');
    Route::post('partners/{vendor}/affiliate-kyc/approve', [VendorController::class, 'approveAffiliateKyc'])->name('partners.affiliate-kyc.approve');
    Route::post('partners/{vendor}/affiliate-kyc/reject', [VendorController::class, 'rejectAffiliateKyc'])->name('partners.affiliate-kyc.reject');
    Route::post('partners/{vendor}/affiliate-lifecycle', [VendorController::class, 'updateAffiliateLifecycle'])->name('partners.affiliate-lifecycle.update');
    Route::post('partners/{vendor}/affiliate-fraud/scan', [VendorController::class, 'scanAffiliateFraud'])->name('partners.affiliate-fraud.scan');
    Route::post('partners/{vendor}/affiliate-risk-flag', [VendorController::class, 'updateAffiliateRiskFlag'])->name('partners.affiliate-risk-flag.update');
};
