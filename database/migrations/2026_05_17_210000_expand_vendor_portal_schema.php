<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('vendors', 'address')) {
                $table->text('address')->nullable();
            }
        });

        Schema::table('vendor_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_tasks', 'customer_name'))    $table->string('customer_name', 120)->nullable()->after('loan_id');
            if (! Schema::hasColumn('vendor_tasks', 'customer_phone'))   $table->string('customer_phone', 30)->nullable()->after('customer_name');
            if (! Schema::hasColumn('vendor_tasks', 'vehicle_details'))  $table->string('vehicle_details', 180)->nullable()->after('customer_phone');
            if (! Schema::hasColumn('vendor_tasks', 'location'))         $table->string('location', 180)->nullable()->after('vehicle_details');
            if (! Schema::hasColumn('vendor_tasks', 'instructions'))     $table->text('instructions')->nullable()->after('location');
            if (! Schema::hasColumn('vendor_tasks', 'fee_amount'))       $table->unsignedInteger('fee_amount')->default(0)->after('instructions');
            if (! Schema::hasColumn('vendor_tasks', 'accepted_at'))      $table->timestamp('accepted_at')->nullable()->after('due_at');
            if (! Schema::hasColumn('vendor_tasks', 'started_at'))       $table->timestamp('started_at')->nullable()->after('accepted_at');
            if (! Schema::hasColumn('vendor_tasks', 'gps_serial'))       $table->string('gps_serial', 60)->nullable()->after('proof_path');
            if (! Schema::hasColumn('vendor_tasks', 'payment_status'))   $table->string('payment_status', 20)->default('pending')->after('gps_serial');
        });

        if (! Schema::hasTable('vendor_documents')) {
            Schema::create('vendor_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('vendor_task_id')->nullable()->constrained('vendor_tasks')->nullOnDelete();
                $table->string('label', 80);
                $table->string('file_path', 255);
                $table->string('mime', 80)->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_payments')) {
            Schema::create('vendor_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('vendor_task_id')->nullable()->constrained('vendor_tasks')->nullOnDelete();
                $table->string('invoice_number', 40)->unique();
                $table->unsignedInteger('amount');
                $table->string('status', 20)->default('pending'); // pending | paid | cancelled
                $table->string('channel', 30)->nullable();
                $table->string('reference', 60)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_documents');
        Schema::table('vendor_tasks', function (Blueprint $table) {
            foreach (['customer_name','customer_phone','vehicle_details','location','instructions','fee_amount','accepted_at','started_at','gps_serial','payment_status'] as $col) {
                if (Schema::hasColumn('vendor_tasks', $col)) $table->dropColumn($col);
            }
        });
    }
};
