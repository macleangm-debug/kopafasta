<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'pin_hash')) {
                $table->string('pin_hash')->nullable();
            }
            if (! Schema::hasColumn('users', 'pin_set_at')) {
                $table->timestamp('pin_set_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'pin_set_at')) {
                $table->dropColumn('pin_set_at');
            }
            if (Schema::hasColumn('users', 'pin_hash')) {
                $table->dropColumn('pin_hash');
            }
        });
    }
};
