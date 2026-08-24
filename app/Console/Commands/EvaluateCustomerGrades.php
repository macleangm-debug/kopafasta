<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Grades\CustomerGradeEngine;
use Illuminate\Console\Command;

class EvaluateCustomerGrades extends Command
{
    protected $signature = 'grades:evaluate {--customer=} {--backtest}';

    protected $description = 'Re-evaluate customer grades from Source of Truth rules';

    public function handle(CustomerGradeEngine $engine): int
    {
        if ($this->option('backtest')) {
            $this->table(['Grade', 'Customers'], collect($engine->backtest())->map(fn ($n, $g) => [$g, $n])->all());

            return self::SUCCESS;
        }

        $query = Customer::query()->orderBy('id');
        if ($id = $this->option('customer')) {
            $query->where('id', $id);
        }

        $count = 0;
        $query->chunkById(100, function ($chunk) use ($engine, &$count) {
            foreach ($chunk as $customer) {
                $engine->evaluate($customer, 'scheduled');
                $count++;
            }
        });

        $this->info("Evaluated {$count} customer(s).");

        return self::SUCCESS;
    }
}
