<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('sms_logs', 'message_type')) {
                $table->string('message_type')->default('transactional')->after('message');
            }
            
            if (!Schema::hasColumn('sms_logs', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('sms_logs', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('error_message');
            }
            
            if (!Schema::hasColumn('sms_logs', 'campaign_id')) {
                $table->unsignedBigInteger('campaign_id')->nullable()->after('tenant_id');
            }
            
            if (!Schema::hasColumn('sms_logs', 'cost')) {
                $table->decimal('cost', 10, 2)->nullable()->after('provider_message_id');
            }
        });
    }

    public function down()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'error_message', 'tenant_id', 'campaign_id', 'cost']);
        });
    }
};