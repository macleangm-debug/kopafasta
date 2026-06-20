<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** @return list<string> */
    private function tables(): array
    {
        return [
            'partner_documents',
            'partner_payments',
            'recovery_assignments',
            'valuation_assignments',
        ];
    }

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'vendor_task_id') || Schema::hasColumn($table, 'partner_task_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                try {
                    $blueprint->dropForeign(['vendor_task_id']);
                } catch (\Throwable) {
                }
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('vendor_task_id', 'partner_task_id');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('partner_task_id')->references('id')->on('partner_tasks')->nullOnDelete();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'partner_task_id') || Schema::hasColumn($table, 'vendor_task_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                try {
                    $blueprint->dropForeign(['partner_task_id']);
                } catch (\Throwable) {
                }
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('partner_task_id', 'vendor_task_id');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('vendor_task_id')->references('id')->on('partner_tasks')->nullOnDelete();
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
