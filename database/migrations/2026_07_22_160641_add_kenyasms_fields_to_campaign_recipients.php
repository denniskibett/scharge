<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('campaign_recipients', 'message_id')) {
                $table->string('message_id')->nullable()->after('error_message');
            }
            
            if (!Schema::hasColumn('campaign_recipients', 'provider_status')) {
                $table->string('provider_status')->nullable()->after('message_id');
            }
            
            if (!Schema::hasColumn('campaign_recipients', 'provider_response')) {
                $table->text('provider_response')->nullable()->after('provider_status');
            }
        });
    }

    public function down()
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['message_id', 'provider_status', 'provider_response']);
        });
    }
};