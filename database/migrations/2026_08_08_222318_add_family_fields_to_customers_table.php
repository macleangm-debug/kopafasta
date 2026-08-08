<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('marital_status', 20)->nullable()->after('gender');
            $table->string('spouse_first_name', 80)->nullable()->after('marital_status');
            $table->string('spouse_middle_name', 80)->nullable()->after('spouse_first_name');
            $table->string('spouse_last_name', 80)->nullable()->after('spouse_middle_name');
            $table->unsignedTinyInteger('number_of_children')->nullable()->after('spouse_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'marital_status',
                'spouse_first_name',
                'spouse_middle_name',
                'spouse_last_name',
                'number_of_children',
            ]);
        });
    }
};
