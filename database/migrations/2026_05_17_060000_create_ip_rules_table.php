<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('cidr', 64);
            $table->string('mode', 10); // allow | deny
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cidr', 'mode']);
            $table->index('mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_rules');
    }
};
