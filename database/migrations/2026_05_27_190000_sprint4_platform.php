<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendors', 'roles')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->json('roles')->nullable()->after('category');
            });
        }

        if (! Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->string('type', 30);
                $table->string('status', 20)->default('draft');
                $table->decimal('discount_percent', 8, 2)->nullable();
                $table->decimal('discount_amount', 15, 2)->nullable();
                $table->string('applies_to', 40)->nullable();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->text('message_template')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('vendors', 'roles')) {
            foreach (DB::table('vendors')->whereNull('roles')->whereNotNull('category')->get() as $vendor) {
                DB::table('vendors')
                    ->where('id', $vendor->id)
                    ->update(['roles' => json_encode([$vendor->category])]);
            }
        }

        if (Schema::hasTable('branches')) {
            DB::table('branches')
                ->where('code', '!=', 'HQ001')
                ->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');

        if (Schema::hasColumn('vendors', 'roles')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->dropColumn('roles');
            });
        }
    }
};
