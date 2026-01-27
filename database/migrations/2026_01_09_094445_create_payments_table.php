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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // tenant
            $table->foreignId('invoice_id')->nullable()->constrained();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['mpesa', 'bank', 'cash']);
            $table->string('transaction_id')->nullable();
            $table->text('transaction_message')->nullable();
            $table->string('paid_to')->nullable();
            $table->string('payer_name')->nullable();
            $table->timestamp('payment_datetime');
            $table->string('payment_month');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
