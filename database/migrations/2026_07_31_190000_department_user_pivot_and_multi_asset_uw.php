<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('department_user')) {
            Schema::create('department_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['department_id', 'user_id']);
            });
        }

        if (Schema::hasColumn('users', 'department_id')) {
            $rows = DB::table('users')
                ->whereNotNull('department_id')
                ->select('id as user_id', 'department_id')
                ->get();

            $now = now();
            foreach ($rows as $row) {
                DB::table('department_user')->insertOrIgnore([
                    'department_id' => $row->department_id,
                    'user_id'       => $row->user_id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->dropMysqlUniqueLoanApplicationFk();
        } elseif ($driver === 'sqlite') {
            // SQLite: rebuild table without the unique constraint.
            $this->rebuildSqliteLoanApplicationAssets();
        } else {
            Schema::table('loan_application_assets', function (Blueprint $table): void {
                $table->dropUnique(['loan_application_id']);
            });
        }

        Schema::table('loan_application_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_application_assets', 'uw_status')) {
                $table->string('uw_status', 20)->default('pending')->after('valuation_status');
            }
            if (! Schema::hasColumn('loan_application_assets', 'uw_notes')) {
                $table->text('uw_notes')->nullable()->after('uw_status');
            }
            if (! Schema::hasColumn('loan_application_assets', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('uw_notes');
            }
        });

        $hasComposite = false;
        try {
            $hasComposite = collect(DB::select('SHOW INDEX FROM loan_application_assets'))
                ->contains(fn ($row) => ($row->Key_name ?? null) === 'loan_application_assets_loan_application_id_uw_status_index');
        } catch (\Throwable) {
            $hasComposite = false;
        }

        if (! $hasComposite && $driver !== 'sqlite') {
            Schema::table('loan_application_assets', function (Blueprint $table): void {
                $table->index(['loan_application_id', 'uw_status']);
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('loan_application_assets', function (Blueprint $table): void {
                $table->index(['loan_application_id', 'uw_status']);
            });
        }
    }

    private function dropMysqlUniqueLoanApplicationFk(): void
    {
        $foreignKeys = collect(DB::select('
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ', ['loan_application_assets', 'loan_application_id']))
            ->pluck('CONSTRAINT_NAME')
            ->unique()
            ->values();

        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE loan_application_assets DROP FOREIGN KEY `{$fk}`");
        }

        $indexes = collect(DB::select('SHOW INDEX FROM loan_application_assets WHERE Column_name = ? AND Non_unique = 0', ['loan_application_id']))
            ->pluck('Key_name')
            ->unique()
            ->reject(fn ($name) => $name === 'PRIMARY')
            ->values();

        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE loan_application_assets DROP INDEX `{$index}`");
        }

        Schema::table('loan_application_assets', function (Blueprint $table): void {
            $table->foreign('loan_application_id')
                ->references('id')
                ->on('loan_applications')
                ->cascadeOnDelete();
        });
    }

    private function rebuildSqliteLoanApplicationAssets(): void
    {
        // On SQLite refresh, unique may already be absent depending on how the
        // original create ran; only rebuild when a unique index is present.
        $indexes = collect(DB::select("PRAGMA index_list('loan_application_assets')"));
        $hasUnique = $indexes->contains(fn ($row) => (int) ($row->unique ?? 0) === 1
            && str_contains((string) ($row->name ?? ''), 'loan_application_id'));

        if (! $hasUnique) {
            return;
        }

        Schema::table('loan_application_assets', function (Blueprint $table): void {
            // Best-effort; SQLite may ignore dropUnique when FK-backed.
            try {
                $table->dropUnique(['loan_application_id']);
            } catch (\Throwable) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_application_assets', function (Blueprint $table): void {
            try {
                $table->dropIndex(['loan_application_id', 'uw_status']);
            } catch (\Throwable) {
                // ignore
            }
            $table->dropColumn(['uw_status', 'uw_notes', 'is_primary']);
        });

        Schema::dropIfExists('department_user');
    }
};
