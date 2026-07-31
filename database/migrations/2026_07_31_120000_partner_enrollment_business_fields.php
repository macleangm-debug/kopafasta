<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->string('partner_category', 60)->nullable()->after('type');
            $table->string('legal_name', 150)->nullable()->after('business_name');
            $table->string('registration_number', 80)->nullable()->after('legal_name');
            $table->string('tin', 40)->nullable()->after('registration_number');
            $table->json('coverage_regions')->nullable()->after('region');
            $table->foreignId('partner_id')->nullable()->after('reviewed_at')->constrained('partners')->nullOnDelete();
        });

        Schema::create('partner_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_application_id')->constrained('partner_applications')->cascadeOnDelete();
            $table->string('doc_type', 40);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->string('legal_name', 150)->nullable()->after('name');
            $table->string('registration_number', 80)->nullable()->after('legal_name');
            $table->string('tin', 40)->nullable()->after('registration_number');
        });

        Schema::table('partner_documents', function (Blueprint $table) {
            $table->string('doc_type', 40)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('partner_documents', function (Blueprint $table) {
            $table->dropColumn('doc_type');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'registration_number', 'tin']);
        });

        Schema::dropIfExists('partner_application_documents');

        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn([
                'partner_category',
                'legal_name',
                'registration_number',
                'tin',
                'coverage_regions',
            ]);
        });
    }
};
