<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_events')) {
            return;
        }

        Schema::table('affiliate_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliate_events', 'referral_code')) {
                $table->string('referral_code', 40)->nullable()->after('event_type');
            }
            if (! Schema::hasColumn('affiliate_events', 'campaign')) {
                $table->string('campaign', 120)->nullable()->after('referral_code');
            }
            if (! Schema::hasColumn('affiliate_events', 'landing_page')) {
                $table->string('landing_page', 500)->nullable()->after('campaign');
            }
            if (! Schema::hasColumn('affiliate_events', 'referrer_url')) {
                $table->string('referrer_url', 500)->nullable()->after('landing_page');
            }
            if (! Schema::hasColumn('affiliate_events', 'utm_source')) {
                $table->string('utm_source', 120)->nullable()->after('referrer_url');
            }
            if (! Schema::hasColumn('affiliate_events', 'utm_medium')) {
                $table->string('utm_medium', 120)->nullable()->after('utm_source');
            }
            if (! Schema::hasColumn('affiliate_events', 'utm_campaign')) {
                $table->string('utm_campaign', 120)->nullable()->after('utm_medium');
            }
            if (! Schema::hasColumn('affiliate_events', 'utm_term')) {
                $table->string('utm_term', 120)->nullable()->after('utm_campaign');
            }
            if (! Schema::hasColumn('affiliate_events', 'utm_content')) {
                $table->string('utm_content', 120)->nullable()->after('utm_term');
            }
            if (! Schema::hasColumn('affiliate_events', 'device_type')) {
                $table->string('device_type', 20)->nullable()->after('user_agent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliate_events')) {
            return;
        }

        Schema::table('affiliate_events', function (Blueprint $table): void {
            foreach ([
                'referral_code', 'campaign', 'landing_page', 'referrer_url',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'device_type',
            ] as $column) {
                if (Schema::hasColumn('affiliate_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
