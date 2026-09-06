<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controlled production cleanup of confirmed QA / integration-test customer contamination.
 * Never deletes MacLean's genuine borrower registration or payments with ledger money movement.
 */
class CleanProductionQaCustomersCommand extends Command
{
    protected $signature = 'production:clean-qa-customers
        {--dry-run : Report only}
        {--confirm= : Must be CLEAN_PRODUCTION_QA to mutate}';

    protected $description = 'Remove confirmed Integration LiveTest / BP ProdSafe QA customers from production';

    public function handle(): int
    {
        if (! app()->isProduction() && ! $this->option('dry-run')) {
            $this->error('Refusing to mutate outside production without --dry-run.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $confirm = (string) $this->option('confirm');
        if (! $dry && $confirm !== 'CLEAN_PRODUCTION_QA') {
            $this->error('Pass --confirm=CLEAN_PRODUCTION_QA to mutate.');

            return self::FAILURE;
        }

        $before = Customer::query()->orderBy('id')->get(['id', 'customer_number', 'first_name', 'last_name', 'phone', 'user_id', 'activity_details']);
        $this->info('Customers before: '.$before->count());

        $preserve = Customer::query()
            ->where('customer_number', 'C-SU68XO')
            ->orWhere('phone', '255715222132')
            ->pluck('id')
            ->all();

        $qaCustomers = Customer::query()
            ->whereNotIn('id', $preserve ?: [0])
            ->where(function ($q) {
                $q->where('customer_number', 'like', 'TST-%')
                    ->orWhere('customer_number', 'like', 'CU-BPS-%')
                    ->orWhere('first_name', 'Integration')
                    ->orWhere('last_name', 'LiveTest')
                    ->orWhere('last_name', 'ProdSafe')
                    ->orWhere('activity_details->integration_live_test', true);
            })
            ->get();

        // Also catch BP by linked @kopafasta.test user email.
        $bpUserIds = User::query()
            ->where('email', 'like', '%@kopafasta.test')
            ->orWhere('email', 'like', 'bp.prodsafe.%')
            ->pluck('id');
        $qaCustomers = $qaCustomers
            ->merge(Customer::query()->whereIn('user_id', $bpUserIds)->get())
            ->unique('id')
            ->reject(fn (Customer $c) => in_array($c->id, $preserve, true))
            ->values();

        $this->table(
            ['id', 'number', 'name', 'phone', 'user_id'],
            $qaCustomers->map(fn (Customer $c) => [
                $c->id,
                $c->customer_number,
                trim($c->first_name.' '.$c->last_name),
                $c->phone,
                $c->user_id,
            ])->all()
        );

        if ($qaCustomers->isEmpty()) {
            $this->info('No QA customers matched.');

            return self::SUCCESS;
        }

        $qaIds = $qaCustomers->pluck('id')->all();
        $payments = CustomerPayment::query()->whereIn('customer_id', $qaIds)->get();
        $moneyMoved = $payments->filter(fn (CustomerPayment $p) => filled($p->paid_at) || filled($p->journal_entry_id));
        if ($moneyMoved->isNotEmpty()) {
            $this->error('Aborting: money-moved payments found on QA customers: '.$moneyMoved->pluck('id')->join(','));

            return self::FAILURE;
        }

        $userIds = $qaCustomers->pluck('user_id')->filter()->unique()->values();

        $plan = [
            'detach_payments' => $payments->pluck('id')->all(),
            'delete_customers' => $qaIds,
            'delete_users' => $userIds->all(),
            'preserve_customers' => $preserve,
        ];
        $this->line(json_encode($plan, JSON_PRETTY_PRINT));

        if ($dry) {
            $this->warn('Dry run only — no changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($payments, $qaIds, $userIds) {
            foreach ($payments as $payment) {
                $meta = (array) ($payment->provider_meta ?? []);
                $meta['integration_rehearsal'] = true;
                $meta['integration_live_test'] = $meta['integration_live_test'] ?? true;
                $meta['detached_from_qa_customer_id'] = $payment->customer_id;
                $meta['qa_cleanup_at'] = now()->toIso8601String();
                $payment->update([
                    'customer_id' => null,
                    'provider_meta' => $meta,
                ]);
            }

            // Remove dependent QA rows that would block customer delete.
            if (Schema::hasTable('notification_logs')) {
                DB::table('notification_logs')->whereIn('customer_id', $qaIds)->delete();
            }
            if (Schema::hasTable('customer_documents')) {
                DB::table('customer_documents')->whereIn('customer_id', $qaIds)->delete();
            }
            if (Schema::hasTable('security_answers')) {
                DB::table('security_answers')->whereIn('user_id', $userIds)->delete();
            }
            if (Schema::hasTable('pin_reset_tokens')) {
                DB::table('pin_reset_tokens')->whereIn('user_id', $userIds)->delete();
            }

            Customer::query()->whereIn('id', $qaIds)->delete();
            User::query()
                ->whereIn('id', $userIds)
                ->where('role', 'borrower')
                ->where(function ($q) {
                    $q->where('email', 'like', '%@kopafasta.test')
                        ->orWhere('email', 'like', 'bp.prodsafe.%');
                })
                ->delete();

            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'user_id' => null,
                    'event' => 'production.qa_customers_cleaned',
                    'auditable_type' => Customer::class,
                    'auditable_id' => null,
                    'old_values' => json_encode(['customer_ids' => $qaIds]),
                    'new_values' => json_encode(['detached_payment_ids' => $payments->pluck('id')->all()]),
                    'ip_address' => null,
                    'user_agent' => 'artisan production:clean-qa-customers',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $after = Customer::query()->count();
        $this->info('Customers after: '.$after);
        $this->info('Payments preserved (customer_id nulled): '.$payments->count());

        return self::SUCCESS;
    }
}
