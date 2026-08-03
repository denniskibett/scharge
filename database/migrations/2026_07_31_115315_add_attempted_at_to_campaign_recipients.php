<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttemptedAtToCampaignRecipients extends Migration
{
    public function up()
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->timestamp('attempted_at')->nullable()->after('updated_at');
        });
    }

    public function down()
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn('attempted_at');
        });
    }
}