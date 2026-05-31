<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('nida_verification_status')->default('unverified')->after('national_id');
            $table->timestamp('nida_verified_at')->nullable()->after('nida_verification_status');
            $table->string('nida_verified_source')->nullable()->after('nida_verified_at');
            $table->boolean('identity_locked')->default(false)->after('nida_verified_source');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'nida_verification_status',
                'nida_verified_at',
                'nida_verified_source',
                'identity_locked',
            ]);
        });
    }
};
