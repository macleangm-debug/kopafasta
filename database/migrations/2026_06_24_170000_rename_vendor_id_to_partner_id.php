<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** @return list<array{0: string, 1: string, 2: string, 3: string}> */
    private function columns(): array
    {
        return [
            ['vendor_tasks', 'vendor_id', 'partner_id', 'cascade'],
            ['vendor_documents', 'vendor_id', 'partner_id', 'cascade'],
            ['vendor_payments', 'vendor_id', 'partner_id', 'cascade'],
            ['partner_settlements', 'vendor_id', 'partner_id', 'cascade'],
            ['marketplace_assets', 'vendor_id', 'partner_id', 'set null'],
            ['asset_requests', 'vendor_id', 'partner_id', 'set null'],
            ['customers', 'affiliate_vendor_id', 'affiliate_partner_id', 'set null'],
            ['expenses', 'vendor_id', 'partner_id', 'set null'],
            ['recovery_assignments', 'vendor_id', 'partner_id', 'cascade'],
            ['valuation_assignments', 'vendor_id', 'partner_id', 'cascade'],
            ['affiliate_events', 'vendor_id', 'partner_id', 'cascade'],
        ];
    }

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->columns() as [$table, $from, $to, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($from): void {
                try {
                    $blueprint->dropForeign([$from]);
                } catch (\Throwable) {
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
                $blueprint->renameColumn($from, $to);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($to, $onDelete): void {
                $foreign = $blueprint->foreign($to)->references('id')->on('partners');
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
        Schema::disableForeignKeyConstraints();

        foreach ($this->columns() as [$table, $from, $to, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $to) || Schema::hasColumn($table, $from)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($to): void {
                try {
                    $blueprint->dropForeign([$to]);
                } catch (\Throwable) {
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
                $blueprint->renameColumn($to, $from);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($from, $onDelete): void {
                $foreign = $blueprint->foreign($from)->references('id')->on('partners');
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
