<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            $table->string('id_number')->nullable()->after('email');
            $table->string('nssf_number')->nullable()->after('kra_pin');
            $table->string('sha_number')->nullable()->after('nssf_number');
        });
    }

    public function down(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            $table->dropColumn(['id_number', 'nssf_number', 'sha_number']);
        });
    }
};