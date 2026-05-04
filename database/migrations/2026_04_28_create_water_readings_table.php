<?php
// database/migrations/xxxx_xx_xx_create_water_readings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaterReadingsTable extends Migration
{
    public function up()
    {
        Schema::create('water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->decimal('previous_reading', 12, 2)->default(0);
            $table->decimal('current_reading', 12, 2);
            $table->decimal('consumption', 12, 2);
            $table->decimal('rate_applied', 12, 2);
            $table->decimal('charge', 12, 2);
            $table->string('billing_type')->default('consumption'); // flat or consumption
            $table->date('reading_date');
            $table->string('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['unit_id', 'reading_date']);
            $table->index('reading_date');
        });
        
        // Add columns to units table for backward compatibility
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('current_water_reading', 12, 2)->default(0)->change();
            $table->decimal('previous_water_reading', 12, 2)->default(0)->change();
            $table->date('last_reading_date')->nullable()->change();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('water_readings');
    }
}