<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_guests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('source', 40)->default('guest_chat');
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->unsignedInteger('contact_count')->default(0);
            $table->string('registration_status', 32)->default('guest'); // guest | converted
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('converted_as', 20)->nullable(); // member | partner
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['registration_status', 'last_contact_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_guests');
    }
};
