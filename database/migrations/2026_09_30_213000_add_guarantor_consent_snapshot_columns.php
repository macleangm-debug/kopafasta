<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('guarantor_invitations', 'consent_snapshot')) {
                $table->json('consent_snapshot')->nullable()->after('response_notes');
            }
            if (! Schema::hasColumn('guarantor_invitations', 'confirmation_status')) {
                $table->string('confirmation_status', 40)->nullable()->after('consent_snapshot');
            }
            if (! Schema::hasColumn('guarantor_invitations', 'consent_history')) {
                $table->json('consent_history')->nullable()->after('confirmation_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table) {
            foreach (['consent_history', 'confirmation_status', 'consent_snapshot'] as $column) {
                if (Schema::hasColumn('guarantor_invitations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
