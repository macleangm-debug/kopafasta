<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('is_active');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            $columns = [
                'gender'           => fn () => $table->string('gender', 20)->nullable()->after('date_of_birth'),
                'region'           => fn () => $table->string('region')->nullable()->after('address'),
                'district'         => fn () => $table->string('district')->nullable()->after('region'),
                'ward'             => fn () => $table->string('ward')->nullable()->after('district'),
                'street'           => fn () => $table->string('street')->nullable()->after('ward'),
                'activity_type'    => fn () => $table->string('activity_type', 40)->nullable()->after('employment_type'),
                'activity_details' => fn () => $table->json('activity_details')->nullable()->after('activity_type'),
                'income_range'     => fn () => $table->string('income_range', 30)->nullable()->after('monthly_income'),
                'nok_name'         => fn () => $table->string('nok_name')->nullable()->after('income_range'),
                'nok_relationship' => fn () => $table->string('nok_relationship', 40)->nullable()->after('nok_name'),
                'nok_phone'        => fn () => $table->string('nok_phone', 20)->nullable()->after('nok_relationship'),
                'nok_region'       => fn () => $table->string('nok_region')->nullable()->after('nok_phone'),
                'nok_district'     => fn () => $table->string('nok_district')->nullable()->after('nok_region'),
            ];

            foreach ($columns as $name => $callback) {
                if (! Schema::hasColumn('customers', $name)) {
                    $callback();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'preferences')) {
                $table->dropColumn('preferences');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'gender', 'region', 'district', 'ward', 'street',
                'activity_type', 'activity_details', 'income_range',
                'nok_name', 'nok_relationship', 'nok_phone', 'nok_region', 'nok_district',
            ]);
        });
    }
};
