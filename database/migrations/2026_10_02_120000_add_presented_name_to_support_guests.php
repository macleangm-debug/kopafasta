<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_guests', function (Blueprint $table) {
            $table->string('presented_first_name', 80)->nullable()->after('last_name');
            $table->string('presented_last_name', 80)->nullable()->after('presented_first_name');
            $table->timestamp('name_mismatch_at')->nullable()->after('presented_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('support_guests', function (Blueprint $table) {
            $table->dropColumn(['presented_first_name', 'presented_last_name', 'name_mismatch_at']);
        });
    }
};
