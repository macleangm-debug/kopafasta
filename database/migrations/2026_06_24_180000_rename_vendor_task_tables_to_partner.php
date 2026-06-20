<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** @return list<array{0: string, 1: string}> */
    private function taskForeignKeys(): array
    {
        return [
            ['vendor_documents', 'vendor_task_id'],
            ['vendor_payments', 'vendor_task_id'],
            ['recovery_assignments', 'vendor_task_id'],
            ['valuation_assignments', 'vendor_task_id'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function partnerForeignKeys(): array
    {
        return [
            ['vendor_tasks', 'partner_id', 'cascade'],
            ['vendor_documents', 'partner_id', 'cascade'],
            ['vendor_payments', 'partner_id', 'cascade'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('vendor_tasks')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($this->taskForeignKeys() as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                try {
                    $blueprint->dropForeign([$column]);
                } catch (\Throwable) {
                }
            });
        }

        foreach ($this->partnerForeignKeys() as [$table, $column, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                try {
                    $blueprint->dropForeign([$column]);
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('vendor_tasks') && ! Schema::hasTable('partner_tasks')) {
            Schema::rename('vendor_tasks', 'partner_tasks');
        }

        if (Schema::hasTable('vendor_documents') && ! Schema::hasTable('partner_documents')) {
            Schema::rename('vendor_documents', 'partner_documents');
        }

        if (Schema::hasTable('vendor_payments') && ! Schema::hasTable('partner_payments')) {
            Schema::rename('vendor_payments', 'partner_payments');
        }

        foreach (['partner_tasks', 'partner_documents', 'partner_payments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'partner_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            });
        }

        foreach ($this->taskForeignKeys() as [$table, $column]) {
            $renamed = match ($table) {
                'vendor_documents' => 'partner_documents',
                'vendor_payments'  => 'partner_payments',
                default            => $table,
            };

            if (! Schema::hasTable($renamed) || ! Schema::hasColumn($renamed, $column)) {
                continue;
            }

            Schema::table($renamed, function (Blueprint $blueprint) use ($column): void {
                $blueprint->foreign($column)->references('id')->on('partner_tasks')->nullOnDelete();
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_tasks')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($this->taskForeignKeys() as [$table, $column]) {
            $renamed = match ($table) {
                'vendor_documents' => 'partner_documents',
                'vendor_payments'  => 'partner_payments',
                default            => $table,
            };

            if (! Schema::hasTable($renamed) || ! Schema::hasColumn($renamed, $column)) {
                continue;
            }

            Schema::table($renamed, function (Blueprint $blueprint) use ($column): void {
                try {
                    $blueprint->dropForeign([$column]);
                } catch (\Throwable) {
                }
            });
        }

        foreach (['partner_tasks', 'partner_documents', 'partner_payments'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'partner_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                try {
                    $blueprint->dropForeign(['partner_id']);
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('partner_tasks') && ! Schema::hasTable('vendor_tasks')) {
            Schema::rename('partner_tasks', 'vendor_tasks');
        }

        if (Schema::hasTable('partner_documents') && ! Schema::hasTable('vendor_documents')) {
            Schema::rename('partner_documents', 'vendor_documents');
        }

        if (Schema::hasTable('partner_payments') && ! Schema::hasTable('vendor_payments')) {
            Schema::rename('partner_payments', 'vendor_payments');
        }

        foreach ($this->partnerForeignKeys() as [$table, $column, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $onDelete): void {
                $foreign = $blueprint->foreign($column)->references('id')->on('partners');
                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        }

        foreach ($this->taskForeignKeys() as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->foreign($column)->references('id')->on('vendor_tasks')->nullOnDelete();
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
