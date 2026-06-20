<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** @return list<array{0: string, 1: string, 2: string}> */
    private function foreignKeys(): array
    {
        return [
            ['vendor_tasks', 'vendor_id', 'cascade'],
            ['vendor_documents', 'vendor_id', 'cascade'],
            ['vendor_payments', 'vendor_id', 'cascade'],
            ['partner_settlements', 'vendor_id', 'cascade'],
            ['marketplace_assets', 'vendor_id', 'set null'],
            ['asset_requests', 'vendor_id', 'set null'],
            ['customers', 'affiliate_vendor_id', 'set null'],
            ['expenses', 'vendor_id', 'set null'],
            ['recovery_assignments', 'vendor_id', 'cascade'],
            ['valuation_assignments', 'vendor_id', 'cascade'],
            ['affiliate_events', 'vendor_id', 'cascade'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($this->foreignKeys() as [$table, $column, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                try {
                    $blueprint->dropForeign([$column]);
                } catch (\Throwable) {
                    // FK may already be missing in some environments.
                }
            });
        }

        Schema::rename('vendors', 'partners');

        foreach ($this->foreignKeys() as [$table, $column, $onDelete]) {
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

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($this->foreignKeys() as [$table, $column, $onDelete]) {
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

        Schema::rename('partners', 'vendors');

        foreach ($this->foreignKeys() as [$table, $column, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $onDelete): void {
                $foreign = $blueprint->foreign($column)->references('id')->on('vendors');
                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
