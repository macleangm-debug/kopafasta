<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\CrbService;

$crb = app(CrbService::class);

echo 'CRB mode: '.($crb->usesStub() ? 'STUB (sandbox samples)' : 'LIVE D&B')."\n\n";

$samples = config('crb_samples.scenarios', []);

foreach ($samples as $key => $sample) {
    echo str_repeat('=', 60)."\n";
    echo "SCENARIO: {$key} — {$sample['label']}\n";
    echo "NIDA: {$sample['nida']}\n";
    echo str_repeat('-', 60)."\n";

    $result = $crb->verifyConsumerIdentity($sample['nida'], 'Test Borrower', '1990-05-15');

    printResult($result);

    if ($result->isMultihit() && ! empty($result->candidates)) {
        $first = $result->candidates[0];
        echo "\n--- After selecting first candidate (entity_key: {$first['entity_key']}) ---\n";
        $searchId = $result->raw['search_request_id'] ?? 'stub-search';
        $confirmed = $crb->fetchByEntityKey($searchId, (string) $first['entity_key'], $sample['nida']);
        printResult($confirmed);
    }

    echo "\n";
}

function printResult(object $result): void
{
    $fields = [
        'success'      => $result->success,
        'status'       => $result->status,
        'message'      => $result->message,
        'fullName'     => $result->fullName,
        'firstName'    => $result->firstName,
        'lastName'     => $result->lastName,
        'dateOfBirth'  => $result->dateOfBirth,
        'gender'       => $result->gender,
        'nationalId'   => $result->nationalId,
        'searchScore'  => $result->searchScore,
        'crbRuid'      => $result->crbRuid,
    ];

    foreach ($fields as $label => $value) {
        $display = $value === null ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        echo str_pad($label, 14).': '.$display."\n";
    }

    if (! empty($result->candidates)) {
        echo "candidates:\n";
        echo json_encode($result->candidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }

    if (! empty($result->raw)) {
        echo "raw:\n";
        echo json_encode($result->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    }
}
