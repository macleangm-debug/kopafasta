<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $t): void {
            $t->id();
            $t->string('entry_number')->unique();
            $t->date('entry_date');
            $t->string('description');
            $t->nullableMorphs('source');                          // e.g. loan / repayment / expense
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('posted_at')->nullable();
            $t->string('status')->default('posted');               // draft / posted / reversed
            $t->decimal('total_debit', 18, 2)->default(0);
            $t->decimal('total_credit', 18, 2)->default(0);
            $t->string('currency', 3)->default('TZS');
            $t->text('memo')->nullable();
            $t->timestamps();

            $t->index('entry_date');
            $t->index('status');
        });

        Schema::create('journal_entry_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $t->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->decimal('debit', 18, 2)->default(0);
            $t->decimal('credit', 18, 2)->default(0);
            $t->string('description')->nullable();
            $t->unsignedSmallInteger('line_no')->default(1);
            $t->timestamps();

            $t->index(['journal_entry_id', 'chart_of_account_id']);
            $t->index('chart_of_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
