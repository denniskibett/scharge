<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Rent automation (docs/RENT_AUTOMATION.md): every bill and arrears reminder sent to a tenant, one row per invoice and
| stage, so the scheduler never sends the same message twice. Self-contained: run it alone with
|   php artisan migrate --path=database/migrations/2026_11_10_120000_create_rent_messages_table.php --force
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rent_messages')) {
            return;
        }

        Schema::create('rent_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('tenancy_id')->index();
            $table->unsignedBigInteger('invoice_id');
            $table->string('kind', 20); // bill | reminder
            $table->unsignedSmallInteger('stage')->default(0); // reminders: days after the due date
            $table->string('channel', 20)->nullable(); // sms | whatsapp
            $table->string('phone', 40)->nullable();
            $table->string('status', 20); // sent | failed | no_phone
            $table->string('error', 255)->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'kind', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_messages');
    }
};
