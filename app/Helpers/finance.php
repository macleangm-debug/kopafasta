<?php

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;

if (! function_exists('repayment_approval_required')) {
    function repayment_approval_required(): bool
    {
        return (bool) (Setting::get('finance.repayment_approval_required') ?? false);
    }
}

if (! function_exists('application_needs_funding_choice')) {
    function application_needs_funding_choice(?LoanProduct $product): bool
    {
        return $product && loan_product_uses_capital_partner($product);
    }
}

if (! function_exists('application_uses_internal_funding')) {
    function application_uses_internal_funding(LoanApplication $application): bool
    {
        return ($application->funding_source ?? '') === 'internal';
    }
}

if (! function_exists('collections_gateway_only')) {
    function collections_gateway_only(): bool
    {
        return (bool) (Setting::get('finance.collections_gateway_only') ?? false);
    }
}

if (! function_exists('admin_repayment_recording_allowed')) {
    function admin_repayment_recording_allowed(): bool
    {
        return ! collections_gateway_only();
    }
}
