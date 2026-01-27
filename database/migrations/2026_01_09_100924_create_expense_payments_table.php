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
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['mpesa', 'bank', 'cash']);
            $table->string('transaction_id')->nullable();
            $table->text('transaction_message')->nullable();
            $table->string('paid_by')->nullable();    // who made the payment
            $table->timestamp('payment_datetime');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
