<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('sms_logs', 'message_type')) {
                $table->string('message_type')->default('transactional')->after('message');
            }
            
            if (!Schema::hasColumn('sms_logs', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('sms_logs', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('error_message');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('sms_logs', 'campaign_id')) {
                $table->unsignedBigInteger('campaign_id')->nullable()->after('tenant_id');
                $table->foreign('campaign_id')->references('id')->on('sms_campaigns')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('sms_logs', 'cost')) {
                $table->decimal('cost', 10, 2)->nullable()->after('provider_message_id');
            }
            
            // Add indexes for performance
            $table->index('recipient_phone');
            $table->index('status');
            $table->index('campaign_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'error_message', 'tenant_id', 'campaign_id', 'cost']);
        });
    }
};