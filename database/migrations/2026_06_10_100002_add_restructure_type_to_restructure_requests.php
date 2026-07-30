<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restructure_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('restructure_requests', 'restructure_type')) {
                $table->string('restructure_type', 40)->nullable();
            }
            if (! Schema::hasColumn('restructure_requests', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restructure_requests', function (Blueprint $table) {
            if (Schema::hasColumn('restructure_requests', 'restructure_type')) {
                $table->dropColumn('restructure_type');
            }
            if (Schema::hasColumn('restructure_requests', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
