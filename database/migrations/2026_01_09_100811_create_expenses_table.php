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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained();
            $table->foreignId('payee_id')->constrained(); 
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->date('expense_date');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
