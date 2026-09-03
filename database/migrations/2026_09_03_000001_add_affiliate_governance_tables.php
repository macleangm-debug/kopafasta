<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'payload')) {
                $table->json('payload')->nullable();
            }
        });

        Schema::table('partners', function (Blueprint $table): void {
            if (! Schema::hasColumn('partners', 'affiliate_performance_status')) {
                $table->string('affiliate_performance_status', 40)->nullable();
            }
        });

        if (! Schema::hasTable('partner_agreement_acceptances')) {
            Schema::create('partner_agreement_acceptances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->string('partner_type', 40);
                $table->string('agreement_key', 80);
                $table->unsignedInteger('agreement_version')->default(1);
                $table->unsignedInteger('policy_version')->default(1);
                $table->string('locale', 8)->default('en');
                $table->longText('rendered_text');
                $table->string('content_hash', 64);
                $table->json('settings_snapshot')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('accepted_at');
                $table->timestamps();

                $table->index(['partner_id', 'agreement_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreement_acceptances');

        Schema::table('partners', function (Blueprint $table): void {
            if (Schema::hasColumn('partners', 'affiliate_performance_status')) {
                $table->dropColumn('affiliate_performance_status');
            }
        });

        Schema::table('partner_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_applications', 'payload')) {
                $table->dropColumn('payload');
            }
        });
    }
};
