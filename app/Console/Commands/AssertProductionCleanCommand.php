<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\MarketplaceAsset;
use App\Models\NotificationLog;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Production initialization guard: fail if any test/operational dataset is present.
 */
class AssertProductionCleanCommand extends Command
{
    protected $signature = 'production:assert-clean';

    protected $description = 'Fail if the database contains borrowers, loans, payments, partners, or other test/operational records.';

    public function handle(): int
    {
        if (app()->environment('staging')) {
            $this->error('Refusing: production:assert-clean is not for the staging dataset.');

            return self::FAILURE;
        }

        if (! app()->isProduction() && ! app()->environment('testing')) {
            $this->error('Refusing: this command only runs in production (or automated tests).');

            return self::FAILURE;
        }

        $failures = [];
        $checks = [
            'customers' => [Customer::class, 0],
            'loan_applications' => [LoanApplication::class, 0],
            'loans' => [Loan::class, 0],
            'customer_payments' => [CustomerPayment::class, 0],
            'partners' => [Partner::class, 0],
            'marketplace_assets' => [MarketplaceAsset::class, 0],
            'customer_documents' => [CustomerDocument::class, 0],
        ];

        foreach ($checks as $label => [$model, $max]) {
            if (! class_exists($model) || (method_exists($model, 'query') && ! Schema::hasTable((new $model)->getTable()))) {
                continue;
            }
            $count = $model::query()->count();
            if ($count > $max) {
                $failures[] = "{$label}={$count} (must be {$max})";
            }
        }

        if (Schema::hasTable('notification_logs')) {
            $count = NotificationLog::query()->count();
            if ($count > 0) {
                $failures[] = "notification_logs={$count} (must be 0)";
            }
        }

        $staff = User::query()->whereNotIn('role', ['admin'])->count();
        if ($staff > 0) {
            $failures[] = "non-admin users={$staff} (must be 0)";
        }

        $admins = User::query()->where('role', 'admin')->count();
        if ($admins > 1) {
            $failures[] = "admin users={$admins} (must be 0 or 1 owner bootstrap)";
        }

        if ($failures !== []) {
            $this->error('Production database is not clean:');
            foreach ($failures as $failure) {
                $this->error(' - '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('Production database is clean of test/operational records.');

        return self::SUCCESS;
    }
}
