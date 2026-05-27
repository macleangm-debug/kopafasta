<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->unsignedInteger('registration_fee_amount')->nullable()->after('purpose');
            $table->string('registration_fee_status', 20)->default('unpaid')->after('registration_fee_amount');
            $table->string('registration_fee_reference', 60)->nullable()->after('registration_fee_status');
            $table->string('registration_fee_channel', 30)->nullable()->after('registration_fee_reference');
            $table->timestamp('registration_fee_paid_at')->nullable()->after('registration_fee_channel');
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'registration_fee_amount',
                'registration_fee_status',
                'registration_fee_reference',
                'registration_fee_channel',
                'registration_fee_paid_at',
            ]);
        });
    }
};
