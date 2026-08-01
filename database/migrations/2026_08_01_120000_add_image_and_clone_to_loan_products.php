<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_products', 'image_path')) {
                $table->string('image_path')->nullable()->after('description');
            }
            if (! Schema::hasColumn('loan_products', 'clone_source_id')) {
                $table->foreignId('clone_source_id')->nullable()->after('image_path')
                    ->constrained('loan_products')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (Schema::hasColumn('loan_products', 'clone_source_id')) {
                $table->dropConstrainedForeignId('clone_source_id');
            }
            if (Schema::hasColumn('loan_products', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
