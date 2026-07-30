<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('guarantor_invitations', 'guarantor_signer_name')) {
                $table->string('guarantor_signer_name', 120)->nullable();
            }
            if (! Schema::hasColumn('guarantor_invitations', 'guarantor_signature_data')) {
                $table->text('guarantor_signature_data')->nullable();
            }
            if (! Schema::hasColumn('guarantor_invitations', 'guarantor_signed_at')) {
                $table->timestamp('guarantor_signed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table): void {
            $columns = ['guarantor_signer_name', 'guarantor_signature_data', 'guarantor_signed_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('guarantor_invitations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
