<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->string('source')->default('local')->after('status')->comment('local, kenyasms, imported');
            $table->string('source_id')->nullable()->after('source')->comment('Original ID from source system');
        });
    }

    public function down()
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_id']);
        });
    }
};