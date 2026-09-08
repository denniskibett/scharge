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
        Schema::table('security', function (Blueprint $table) {
            // Add missing vehicle columns
            $table->string('vehicle_type')->nullable()->after('vehicle_registration_snapshot');
            $table->string('vehicle_model')->nullable()->after('vehicle_type');
            $table->string('vehicle_color')->nullable()->after('vehicle_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'vehicle_model', 'vehicle_color']);
        });
    }
};