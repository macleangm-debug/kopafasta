<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_application_post_approval_fees') && ! $this->hasForeign('loan_application_post_approval_fees', 'app_post_approval_fee_template_fk')) {
            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_application_post_approval_fees', 'loan_product_post_approval_fee_id')) {
                    $table->unsignedBigInteger('loan_product_post_approval_fee_id')->nullable();
                }
            });

            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                $table->foreign('loan_product_post_approval_fee_id', 'app_post_approval_fee_template_fk')
                    ->references('id')
                    ->on('loan_product_post_approval_fees')
                    ->nullOnDelete();
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

        if (Schema::hasTable('marketplace_assets') && ! Schema::hasTable('asset_reservations')) {
            Schema::create('asset_reservations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('marketplace_asset_id')->constrained()->cascadeOnDelete();
                $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status', 40)->default('viewing_scheduled');
                $table->date('viewing_date')->nullable();
                $table->string('viewing_time', 20)->nullable();
                $table->decimal('reservation_fee_amount', 15, 2)->default(0);
                $table->string('reservation_fee_status', 20)->default('pending');
                $table->timestamp('reservation_fee_paid_at')->nullable();
                $table->string('reservation_payment_reference', 60)->nullable();
                $table->decimal('deposit_amount', 15, 2)->default(0);
                $table->string('deposit_status', 20)->default('pending');
                $table->timestamp('deposit_paid_at')->nullable();
                $table->string('deposit_payment_reference', 60)->nullable();
                $table->timestamp('viewing_completed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No-op repair migration.
    }

    private function hasForeign(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $foreignKeys = $connection->select("PRAGMA foreign_key_list({$table})");

            return collect($foreignKeys)->contains(fn ($fk) => ($fk->id ?? null) !== null);
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return count($result) > 0;
    }
};
