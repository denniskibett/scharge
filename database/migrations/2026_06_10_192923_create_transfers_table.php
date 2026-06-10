<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('from_id')->index();

            $table->unsignedBigInteger('to_id')->index();

            $table->enum('status', [
                'exchange',
                'transfer',
                'paid',
                'refund',
                'gift'
            ])->default('transfer');

            $table->enum('status_last', [
                'exchange',
                'transfer',
                'paid',
                'refund',
                'gift'
            ])->nullable();

            $table->foreignId('deposit_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->foreignId('withdraw_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->decimal('discount', 64, 0)
                ->default(0);

            $table->decimal('fee', 64, 0)
                ->default(0);

            $table->json('extra')
                ->nullable();

            $table->uuid('uuid')
                ->unique();

            $table->timestamps();

            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};