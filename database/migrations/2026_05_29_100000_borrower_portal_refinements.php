<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'middle_name')) {
                $table->string('middle_name', 60)->nullable();
            }
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_logs', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
            if (! Schema::hasColumn('notification_logs', 'category')) {
                $table->string('category', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'middle_name')) {
                $table->dropColumn('middle_name');
            }
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $cols = array_filter(['read_at', 'category'], fn (string $c) => Schema::hasColumn('notification_logs', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
