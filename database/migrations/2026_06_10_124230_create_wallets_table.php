<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            $table->morphs('holder');

            $table->string('name');

            $table->string('slug')->index();

            $table->uuid('uuid')->unique();

            $table->string('description')->nullable();

            $table->json('meta')->nullable();

            $table->decimal('balance', 64, 0)->default(0);

            $table->unsignedSmallInteger('decimal_places')->default(2);

            $table->timestamps();

            $table->softDeletesTz();

            $table->unique([
                'holder_type',
                'holder_id',
                'slug'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};