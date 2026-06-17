<?php

use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

return function (): void {
    Route::view('partners/all', 'admin.vendors.index')->name('partners.all');
    Route::view('partners/applications', 'admin.vendors.applications')->name('partners.applications');
    Route::view('partners/gps-installers', 'admin.vendors.gps-installers')->name('partners.gps-installers');
    Route::view('partners/insurance-providers', 'admin.vendors.insurance-providers')->name('partners.insurance-providers');
    Route::view('partners/valuers', 'admin.vendors.valuers')->name('partners.valuers');
    Route::view('partners/suppliers', 'admin.vendors.suppliers')->name('partners.suppliers');
    Route::view('partners/affiliates', 'admin.vendors.affiliates')->name('partners.affiliates');
    Route::view('partners/tasks', 'admin.vendors.tasks')->name('partners.tasks');

    Route::get('partners/create', [VendorController::class, 'create'])->name('partners.create');
    Route::post('partners', [VendorController::class, 'store'])->name('partners.store');
    Route::get('partners/{vendor}', [VendorController::class, 'show'])->name('partners.show');
    Route::get('partners/{vendor}/edit', [VendorController::class, 'edit'])->name('partners.edit');
    Route::put('partners/{vendor}', [VendorController::class, 'update'])->name('partners.update');
    Route::delete('partners/{vendor}', [VendorController::class, 'destroy'])->name('partners.destroy');
    Route::post('partners/{vendor}/affiliate-kyc/approve', [VendorController::class, 'approveAffiliateKyc'])->name('partners.affiliate-kyc.approve');
    Route::post('partners/{vendor}/affiliate-kyc/reject', [VendorController::class, 'rejectAffiliateKyc'])->name('partners.affiliate-kyc.reject');
};
