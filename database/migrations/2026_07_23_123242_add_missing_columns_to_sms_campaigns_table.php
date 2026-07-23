<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            // Add description column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            // Add campaign_type column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'campaign_type')) {
                $table->string('campaign_type', 50)->default('transactional')->after('filters');
            }
            
            // Add delivered_count column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'delivered_count')) {
                $table->integer('delivered_count')->default(0)->after('failed_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn(['description', 'campaign_type', 'delivered_count']);
        });
    }
};