<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_conversations', 'conversation_number')) {
                $table->string('conversation_number')->nullable()->unique()->after('id');
            }
        });

        // Backfill existing rows as KPF-CNV-XXXXXX padded from id.
        $rows = DB::table('support_conversations')
            ->whereNull('conversation_number')
            ->orderBy('id')
            ->get(['id']);

        $maxSeq = 0;
        foreach ($rows as $row) {
            $seq = (int) $row->id;
            $maxSeq = max($maxSeq, $seq);
            $number = 'KPF-CNV-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            DB::table('support_conversations')
                ->where('id', $row->id)
                ->update(['conversation_number' => $number]);
        }

        if ($maxSeq > 0) {
            $key = 'support.conversation_number_seq.kpf';
            $existing = DB::table('system_settings')->where('key', $key)->value('value');
            $current = is_numeric($existing) ? (int) $existing : 0;
            if ($maxSeq > $current) {
                if ($existing === null) {
                    DB::table('system_settings')->insert([
                        'key' => $key,
                        'value' => (string) $maxSeq,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('system_settings')->where('key', $key)->update([
                        'value' => (string) $maxSeq,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('support_conversations', 'conversation_number')) {
                $table->dropUnique(['conversation_number']);
                $table->dropColumn('conversation_number');
            }
        });
    }
};
