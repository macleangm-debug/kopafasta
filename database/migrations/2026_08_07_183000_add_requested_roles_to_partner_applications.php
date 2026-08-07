<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'requested_roles')) {
                $table->json('requested_roles')->nullable()->after('partner_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_applications', 'requested_roles')) {
                $table->dropColumn('requested_roles');
            }
        });
    }
};
