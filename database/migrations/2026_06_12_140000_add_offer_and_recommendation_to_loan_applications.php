<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            $table->string('recommendation_type')->nullable();
            $table->decimal('offered_amount', 15, 2)->nullable();
            $table->unsignedInteger('offered_tenure_months')->nullable();
            $table->string('offer_status')->nullable();
            $table->timestamp('offer_issued_at')->nullable();
            $table->timestamp('offer_responded_at')->nullable();
            $table->text('committee_recommendation')->nullable();
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->foreignId('alternative_loan_product_id')->nullable()->constrained('loan_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('alternative_loan_product_id');
            $table->dropConstrainedForeignId('recommended_by');
            $table->dropColumn([
                'recommendation_type',
                'offered_amount',
                'offered_tenure_months',
                'offer_status',
                'offer_issued_at',
                'offer_responded_at',
                'committee_recommendation',
                'recommended_at',
            ]);
        });
    }
};
