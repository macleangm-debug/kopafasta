<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('nida_verification_status')->default('unverified');
            $table->timestamp('nida_verified_at')->nullable();
            $table->string('nida_verified_source')->nullable();
            $table->boolean('identity_locked')->default(false);
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
