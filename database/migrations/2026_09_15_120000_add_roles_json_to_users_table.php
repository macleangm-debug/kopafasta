<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'roles')) {
                $table->json('roles')->nullable()->after('role');
            }
        });

        // Backfill: primary role column becomes a one-element roles list.
        DB::table('users')
            ->whereNull('roles')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $code = is_string($row->role ?? null) && $row->role !== ''
                        ? $row->role
                        : null;
                    DB::table('users')->where('id', $row->id)->update([
                        'roles' => $code ? json_encode([$code]) : json_encode([]),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'roles')) {
                $table->dropColumn('roles');
            }
        });
    }
};
