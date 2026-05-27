<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\Setting;
use App\Models\ChargesFee;
use App\Models\Loan;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\LoanDisbursementService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Configure GL defaults
$cash = ChartOfAccount::firstOrCreate(['code' => '1000'], ['name' => 'Cash & Bank', 'type' => 'asset', 'is_active' => true]);
$recv = ChartOfAccount::firstOrCreate(['code' => '1200'], ['name' => 'Loan Receivable', 'type' => 'asset', 'is_active' => true]);
$feeIncome = ChartOfAccount::firstOrCreate(['code' => '4100'], ['name' => 'Fee Income', 'type' => 'income', 'is_active' => true]);
Setting::setMany([
    'finance.cash_gl_account_id' => $cash->id,
    'finance.loan_receivable_gl_account_id' => $recv->id,
    'finance.fee_income_gl_account_id' => $feeIncome->id,
]);
ChargesFee::where('charge_when','disbursement')->update(['gl_account_id' => $feeIncome->id]);

// Pending loan
$loan = Loan::where('status','pending')->first();
if (->make(Illuminate\Contracts\Console\Kernel::class);loan) {
    $customer = Customer::first();
    $product = LoanProduct::first();
    $loan = Loan::create([
        'customer_id' => $customer->id, 'loan_product_id' => $product->id,
        'loan_number' => 'LN-GL-'.rand(1000,9999), 'principal_amount' => 800000,
        'approved_amount' => 800000, 'outstanding_balance' => 800000,
        'interest_rate' => 0.18, 'tenure_months' => 12, 'status' => 'pending',
    ]);
}
echo 'Loan #' . $loan->id . ' approved=' . (float)$loan->approved_amount . PHP_EOL;

app(LoanDisbursementService::class)->applyFees($loan->fresh());

$entries = JournalEntry::where('source_type', Loan::class)->where('source_id', $loan->id)->get();
echo 'Journal entries posted: ' . $entries->count() . PHP_EOL;
foreach ($entries as $e) {
    echo '  Entry ' . $e->entry_number . ' (' . $e->entry_date . ') Dr=' . number_format($e->total_debit) . ' Cr=' . number_format($e->total_credit) . PHP_EOL;
    foreach ($e->lines as $l) {
        echo '    [' . $l->account->code . '] ' . $l->account->name . ' Dr=' . number_format($l->debit) . ' Cr=' . number_format($l->credit) . ' | ' . $l->description . PHP_EOL;
    }
}

// Smoke admin pages
$user = User::where('role','admin')->first() ?? User::first();
auth()->login($user);
$kernel = app(Kernel::class);
foreach (['/admin/journal-entries','/admin/settings/finance', '/admin/journal-entries/'.($entries->first()?->id ?? 1)] as $url) {
    $req = Request::create($url, 'GET');
    $req->setUserResolver(fn() => $user);
    try { 
        $resp = $kernel->handle($req); 
        echo str_pad($url,42) . ' => ' . $resp->getStatusCode() . PHP_EOL; 
        if ($resp->getStatusCode() >= 400) echo substr(strip_tags($resp->getContent()),0,300) . PHP_EOL; 
    } catch (\Throwable $e) { 
        echo $url . ' EX: ' . $e->getMessage() . PHP_EOL; 
    }
}
