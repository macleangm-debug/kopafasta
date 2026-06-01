<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendors', 'deposit_markup_percent')) {
                $table->decimal('deposit_markup_percent', 8, 2)->nullable()->after('markup_percent');
            }
            if (! Schema::hasColumn('vendors', 'affiliate_code')) {
                $table->string('affiliate_code', 32)->nullable()->unique()->after('deposit_markup_percent');
            }
            if (! Schema::hasColumn('vendors', 'registration_discount_percent')) {
                $table->decimal('registration_discount_percent', 8, 2)->nullable()->after('affiliate_code');
            }
            if (! Schema::hasColumn('vendors', 'application_discount_percent')) {
                $table->decimal('application_discount_percent', 8, 2)->nullable()->after('registration_discount_percent');
            }
            if (! Schema::hasColumn('vendors', 'affiliate_commission_percent')) {
                $table->decimal('affiliate_commission_percent', 8, 2)->nullable()->after('application_discount_percent');
            }
        });

        if (! Schema::hasTable('affiliate_events')) {
            Schema::create('affiliate_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 30);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('commission_amount', 15, 2)->nullable();
                $table->timestamps();
            });
        }

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_assets', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('asset_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('asset_requests', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            }
        });

        if (! Schema::hasTable('asset_reservations')) {
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
                $table->decimal('deposit_amount', 15, 2)->default(0);
                $table->string('deposit_status', 20)->default('pending');
                $table->timestamp('viewing_completed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'affiliate_vendor_id')) {
                $table->foreignId('affiliate_vendor_id')->nullable()->after('referred_by_customer_id')->constrained('vendors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_reservations');
        Schema::dropIfExists('affiliate_events');

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'affiliate_vendor_id')) {
                $table->dropConstrainedForeignId('affiliate_vendor_id');
            }
        });

        Schema::table('asset_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('asset_requests', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
        });

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            if (Schema::hasColumn('marketplace_assets', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }
        });

        Schema::table('vendors', function (Blueprint $table): void {
            foreach (['deposit_markup_percent', 'affiliate_code', 'registration_discount_percent', 'application_discount_percent', 'affiliate_commission_percent'] as $column) {
                if (Schema::hasColumn('vendors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
