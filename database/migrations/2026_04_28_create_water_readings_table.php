<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->date('reading_date');
            $table->decimal('previous_reading', 12, 2);
            $table->decimal('current_reading', 12, 2);
            $table->decimal('consumption', 12, 2);
            $table->decimal('charge', 12, 2);
            $table->decimal('rate_per_unit', 12, 2);
            $table->timestamps();
            $table->index('reading_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('water_readings');
    }
};