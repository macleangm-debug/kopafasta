<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plus_businesses')) {
            Schema::create('plus_businesses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['customer_id', 'is_active']);
            });
        }

        if (Schema::hasTable('plus_business_entries') && ! Schema::hasColumn('plus_business_entries', 'plus_business_id')) {
            Schema::table('plus_business_entries', function (Blueprint $table) {
                $table->foreignId('plus_business_id')->nullable()->after('customer_id')->constrained('plus_businesses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plus_business_entries') && Schema::hasColumn('plus_business_entries', 'plus_business_id')) {
            Schema::table('plus_business_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plus_business_id');
            });
        }

        Schema::dropIfExists('plus_businesses');
    }
};
