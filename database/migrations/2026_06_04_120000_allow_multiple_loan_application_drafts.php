<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_application_drafts')) {
            return;
        }

        $indexName = 'loan_application_drafts_customer_id_index';
        $uniqueName = 'loan_application_drafts_customer_id_unique';
        $compositeName = 'loan_application_drafts_customer_id_loan_product_id_unique';

        if (! $this->indexExists('loan_application_drafts', $indexName)) {
            Schema::table('loan_application_drafts', function (Blueprint $table) use ($indexName): void {
                $table->index('customer_id', $indexName);
            });
        }

        if ($this->indexExists('loan_application_drafts', $uniqueName)) {
            Schema::table('loan_application_drafts', function (Blueprint $table): void {
                $table->dropUnique(['customer_id']);
            });
        }

        if (! $this->indexExists('loan_application_drafts', $compositeName)) {
            Schema::table('loan_application_drafts', function (Blueprint $table): void {
                $table->unique(['customer_id', 'loan_product_id']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_application_drafts')) {
            return;
        }

        $indexName = 'loan_application_drafts_customer_id_index';
        $compositeName = 'loan_application_drafts_customer_id_loan_product_id_unique';

        if ($this->indexExists('loan_application_drafts', $compositeName)) {
            Schema::table('loan_application_drafts', function (Blueprint $table): void {
                $table->dropUnique(['customer_id', 'loan_product_id']);
            });
        }

        if (! $this->indexExists('loan_application_drafts', 'loan_application_drafts_customer_id_unique')) {
            Schema::table('loan_application_drafts', function (Blueprint $table): void {
                $table->unique('customer_id');
            });
        }

        if ($this->indexExists('loan_application_drafts', $indexName)) {
            Schema::table('loan_application_drafts', function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            return collect($rows)->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        $database = $connection->getDatabaseName();
        $rows = $connection->select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
