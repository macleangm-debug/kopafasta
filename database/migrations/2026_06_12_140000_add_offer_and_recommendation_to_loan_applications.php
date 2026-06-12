<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            $table->string('recommendation_type')->nullable()->after('recommended_amount');
            $table->decimal('offered_amount', 15, 2)->nullable()->after('recommendation_type');
            $table->unsignedInteger('offered_tenure_months')->nullable()->after('offered_amount');
            $table->string('offer_status')->nullable()->after('offered_tenure_months');
            $table->timestamp('offer_issued_at')->nullable()->after('offer_status');
            $table->timestamp('offer_responded_at')->nullable()->after('offer_issued_at');
            $table->text('committee_recommendation')->nullable()->after('offer_responded_at');
            $table->foreignId('recommended_by')->nullable()->after('committee_recommendation')->constrained('users')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable()->after('recommended_by');
            $table->foreignId('alternative_loan_product_id')->nullable()->after('recommended_at')->constrained('loan_products')->nullOnDelete();
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
