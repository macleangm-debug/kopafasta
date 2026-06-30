<?php

namespace App\Console\Commands;

use App\Services\AffiliateFraudDetectionService;
use App\Models\Vendor;
use Illuminate\Console\Command;

class ScanAffiliateFraud extends Command
{
    protected $signature = 'affiliate:scan-fraud {--partner= : Limit scan to partner ID} {--dry-run : Preview without saving}';

    protected $description = 'Scan affiliates for self-referral, shared device, and duplicate identity fraud signals';

    public function handle(AffiliateFraudDetectionService $fraud): int
    {
        $query = Vendor::query()->where('category', 'affiliate')->where('status', '!=', 'inactive');

        if ($partnerId = $this->option('partner')) {
            $query->where('id', $partnerId);
        }

        $affiliates = $query->get();
        $counts = ['low' => 0, 'medium' => 0, 'high' => 0, 'blocked' => 0];

        foreach ($affiliates as $affiliate) {
            $result = $fraud->scan($affiliate);
            $counts[$result['risk_flag']] = ($counts[$result['risk_flag']] ?? 0) + 1;

            if ($this->option('dry-run')) {
                if ($result['risk_flag'] !== AffiliateFraudDetectionService::FLAG_LOW) {
                    $this->line("{$affiliate->name} ({$affiliate->affiliate_code}): {$result['risk_flag']} — score {$result['score']}");
                }

                continue;
            }

            $fraud->scanAndPersist($affiliate);
        }

        $this->info('Scanned '.$affiliates->count().' affiliate(s).');
        $this->table(['Risk flag', 'Count'], collect($counts)->map(fn ($count, $flag) => [$flag, $count])->values()->all());

        return self::SUCCESS;
    }
}
