<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_member_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_member_invitations', 'membership_id')) {
                $table->string('membership_id', 40)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('group_member_invitations', 'link_opened_at')) {
                $table->timestamp('link_opened_at')->nullable()->after('responded_at');
            }
            if (! Schema::hasColumn('group_member_invitations', 'registration_started_at')) {
                $table->timestamp('registration_started_at')->nullable()->after('link_opened_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_member_invitations', function (Blueprint $table): void {
            foreach (['membership_id', 'link_opened_at', 'registration_started_at'] as $column) {
                if (Schema::hasColumn('group_member_invitations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
