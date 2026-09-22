<?php

/**
 * Read-only export of three production applications for staging UAT.
 * Run from the production app root:
 *   php artisan tinker --execute="require '/tmp/kf-export-production-uat.php';"
 *
 * Writes /tmp/kf-prod-uat-export only. Does not update the database.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$numbers = ['APP-IL-LQU6', 'APP-IL-84SH', 'APP-IL-ZR93'];
$outDir = '/tmp/kf-prod-uat-export';
$skipColumn = static function (string $column): bool {
    return (bool) preg_match('/password|remember_token|two_factor|pin|secret|api_token/i', $column);
};

if (! is_dir($outDir) && ! mkdir($outDir, 0700, true) && ! is_dir($outDir)) {
    throw new RuntimeException('Cannot create export directory.');
}

$apps = DB::table('loan_applications')->whereIn('application_number', $numbers)->get();
if ($apps->count() !== 3) {
    throw new RuntimeException('Expected 3 applications, found '.$apps->count());
}

$appIds = $apps->pluck('id')->map(fn ($id) => (int) $id)->all();
$borrowerIds = $apps->pluck('customer_id')->map(fn ($id) => (int) $id)->all();

$links = Schema::hasTable('customer_guarantors')
    ? DB::table('customer_guarantors')->whereIn('loan_application_id', $appIds)->get()
    : collect();
$invites = Schema::hasTable('guarantor_invitations')
    ? DB::table('guarantor_invitations')->whereIn('loan_application_id', $appIds)->get()
    : collect();

$guarantorCustomerIds = $invites->pluck('guarantor_customer_id')->filter()->map(fn ($id) => (int) $id)->all();
$customerIds = array_values(array_unique(array_merge($borrowerIds, $guarantorCustomerIds)));
$guarantorIds = $links->pluck('guarantor_id')->filter()->map(fn ($id) => (int) $id)->all();

$customers = DB::table('customers')->whereIn('id', $customerIds)->get();
$userIds = $customers->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();

$rows = static function (string $table, callable $query) {
    if (! Schema::hasTable($table)) {
        return [];
    }

    return $query(DB::table($table))->get()->map(fn ($row) => (array) $row)->all();
};

$strip = static function (array $records) use ($skipColumn): array {
    return array_map(static function (array $row) use ($skipColumn): array {
        foreach (array_keys($row) as $column) {
            if ($skipColumn($column)) {
                unset($row[$column]);
            }
        }

        return $row;
    }, $records);
};

$bundle = [
    'exported_at' => date('c'),
    'application_numbers' => $numbers,
    'products' => $strip($rows('loan_products', fn ($q) => $q->whereIn('id', $apps->pluck('loan_product_id')->filter()->all() ?: [0]))),
    'branches' => $strip($rows('branches', fn ($q) => $q->whereIn('id', $apps->pluck('branch_id')->merge($customers->pluck('branch_id'))->filter()->unique()->all() ?: [0]))),
    'document_types' => $strip($rows('document_types', fn ($q) => $q->whereIn('id', DB::table('customer_documents')->whereIn('customer_id', $customerIds)->pluck('document_type_id')->filter()->all() ?: [0]))),
    'users' => $strip($rows('users', fn ($q) => $q->whereIn('id', $userIds ?: [0]))),
    'customers' => $strip($rows('customers', fn ($q) => $q->whereIn('id', $customerIds))),
    'customer_kyc' => $strip($rows('customer_kycs', fn ($q) => $q->whereIn('customer_id', $customerIds))),
    'guarantors' => $strip($rows('guarantors', fn ($q) => $q->whereIn('id', $guarantorIds ?: [0]))),
    'loan_applications' => $strip($apps->map(fn ($row) => (array) $row)->all()),
    'customer_guarantors' => $strip($links->map(fn ($row) => (array) $row)->all()),
    'guarantor_invitations' => $strip($invites->map(fn ($row) => (array) $row)->all()),
    'customer_documents' => $strip($rows('customer_documents', fn ($q) => $q->whereIn('customer_id', $customerIds))),
    'customer_assets' => $strip($rows('customer_assets', fn ($q) => $q->whereIn('customer_id', $customerIds))),
    'customer_disbursement_accounts' => $strip($rows('customer_disbursement_accounts', fn ($q) => $q->whereIn('customer_id', $customerIds))),
    'face_verifications' => $strip($rows('face_verifications', fn ($q) => $q->whereIn('customer_id', $customerIds))),
    'loan_application_assets' => $strip($rows('loan_application_assets', fn ($q) => $q->whereIn('loan_application_id', $appIds))),
    'application_stage_histories' => $strip($rows('application_stage_histories', fn ($q) => $q->whereIn('loan_application_id', $appIds))),
    'loan_application_document_requests' => $strip($rows('loan_application_document_requests', fn ($q) => $q->whereIn('loan_application_id', $appIds))),
    'loan_application_document_reviews' => $strip($rows('loan_application_document_reviews', fn ($q) => $q->whereIn('loan_application_id', $appIds))),
    'credit_histories' => $strip($rows('credit_histories', fn ($q) => $q->whereIn('customer_id', $customerIds))),
    'customer_payments' => [],
    'files' => [],
];

$feeRefs = $apps->pluck('application_fee_reference')->filter()->values()->all();
if (Schema::hasTable('customer_payments')) {
    $payments = DB::table('customer_payments')->whereIn('customer_id', $customerIds)->where(function ($query) use ($feeRefs, $appIds): void {
        $query->where('payment_type', 'application_fee');
        if ($feeRefs !== []) {
            $query->orWhereIn('reference', $feeRefs);
        }
        $query->orWhere(function ($inner) use ($appIds): void {
            $inner->where('source_type', 'like', '%LoanApplication')->whereIn('source_id', $appIds);
        });
    })->get();
    $bundle['customer_payments'] = $strip($payments->map(fn ($row) => (array) $row)->all());
}

$files = [];
$collectPath = static function (?string $path) use (&$files, $outDir): void {
    $path = trim((string) $path);
    if ($path === '' || str_contains($path, '..') || isset($files[$path])) {
        return;
    }
    foreach ([storage_path('app/public/'.$path), storage_path('app/'.$path)] as $absolute) {
        if (! is_file($absolute)) {
            continue;
        }
        $target = $outDir.'/files/'.$path;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        copy($absolute, $target);
        $files[$path] = ['bytes' => filesize($absolute)];
        break;
    }
};

foreach (['customer_documents', 'face_verifications', 'customer_kyc', 'customers'] as $table) {
    foreach ($bundle[$table] as $row) {
        foreach ($row as $value) {
            if (is_string($value) && preg_match('#^(customer|kyc|faces|documents|signatures|uploads)/#', $value)) {
                $collectPath($value);
            }
        }
    }
}

$bundle['files'] = $files;
file_put_contents($outDir.'/manifest.json', json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'applications' => $apps->count(),
    'customers' => count($bundle['customers']),
    'guarantors' => count($bundle['guarantors']),
    'documents' => count($bundle['customer_documents']),
    'files' => count($files),
    'payments' => count($bundle['customer_payments']),
    'dir' => $outDir,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
