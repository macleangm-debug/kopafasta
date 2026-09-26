<?php

use App\Http\Controllers\Site\PartnerFaceVerificationController;
use Illuminate\Support\Facades\Route;

if (! function_exists('kopafasta_register_partner_face_verification_routes')) {
    function kopafasta_register_partner_face_verification_routes(): void
    {
        Route::post('profile/face-verification/submit', [PartnerFaceVerificationController::class, 'submit'])
            ->name('face-verification.submit');
        Route::post('profile/face-verification/{angle}', [PartnerFaceVerificationController::class, 'store'])
            ->name('face-verification.store')
            ->where('angle', 'front|left|right|holding_nida');
        Route::delete('profile/face-verification/{angle}', [PartnerFaceVerificationController::class, 'destroy'])
            ->name('face-verification.destroy')
            ->where('angle', 'front|left|right|holding_nida');
    }
}
