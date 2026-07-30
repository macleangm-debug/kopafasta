<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lenders', function (Blueprint $table): void {
            if (! Schema::hasColumn('lenders', 'allocation_priority')) {
                $table->unsignedSmallInteger('allocation_priority')->nullable();
            }
        });

        if (! Schema::hasTable('partner_applications')) {
            Schema::create('partner_applications', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 30)->default('affiliate');
                $table->string('full_name');
                $table->string('email');
                $table->string('phone', 30);
                $table->string('business_name')->nullable();
                $table->string('region')->nullable();
                $table->text('message')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('admin_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');

        Schema::table('lenders', function (Blueprint $table): void {
            if (Schema::hasColumn('lenders', 'allocation_priority')) {
                $table->dropColumn('allocation_priority');
            }
        });
    }
};
