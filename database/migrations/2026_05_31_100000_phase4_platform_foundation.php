<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('customer_number');
            }
            if (! Schema::hasColumn('customers', 'referred_by_customer_id')) {
                $table->foreignId('referred_by_customer_id')->nullable()->after('referral_code')->constrained('customers')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('referral_wallets')) {
            Schema::create('referral_wallets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
                $table->decimal('balance', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('referral_transactions')) {
            Schema::create('referral_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('referral_wallet_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20);
                $table->decimal('amount', 15, 2);
                $table->string('description')->nullable();
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_product_post_approval_fees')) {
            Schema::create('loan_product_post_approval_fees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('name');
                $table->string('fee_type', 20)->default('fixed');
                $table->decimal('amount', 15, 4)->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_product_rate_tiers')) {
            Schema::create('loan_product_rate_tiers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
                $table->decimal('min_amount', 15, 2);
                $table->decimal('max_amount', 15, 2);
                $table->decimal('monthly_rate', 8, 4);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_application_post_approval_fees')) {
            Schema::create('loan_application_post_approval_fees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('loan_product_post_approval_fee_id')->nullable();
                $table->string('code', 40);
                $table->string('name');
                $table->string('fee_type', 20);
                $table->decimal('configured_amount', 15, 4)->default(0);
                $table->decimal('calculated_amount', 15, 2)->default(0);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->string('status', 20)->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->foreign('loan_product_post_approval_fee_id', 'app_post_approval_fee_template_fk')
                    ->references('id')
                    ->on('loan_product_post_approval_fees')
                    ->nullOnDelete();
            });
        } elseif (! $this->hasForeign('loan_application_post_approval_fees', 'app_post_approval_fee_template_fk')) {
            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_application_post_approval_fees', 'loan_product_post_approval_fee_id')) {
                    $table->unsignedBigInteger('loan_product_post_approval_fee_id')->nullable()->after('loan_application_id');
                }
            });

            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                $table->foreign('loan_product_post_approval_fee_id', 'app_post_approval_fee_template_fk')
                    ->references('id')
                    ->on('loan_product_post_approval_fees')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('marketplace_assets')) {
            Schema::create('marketplace_assets', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('category', 40);
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('supplier_name');
                $table->decimal('asset_value', 15, 2)->default(0);
                $table->decimal('supplier_deposit', 15, 2)->default(0);
                $table->decimal('deposit_markup_percent', 8, 2)->default(0);
                $table->decimal('customer_deposit', 15, 2)->default(0);
                $table->decimal('weekly_installment', 15, 2)->default(0);
                $table->unsignedSmallInteger('max_tenure_months')->default(12);
                $table->json('photos')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_requests')) {
            Schema::create('asset_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('asset_name');
                $table->decimal('budget', 15, 2)->nullable();
                $table->unsignedSmallInteger('preferred_tenure_months')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    private function hasForeign(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            if (! Schema::hasTable($table)) {
                return false;
            }

            $foreignKeys = $connection->select("PRAGMA foreign_key_list({$table})");

            return count($foreignKeys) > 0;
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return count($result) > 0;
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
        Schema::dropIfExists('marketplace_assets');
        Schema::dropIfExists('loan_application_post_approval_fees');
        Schema::dropIfExists('loan_product_rate_tiers');
        Schema::dropIfExists('loan_product_post_approval_fees');
        Schema::dropIfExists('referral_transactions');
        Schema::dropIfExists('referral_wallets');

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'referred_by_customer_id')) {
                $table->dropConstrainedForeignId('referred_by_customer_id');
            }
            if (Schema::hasColumn('customers', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });
    }
};
