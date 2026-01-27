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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('unit_number');
            $table->string('unit_type');
            $table->decimal('rent_amount', 12, 2);
            $table->enum('status', ['vacant', 'occupied'])->default('vacant');
            $table->timestamps();

            $table->unique(['estate_id', 'unit_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
