<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->unsignedInteger('application_fee_amount')->nullable();
            $table->string('application_fee_status', 20)->default('unpaid');
            $table->string('application_fee_reference', 60)->nullable();
            $table->string('application_fee_channel', 30)->nullable();
            $table->timestamp('application_fee_paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'application_fee_amount',
                'application_fee_status',
                'application_fee_reference',
                'application_fee_channel',
                'application_fee_paid_at',
            ]);
        });
    }
};
