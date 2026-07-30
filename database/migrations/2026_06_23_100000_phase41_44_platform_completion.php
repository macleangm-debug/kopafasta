<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('loan_groups')) {
            Schema::create('loan_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('group_number', 40)->unique();
                $table->string('name', 150)->nullable();
                $table->foreignId('leader_customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('primary_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
                $table->string('status', 30)->default('forming');
                $table->string('recovery_stage', 30)->default('individual');
                $table->unsignedSmallInteger('target_member_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_group_members')) {
            Schema::create('loan_group_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_group_id')->constrained('loan_groups')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('loan_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
                $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
                $table->string('role', 20)->default('member');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('disbursement_status', 20)->default('locked');
                $table->unsignedSmallInteger('successful_repayments')->default(0);
                $table->timestamp('disbursement_unlocked_at')->nullable();
                $table->timestamp('disbursed_at')->nullable();
                $table->timestamps();

                $table->unique(['loan_group_id', 'customer_id']);
            });
        }

        Schema::table('loan_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_applications', 'loan_group_id')) {
                $table->foreignId('loan_group_id')->nullable()->constrained('loan_groups')->nullOnDelete();
            }
        });

        Schema::table('vendors', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendors', 'coverage_type')) {
                $table->string('coverage_type', 20)->default('regions');
            }
        });

        if (! Schema::hasTable('location_countries')) {
            Schema::create('location_countries', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('location_regions')) {
            Schema::create('location_regions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('country_id')->constrained('location_countries')->cascadeOnDelete();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['country_id', 'name']);
            });
        }

        if (! Schema::hasTable('location_districts')) {
            Schema::create('location_districts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('region_id')->constrained('location_regions')->cascadeOnDelete();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['region_id', 'name']);
            });
        }

        if (! Schema::hasTable('location_wards')) {
            Schema::create('location_wards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('district_id')->constrained('location_districts')->cascadeOnDelete();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['district_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_applications', 'loan_group_id')) {
                $table->dropConstrainedForeignId('loan_group_id');
            }
        });

        Schema::table('vendors', function (Blueprint $table): void {
            if (Schema::hasColumn('vendors', 'coverage_type')) {
                $table->dropColumn('coverage_type');
            }
        });

        Schema::dropIfExists('loan_group_members');
        Schema::dropIfExists('loan_groups');
        Schema::dropIfExists('location_wards');
        Schema::dropIfExists('location_districts');
        Schema::dropIfExists('location_regions');
        Schema::dropIfExists('location_countries');
    }
};
