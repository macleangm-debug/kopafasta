<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reference_sequences')) {
            Schema::create('reference_sequences', function (Blueprint $table): void {
                $table->id();
                $table->string('prefix', 8);
                $table->string('product_code', 16);
                $table->unsignedBigInteger('last_value')->default(0);
                $table->timestamps();

                $table->unique(['prefix', 'product_code']);
            });
        }

        if (Schema::hasTable('loan_application_drafts') && ! Schema::hasColumn('loan_application_drafts', 'draft_reference')) {
            Schema::table('loan_application_drafts', function (Blueprint $table): void {
                $table->string('draft_reference', 32)->nullable()->after('loan_product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_application_drafts') && Schema::hasColumn('loan_application_drafts', 'draft_reference')) {
            Schema::table('loan_application_drafts', function (Blueprint $table): void {
                $table->dropColumn('draft_reference');
            });
        }

        Schema::dropIfExists('reference_sequences');
    }
};
