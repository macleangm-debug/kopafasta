<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ArrearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerPortalController;
use App\Http\Controllers\Api\DisbursementController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\RepaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RestructureController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VendorTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::get('tokens', [AuthController::class, 'tokens']);
        Route::delete('tokens/{id}', [AuthController::class, 'revokeToken'])->whereNumber('id');
        Route::post('2fa/enable', [AuthController::class, 'enableTwoFactor']);
        Route::post('2fa/confirm', [AuthController::class, 'confirmTwoFactor']);
        Route::post('2fa/disable', [AuthController::class, 'disableTwoFactor']);
        Route::get('trusted-devices', [AuthController::class, 'trustedDevices']);
        Route::delete('trusted-devices/{id}', [AuthController::class, 'revokeTrustedDevice'])->whereNumber('id');
        Route::get('login-history', [AuthController::class, 'loginHistory']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('portal')->middleware('role:customer,officer,manager,admin')->group(function (): void {
        Route::get('dashboard', [CustomerPortalController::class, 'dashboard']);
        Route::post('kyc', [CustomerPortalController::class, 'submitKyc']);
        Route::get('applications/{loanApplication}', [CustomerPortalController::class, 'trackApplication']);
    });

    Route::middleware('role:officer,manager,admin')->group(function (): void {
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('loan-products', LoanProductController::class)->parameters(['loan-products' => 'loanProduct']);
        Route::apiResource('loan-applications', LoanApplicationController::class)->parameters(['loan-applications' => 'loanApplication']);
        Route::post('loan-applications/{loanApplication}/transition', [LoanApplicationController::class, 'transition']);
        Route::apiResource('vendors', VendorController::class);
        Route::apiResource('vendor-tasks', VendorTaskController::class)->parameters(['vendor-tasks' => 'vendorTask']);
        Route::post('vendor-tasks/{vendorTask}/complete', [VendorTaskController::class, 'complete']);
        Route::apiResource('loans', LoanController::class);
        Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse']);
        Route::apiResource('disbursements', DisbursementController::class);
        Route::post('disbursements/{disbursement}/release', [DisbursementController::class, 'release']);
        Route::apiResource('repayments', RepaymentController::class)->only(['index', 'store', 'show']);
        Route::get('loans/{loan}/schedule', [RepaymentController::class, 'schedule']);
        Route::apiResource('arrears', ArrearController::class)->only(['index', 'show', 'update'])->parameters(['arrears' => 'arrearCase']);
        Route::post('arrears/{arrearCase}/actions', [ArrearController::class, 'addAction']);
        Route::apiResource('restructures', RestructureController::class)->parameters(['restructures' => 'restructureRequest']);
        Route::post('restructures/{restructureRequest}/approve', [RestructureController::class, 'approve']);
    });

    Route::prefix('reports')->middleware('role:officer,manager,admin')->group(function (): void {
        Route::get('portfolio', [ReportController::class, 'portfolio']);
        Route::get('disbursement', [ReportController::class, 'disbursement']);
        Route::get('repayment', [ReportController::class, 'repayment']);
        Route::get('arrears', [ReportController::class, 'arrears']);
        Route::get('par', [ReportController::class, 'par']);
        Route::get('products', [ReportController::class, 'products']);
        Route::get('officers', [ReportController::class, 'officers']);
        Route::get('vendors', [ReportController::class, 'vendors']);
        Route::get('customer-risk', [ReportController::class, 'customerRisk']);
    });

    Route::prefix('system')->middleware('role:manager,admin')->group(function (): void {
        Route::get('users', [AdminController::class, 'users']);
        Route::post('users/{user}/assign-role', [AdminController::class, 'assignRole']);
        Route::post('users/{user}/lock', [AdminController::class, 'lockUser'])->middleware('role:admin');
        Route::post('users/{user}/unlock', [AdminController::class, 'unlockUser'])->middleware('role:admin');
        Route::get('settings', [AdminController::class, 'settings']);
        Route::post('settings', [AdminController::class, 'upsertSetting']);
        Route::get('audit-logs', [AdminController::class, 'auditLogs']);
        Route::get('security/anomalies', [AdminController::class, 'securityAnomalies'])->middleware('role:admin');
        Route::get('security/ip-rules', [AdminController::class, 'ipRules'])->middleware('role:admin');
        Route::post('security/ip-rules', [AdminController::class, 'createIpRule'])->middleware('role:admin');
        Route::delete('security/ip-rules/{ipRule}', [AdminController::class, 'deleteIpRule'])->middleware('role:admin')->whereNumber('ipRule');
        Route::delete('security/blocks/{ip}', [AdminController::class, 'unblockIp'])->middleware('role:admin')->where('ip', '[0-9a-fA-F:.]+');
    });
});
