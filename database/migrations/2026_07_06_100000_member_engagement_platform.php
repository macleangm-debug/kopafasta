<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'loyalty_points')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedInteger('loyalty_points')->default(0);
            });
        }

        if (! Schema::hasTable('loyalty_point_transactions')) {
            Schema::create('loyalty_point_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20);
                $table->integer('points');
                $table->string('action_key', 80)->nullable();
                $table->string('description')->nullable();
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('referral_attributions')) {
            Schema::create('referral_attributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('session_token', 64)->index();
                $table->string('referral_code', 40);
                $table->timestamp('expires_at');
                $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('profile_section_definitions')) {
            Schema::create('profile_section_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->string('icon', 20)->nullable();
                $table->string('name_en');
                $table->string('name_sw')->nullable();
                $table->text('description_en')->nullable();
                $table->text('description_sw')->nullable();
                $table->boolean('is_required')->default(true);
                $table->string('input_type', 40)->default('text');
                $table->json('validation_rules')->nullable();
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->boolean('required_before_loan')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('promotions')) {
            Schema::table('promotions', function (Blueprint $table) {
                if (! Schema::hasColumn('promotions', 'original_fee')) {
                    $table->decimal('original_fee', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('promotions', 'discount_type')) {
                    $table->string('discount_type', 20)->default('percentage');
                }
                if (! Schema::hasColumn('promotions', 'eligible_members')) {
                    $table->string('eligible_members', 30)->default('all');
                }
                if (! Schema::hasColumn('promotions', 'banner_path')) {
                    $table->string('banner_path')->nullable();
                }
                if (! Schema::hasColumn('promotions', 'message_en')) {
                    $table->text('message_en')->nullable();
                }
                if (! Schema::hasColumn('promotions', 'message_sw')) {
                    $table->text('message_sw')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_section_definitions');
        Schema::dropIfExists('referral_attributions');
        Schema::dropIfExists('loyalty_point_transactions');

        if (Schema::hasColumn('customers', 'loyalty_points')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('loyalty_points');
            });
        }

        if (Schema::hasTable('promotions')) {
            Schema::table('promotions', function (Blueprint $table) {
                foreach (['original_fee', 'discount_type', 'eligible_members', 'banner_path', 'message_en', 'message_sw'] as $col) {
                    if (Schema::hasColumn('promotions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
