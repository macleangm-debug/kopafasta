<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('angle');
            $table->string('file_path');
            $table->string('status')->default('pending_review');
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'angle']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('face_verification_status')->default('incomplete');
            $table->timestamp('face_verified_at')->nullable();
            $table->text('face_rejection_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_verifications');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'face_verification_status',
                'face_verified_at',
                'face_rejection_notes',
            ]);
        });
    }
};
