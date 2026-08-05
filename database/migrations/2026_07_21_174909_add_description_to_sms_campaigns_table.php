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
        // Only add columns to sms_campaigns table
        Schema::table('sms_campaigns', function (Blueprint $table) {
            // Add description column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            // Add campaign_type column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'campaign_type')) {
                $table->string('campaign_type')->default('general')->after('template_id');
            }
            
            // Add sent_count column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'sent_count')) {
                $table->integer('sent_count')->default(0)->after('total_recipients');
            }
            
            // Add failed_count column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'failed_count')) {
                $table->integer('failed_count')->default(0)->after('sent_count');
            }
            
            // Add sent_at column if it doesn't exist
            if (!Schema::hasColumn('sms_campaigns', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'campaign_type',
                'sent_count',
                'failed_count',
                'sent_at'
            ]);
        });
    }
};