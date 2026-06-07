<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('guarantor_invitations', 'short_code')) {
                $table->string('short_code', 12)->nullable()->unique()->after('token');
            }
            if (! Schema::hasColumn('guarantor_invitations', 'requested_amount')) {
                $table->unsignedInteger('requested_amount')->nullable()->after('invitee_name');
            }
            if (! Schema::hasColumn('guarantor_invitations', 'requested_tenure_months')) {
                $table->unsignedSmallInteger('requested_tenure_months')->nullable()->after('requested_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('guarantor_invitations', 'short_code')) {
                $table->dropColumn('short_code');
            }
            if (Schema::hasColumn('guarantor_invitations', 'requested_amount')) {
                $table->dropColumn('requested_amount');
            }
            if (Schema::hasColumn('guarantor_invitations', 'requested_tenure_months')) {
                $table->dropColumn('requested_tenure_months');
            }
        });
    }
};
